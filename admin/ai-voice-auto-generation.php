<?php
/**
 * AI Voice Auto-Generation System
 * Save as: wp-content/plugins/ai-voice/admin/ai-voice-auto-generation.php
 * 
 * Automatically generates audio for published posts with smart rate limiting
 */

if (!defined('ABSPATH')) exit;

class AIVoice_Auto_Generation {
    
    public function __construct() {
        // Hook into post publishing
        add_action('transition_post_status', [$this, 'on_post_publish'], 10, 3);
        
        // WP-Cron hook for processing queue
        add_action('ai_voice_process_auto_queue', [$this, 'process_auto_queue']);
        
        // AJAX handler for saving settings
        add_action('wp_ajax_ai_voice_save_bulk_settings', [$this, 'save_bulk_settings']);
        
        // Register custom cron schedule
        add_filter('cron_schedules', [$this, 'add_cron_schedule']);
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
            return;
        }
        
        // Check if post already has audio
        if ($this->has_audio($post->ID)) {
            return;
        }
        
        // Add to queue
        $this->add_to_auto_queue($post->ID);
        
        // Schedule processing if not already scheduled
        if (!wp_next_scheduled('ai_voice_process_auto_queue')) {
            $delay = $settings['auto_generate_delay'] ?? 120; // 2 minutes default
            wp_schedule_single_event(time() + $delay, 'ai_voice_process_auto_queue');
        }
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
        
        // Log for debugging
        error_log("AI Voice: Added post {$post_id} to auto-generation queue");
    }
    
    /**
     * Process auto-generation queue
     */
    public function process_auto_queue() {
        $queue = get_option('ai_voice_auto_queue', []);
        
        if (empty($queue)) {
            return;
        }
        
        $settings = get_option('ai_voice_settings', []);
        $rate_limit = $settings['bulk_rate_limit'] ?? 60;
        $max_per_hour = $settings['bulk_max_per_hour'] ?? 30;
        
        // Check hourly limit
        $processed_this_hour = get_transient('ai_voice_hourly_count') ?: 0;
        if ($processed_this_hour >= $max_per_hour) {
            // Reschedule for next hour
            wp_schedule_single_event(time() + 3600, 'ai_voice_process_auto_queue');
            error_log("AI Voice: Hourly limit reached ({$max_per_hour}), rescheduling for next hour");
            return;
        }
        
        // Check rate limit
        $last_processed = get_transient('ai_voice_last_processed_time');
        if ($last_processed && (time() - $last_processed) < $rate_limit) {
            // Too soon, reschedule
            $wait_time = $rate_limit - (time() - $last_processed);
            wp_schedule_single_event(time() + $wait_time, 'ai_voice_process_auto_queue');
            return;
        }
        
        // Get next post from queue
        $post_id = array_shift($queue);
        update_option('ai_voice_auto_queue', $queue);
        
        // Verify post exists and is published
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            // Skip this post, process next if any
            if (!empty($queue)) {
                wp_schedule_single_event(time() + 5, 'ai_voice_process_auto_queue');
            }
            return;
        }
        
        // Check if audio already exists (in case it was manually generated)
        if ($this->has_audio($post_id)) {
            error_log("AI Voice: Post {$post_id} already has audio, skipping");
            // Process next in queue
            if (!empty($queue)) {
                wp_schedule_single_event(time() + 5, 'ai_voice_process_auto_queue');
            }
            return;
        }
        
        // Generate audio
        $result = $this->trigger_audio_generation($post_id);
        
        // Update counters
        set_transient('ai_voice_last_processed_time', time(), HOUR_IN_SECONDS);
        set_transient('ai_voice_hourly_count', $processed_this_hour + 1, HOUR_IN_SECONDS);
        
        // Log result
        if ($result) {
            error_log("AI Voice: Successfully generated audio for post {$post_id}");
        } else {
            error_log("AI Voice: Failed to generate audio for post {$post_id}");
        }
        
        // Schedule next post in queue
        if (!empty($queue)) {
            wp_schedule_single_event(time() + $rate_limit, 'ai_voice_process_auto_queue');
        }
    }
    
    /**
     * Trigger audio generation for a post
     * This simulates a user clicking the play button
     */
    private function trigger_audio_generation($post_id) {
        // Set a flag to indicate this is auto-generation
        set_transient('ai_voice_auto_generating_' . $post_id, true, 300); // 5 minutes
        
        // In a production environment, you would:
        // 1. Extract post content
        // 2. Call your TTS server
        // 3. Generate summary if enabled
        // 4. Save audio URL to post meta
        
        // For now, we'll just mark it as processed
        // You should integrate this with your existing generation logic in display.php
        
        // Example: Trigger generation via internal HTTP request
        $site_url = home_url();
        $generate_url = add_query_arg([
            'ai_voice_auto_generate' => '1',
            'post_id' => $post_id,
            'nonce' => wp_create_nonce('ai_voice_auto_generate_' . $post_id)
        ], admin_url('admin-ajax.php'));
        
        // Use wp_remote_post with a short timeout (non-blocking)
        wp_remote_post($generate_url, [
            'timeout' => 1, // Very short timeout to avoid blocking
            'blocking' => false, // Non-blocking request
            'sslverify' => false
        ]);
        
        return true;
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
            'next_scheduled' => wp_next_scheduled('ai_voice_process_auto_queue') ? date('Y-m-d H:i:s', wp_next_scheduled('ai_voice_process_auto_queue')) : 'Not scheduled'
        ];
    }
}

// Initialize
new AIVoice_Auto_Generation();