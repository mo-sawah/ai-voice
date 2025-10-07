<?php
/**
 * AI Voice Auto-Generation System (FIXED VERSION)
 * Automatically generates audio + summaries for published posts
 */

if (!defined('ABSPATH')) exit;

class AIVoice_Auto_Generation {
    
    private $display_class;
    
    public function __construct() {
        // Hook into post publishing
        add_action('transition_post_status', [$this, 'on_post_publish'], 10, 3);
        
        // WP-Cron hook for processing queue
        add_action('ai_voice_process_auto_queue', [$this, 'process_auto_queue']);
        
        // ✅ NEW: Add the missing AJAX handler
        add_action('wp_ajax_ai_voice_auto_generate', [$this, 'handle_auto_generate_ajax']);
        add_action('wp_ajax_nopriv_ai_voice_auto_generate', [$this, 'handle_auto_generate_ajax']);
        
        // AJAX handler for saving settings
        add_action('wp_ajax_ai_voice_save_bulk_settings', [$this, 'save_bulk_settings']);
        
        // Register custom cron schedule
        add_filter('cron_schedules', [$this, 'add_cron_schedule']);
        
        // ✅ NEW: Admin notice for debugging
        add_action('admin_notices', [$this, 'show_admin_notices']);
    }
    
    /**
     * ✅ NEW: Show admin notices for debugging
     */
    public function show_admin_notices() {
        if (!current_user_can('manage_options')) return;
        
        $settings = get_option('ai_voice_settings', []);
        $auto_generate = $settings['auto_generate_on_publish'] ?? false;
        
        if ($auto_generate) {
            $queue = get_option('ai_voice_auto_queue', []);
            if (!empty($queue)) {
                $queue_size = count($queue);
                $next_run = wp_next_scheduled('ai_voice_process_auto_queue');
                $time_until = $next_run ? human_time_diff(time(), $next_run) : 'Not scheduled';
                
                echo '<div class="notice notice-info is-dismissible">';
                echo '<p><strong>🎙️ AI Voice Auto-Generation Active:</strong> ';
                echo sprintf('%d post(s) in queue. Next processing in %s.', $queue_size, $time_until);
                echo '</p></div>';
            }
        }
    }
    
    /**
     * Add custom cron schedule (every 2 minutes)
     */
    public function add_cron_schedule($schedules) {
        $schedules['every_2_minutes'] = [
            'interval' => 120,
            'display' => __('Every 2 Minutes')
        ];
        return $schedules;
    }
    
    /**
     * Hook when post is published
     */
    public function on_post_publish($new_status, $old_status, $post) {
        // Only for posts
        if ($post->post_type !== 'post') {
            return;
        }
        
        // Only when transitioning to published
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }
        
        // Check if auto-generation is enabled
        $settings = get_option('ai_voice_settings', []);
        $auto_generate = $settings['auto_generate_on_publish'] ?? false;
        
        if (!$auto_generate) {
            error_log("AI Voice: Auto-generation disabled in settings");
            return;
        }
        
        // Check if post already has audio
        if ($this->has_audio($post->ID)) {
            error_log("AI Voice: Post {$post->ID} already has audio, skipping");
            return;
        }
        
        // ✅ Check if post belongs to disabled category
        if ($this->is_category_disabled($post->ID)) {
            error_log("AI Voice: Post {$post->ID} belongs to disabled category, skipping");
            return;
        }
        
        // Add to queue
        $this->add_to_auto_queue($post->ID);
        
        // Schedule processing if not already scheduled
        if (!wp_next_scheduled('ai_voice_process_auto_queue')) {
            $delay = $settings['auto_generate_delay'] ?? 120; // 2 minutes default
            wp_schedule_single_event(time() + $delay, 'ai_voice_process_auto_queue');
            error_log("AI Voice: Scheduled queue processing in {$delay} seconds");
        }
    }
    
    /**
     * ✅ NEW: Check if post belongs to disabled category
     */
    private function is_category_disabled($post_id) {
        $settings = get_option('ai_voice_settings', []);
        $disabled_categories = isset($settings['disabled_categories']) ? (array)$settings['disabled_categories'] : array();
        
        if (empty($disabled_categories)) {
            return false;
        }
        
        $post_categories = wp_get_post_categories($post_id);
        
        foreach ($post_categories as $cat_id) {
            if (in_array($cat_id, $disabled_categories)) {
                return true;
            }
            
            // Check parent categories
            $parent_cats = get_ancestors($cat_id, 'category');
            foreach ($parent_cats as $parent_id) {
                if (in_array($parent_id, $disabled_categories)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Add post to auto-generation queue
     */
    private function add_to_auto_queue($post_id) {
        $queue = get_option('ai_voice_auto_queue', []);
        
        // Don't add if already in queue
        if (in_array($post_id, $queue)) {
            return;
        }
        
        $queue[] = $post_id;
        update_option('ai_voice_auto_queue', $queue);
        
        error_log("AI Voice: Added post {$post_id} (" . get_the_title($post_id) . ") to auto-generation queue");
    }
    
    /**
     * Process auto-generation queue
     */
    public function process_auto_queue() {
        error_log("AI Voice: Processing auto-generation queue");
        
        $queue = get_option('ai_voice_auto_queue', []);
        
        if (empty($queue)) {
            error_log("AI Voice: Queue is empty");
            return;
        }
        
        $settings = get_option('ai_voice_settings', []);
        $rate_limit = $settings['bulk_rate_limit'] ?? 60;
        $max_per_hour = $settings['bulk_max_per_hour'] ?? 30;
        
        // Check hourly limit
        $processed_this_hour = get_transient('ai_voice_hourly_count') ?: 0;
        if ($processed_this_hour >= $max_per_hour) {
            wp_schedule_single_event(time() + 3600, 'ai_voice_process_auto_queue');
            error_log("AI Voice: Hourly limit reached ({$max_per_hour}), rescheduling for next hour");
            return;
        }
        
        // Check rate limit
        $last_processed = get_transient('ai_voice_last_processed_time');
        if ($last_processed && (time() - $last_processed) < $rate_limit) {
            $wait_time = $rate_limit - (time() - $last_processed);
            wp_schedule_single_event(time() + $wait_time, 'ai_voice_process_auto_queue');
            error_log("AI Voice: Rate limit active, waiting {$wait_time} seconds");
            return;
        }
        
        // Get next post from queue
        $post_id = array_shift($queue);
        update_option('ai_voice_auto_queue', $queue);
        
        error_log("AI Voice: Processing post {$post_id} from queue");
        
        // Verify post exists and is published
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            error_log("AI Voice: Post {$post_id} not found or not published, skipping");
            if (!empty($queue)) {
                wp_schedule_single_event(time() + 5, 'ai_voice_process_auto_queue');
            }
            return;
        }
        
        // Check if audio already exists
        if ($this->has_audio($post_id)) {
            error_log("AI Voice: Post {$post_id} already has audio, skipping");
            if (!empty($queue)) {
                wp_schedule_single_event(time() + 5, 'ai_voice_process_auto_queue');
            }
            return;
        }
        
        // ✅ FIXED: Actually generate the audio
        $result = $this->generate_audio_and_summary($post_id);
        
        // Update counters
        set_transient('ai_voice_last_processed_time', time(), HOUR_IN_SECONDS);
        set_transient('ai_voice_hourly_count', $processed_this_hour + 1, HOUR_IN_SECONDS);
        
        // Log result
        if ($result['success']) {
            error_log("AI Voice: ✅ Successfully generated audio for post {$post_id}");
        } else {
            error_log("AI Voice: ❌ Failed to generate audio for post {$post_id}: " . $result['error']);
        }
        
        // Schedule next post in queue
        if (!empty($queue)) {
            wp_schedule_single_event(time() + $rate_limit, 'ai_voice_process_auto_queue');
            error_log("AI Voice: Scheduled next queue processing in {$rate_limit} seconds");
        } else {
            error_log("AI Voice: Queue processing complete");
        }
    }
    
    /**
     * ✅ NEW: Actually generate audio and summary (proper implementation)
     */
    private function generate_audio_and_summary($post_id) {
        try {
            // Get display class instance
            if (!$this->display_class) {
                require_once AI_VOICE_PLUGIN_DIR . 'public/display.php';
                $this->display_class = new AIVoice_Public();
            }
            
            $settings = get_option('ai_voice_settings', []);
            
            // Extract post text
            $reflection = new ReflectionClass($this->display_class);
            $method = $reflection->getMethod('get_post_plain_text');
            $method->setAccessible(true);
            $raw_text = $method->invoke($this->display_class, $post_id);
            
            if (empty($raw_text) || strlen(trim($raw_text)) < 10) {
                return ['success' => false, 'error' => 'No readable text found'];
            }
            
            // Clean text
            $strip_method = $reflection->getMethod('strip_leading_parenthetical_dateline');
            $strip_method->setAccessible(true);
            $raw_text = $strip_method->invoke($this->display_class, $raw_text);
            
            $max_total_chars = 8000;
            if (strlen($raw_text) > $max_total_chars) {
                $raw_text = substr($raw_text, 0, $max_total_chars) . '...';
            }
            
            $clean_method = $reflection->getMethod('clean_text_for_tts');
            $clean_method->setAccessible(true);
            $final_text = $clean_method->invoke($this->display_class, $raw_text);
            
            // Generate audio
            $ai_service = get_post_meta($post_id, '_ai_voice_ai_service', true) ?: ($settings['default_ai'] ?? 'google');
            if ($ai_service === 'default') {
                $ai_service = $settings['default_ai'] ?? 'google';
            }
            
            error_log("AI Voice: Generating audio for post {$post_id} using {$ai_service}");
            
            $generation_method = get_post_meta($post_id, '_ai_voice_generation_method', true);
            if ($generation_method === 'default' || empty($generation_method)) {
                $generation_method = $settings['generation_method'] ?? 'chunked';
            }
            
            // Generate based on method
            if ($ai_service === 'openai' || $generation_method === 'single') {
                $gen_method = $reflection->getMethod('generate_single_request_audio');
                $gen_method->setAccessible(true);
                $audio_result = $gen_method->invoke($this->display_class, $post_id, $final_text);
            } else {
                $gen_method = $reflection->getMethod('generate_chunked_audio');
                $gen_method->setAccessible(true);
                $audio_result = $gen_method->invoke($this->display_class, $post_id, $final_text);
            }
            
            if (is_wp_error($audio_result)) {
                return ['success' => false, 'error' => $audio_result->get_error_message()];
            }
            
            // ✅ Generate summary if enabled
            $enable_summary = $settings['enable_summary'] ?? false;
            if ($enable_summary) {
                error_log("AI Voice: Generating summary for post {$post_id}");
                
                $summary_method = $reflection->getMethod('generate_summary');
                $summary_method->setAccessible(true);
                $summary_result = $summary_method->invoke($this->display_class, $post_id, $final_text);
                
                if (!is_wp_error($summary_result)) {
                    $content_hash = md5($final_text);
                    update_post_meta($post_id, '_ai_voice_summary_' . $content_hash, $summary_result);
                    error_log("AI Voice: ✅ Summary generated for post {$post_id}");
                } else {
                    error_log("AI Voice: ⚠️ Summary generation failed: " . $summary_result->get_error_message());
                }
            }
            
            return ['success' => true, 'audio_url' => $audio_result];
            
        } catch (Exception $e) {
            error_log("AI Voice: Exception during generation: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * ✅ NEW: AJAX handler for auto-generation (the missing piece!)
     */
    public function handle_auto_generate_ajax() {
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        $nonce = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '';
        
        if (!$post_id || !wp_verify_nonce($nonce, 'ai_voice_auto_generate_' . $post_id)) {
            wp_send_json_error(['message' => 'Invalid request']);
        }
        
        $result = $this->generate_audio_and_summary($post_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Check if post has audio
     */
    private function has_audio($post_id) {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->postmeta}
            WHERE post_id = %d AND meta_key LIKE '_ai_voice_audio_url_%%'
        ", $post_id));
        return $count > 0;
    }
    
    /**
     * AJAX handler for saving bulk settings
     */
    public function save_bulk_settings() {
        check_ajax_referer('ai_voice_bulk_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $rate_limit = isset($_POST['rate_limit']) ? intval($_POST['rate_limit']) : 60;
        $max_per_hour = isset($_POST['max_per_hour']) ? intval($_POST['max_per_hour']) : 30;
        
        $settings = get_option('ai_voice_settings', []);
        $settings['bulk_rate_limit'] = max(30, min(300, $rate_limit));
        $settings['bulk_max_per_hour'] = max(1, min(120, $max_per_hour));
        
        update_option('ai_voice_settings', $settings);
        
        wp_send_json_success(['message' => 'Settings saved']);
    }
    
    /**
     * Get queue status (for display)
     */
    public static function get_queue_status() {
        $queue = get_option('ai_voice_auto_queue', []);
        $processed_this_hour = get_transient('ai_voice_hourly_count') ?: 0;
        $last_processed = get_transient('ai_voice_last_processed_time');
        
        return [
            'queue_size' => count($queue),
            'processed_this_hour' => $processed_this_hour,
            'last_processed' => $last_processed ? human_time_diff($last_processed) . ' ago' : 'Never',
            'next_scheduled' => wp_next_scheduled('ai_voice_process_auto_queue') ? 
                date('Y-m-d H:i:s', wp_next_scheduled('ai_voice_process_auto_queue')) : 'Not scheduled'
        ];
    }
}

// Initialize
new AIVoice_Auto_Generation();