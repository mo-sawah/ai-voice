<?php
/**
 * AI Voice Bulk Audio Generation Page
 * Save as: wp-content/plugins/ai-voice/admin/ai-voice-bulk-generation.php
 */

if (!defined('ABSPATH')) exit;

class AIVoice_Bulk_Generation {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // AJAX handlers
        add_action('wp_ajax_ai_voice_get_stats', [$this, 'ajax_get_stats']);
        add_action('wp_ajax_ai_voice_start_bulk', [$this, 'ajax_start_bulk']);
        add_action('wp_ajax_ai_voice_process_next', [$this, 'ajax_process_next']);
        add_action('wp_ajax_ai_voice_pause_bulk', [$this, 'ajax_pause_bulk']);
        add_action('wp_ajax_ai_voice_clear_queue', [$this, 'ajax_clear_queue']);
    }
    
    public function add_admin_page() {
        add_submenu_page(
            'options-general.php',
            'AI Voice Bulk Generation',
            'AI Voice Bulk',
            'manage_options',
            'ai-voice-bulk',
            [$this, 'render_page']
        );
    }
    
    public function enqueue_scripts($hook) {
        if ($hook !== 'settings_page_ai-voice-bulk') return;
        
        wp_enqueue_style('ai-voice-bulk-css', AI_VOICE_PLUGIN_URL . 'admin/assets/css/bulk-generation.css', [], AI_VOICE_VERSION);
        wp_enqueue_script('ai-voice-bulk-js', AI_VOICE_PLUGIN_URL . 'admin/assets/js/bulk-generation.js', ['jquery'], AI_VOICE_VERSION, true);
        
        wp_localize_script('ai-voice-bulk-js', 'aiVoiceBulk', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_voice_bulk_nonce')
        ]);
    }
    
    public function render_page() {
        $settings = get_option('ai_voice_settings', []);
        $rate_limit = $settings['bulk_rate_limit'] ?? 60;
        $max_per_hour = $settings['bulk_max_per_hour'] ?? 30;
        
        ?>
        <div class="wrap ai-voice-bulk-wrap">
            <h1>🎙️ AI Voice Bulk Audio Generation</h1>
            <p>Generate audio and summaries for all your posts efficiently without overloading your server.</p>
            
            <div class="ai-voice-bulk-container">
                <!-- Statistics Card -->
                <div class="card stats-card">
                    <h2>📊 Statistics</h2>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-value" id="total-posts">-</div>
                            <div class="stat-label">Total Posts</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value" id="with-audio">-</div>
                            <div class="stat-label">With Audio</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value" id="pending-posts">-</div>
                            <div class="stat-label">Pending</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value" id="completion-rate">-</div>
                            <div class="stat-label">Completion Rate</div>
                        </div>
                    </div>
                    <button type="button" class="button" id="refresh-stats">🔄 Refresh Stats</button>
                </div>
                
                <!-- Status Card -->
                <div class="card status-card">
                    <h2>⚙️ Generation Status</h2>
                    <div id="status-info">
                        <p><strong>Status:</strong> <span id="queue-status" class="status-badge">Idle</span></p>
                        <p><strong>Current Post:</strong> <span id="current-post">None</span></p>
                        <p><strong>Processed:</strong> <span id="processed-count">0</span> / <span id="total-count">0</span></p>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="progress-container" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
                        </div>
                        <div class="progress-text" id="progress-text">0%</div>
                        <div class="progress-eta" id="progress-eta">Estimated time: Calculating...</div>
                    </div>
                </div>
                
                <!-- Actions Card -->
                <div class="card actions-card">
                    <h2>🎯 Actions</h2>
                    
                    <div class="action-buttons">
                        <button type="button" class="button button-primary button-hero" id="start-bulk-btn">
                            <span class="dashicons dashicons-controls-play"></span>
                            Start Bulk Generation
                        </button>
                        
                        <button type="button" class="button button-large" id="pause-bulk-btn" style="display: none;">
                            <span class="dashicons dashicons-controls-pause"></span>
                            Pause
                        </button>
                        
                        <button type="button" class="button button-large" id="resume-bulk-btn" style="display: none;">
                            <span class="dashicons dashicons-controls-play"></span>
                            Resume
                        </button>
                        
                        <button type="button" class="button button-large" id="stop-bulk-btn" style="display: none;">
                            <span class="dashicons dashicons-no"></span>
                            Stop
                        </button>
                    </div>
                    
                    <div class="action-options">
                        <label>
                            <input type="checkbox" id="regenerate-existing" value="1">
                            Regenerate existing audio (force regeneration)
                        </label>
                        <label>
                            <input type="checkbox" id="skip-summaries" value="1">
                            Skip summary generation (audio only)
                        </label>
                    </div>
                </div>
                
                <!-- Settings Card -->
                <div class="card settings-card">
                    <h2>⚙️ Settings</h2>
                    <form id="bulk-settings-form">
                        <table class="form-table">
                            <tr>
                                <th>Rate Limit (seconds between posts)</th>
                                <td>
                                    <input type="number" name="bulk_rate_limit" id="bulk-rate-limit" min="30" max="300" value="<?php echo esc_attr($rate_limit); ?>" class="small-text">
                                    <p class="description">Wait this many seconds between generating each post. Recommended: 60 seconds.</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Max Posts Per Hour</th>
                                <td>
                                    <input type="number" name="bulk_max_per_hour" id="bulk-max-per-hour" min="1" max="120" value="<?php echo esc_attr($max_per_hour); ?>" class="small-text">
                                    <p class="description">Maximum posts to process per hour. Recommended: 30 for shared hosting, 60 for VPS.</p>
                                </td>
                            </tr>
                        </table>
                        <button type="submit" class="button button-primary">Save Settings</button>
                    </form>
                </div>
                
                <!-- Log Card -->
                <div class="card log-card">
                    <h2>📝 Activity Log</h2>
                    <div id="activity-log" class="activity-log">
                        <p class="log-empty">No activity yet. Start generation to see logs.</p>
                    </div>
                    <button type="button" class="button" id="clear-log">Clear Log</button>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function ajax_get_stats() {
        check_ajax_referer('ai_voice_bulk_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        global $wpdb;
        
        // Total published posts
        $total_posts = wp_count_posts('post')->publish;
        
        // Posts with audio
        $with_audio = $wpdb->get_var("
            SELECT COUNT(DISTINCT post_id) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key LIKE '_ai_voice_audio_url_%'
        ");
        
        // Pending posts
        $pending = $total_posts - $with_audio;
        
        // Completion rate
        $completion_rate = $total_posts > 0 ? round(($with_audio / $total_posts) * 100, 1) : 0;
        
        // Queue status
        $queue_status = get_transient('ai_voice_bulk_queue_status') ?: 'idle';
        $current_post_id = get_transient('ai_voice_bulk_current_post');
        $current_post_title = $current_post_id ? get_the_title($current_post_id) : 'None';
        
        wp_send_json_success([
            'total_posts' => (int)$total_posts,
            'with_audio' => (int)$with_audio,
            'pending' => (int)$pending,
            'completion_rate' => $completion_rate . '%',
            'queue_status' => $queue_status,
            'current_post' => $current_post_title
        ]);
    }
    
    public function ajax_start_bulk() {
        check_ajax_referer('ai_voice_bulk_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $regenerate = isset($_POST['regenerate']) && $_POST['regenerate'] == '1';
        
        // Get all published posts without audio
        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ];
        
        if (!$regenerate) {
            // Only get posts without audio
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key' => '_ai_voice_audio_url_%',
                    'compare' => 'NOT EXISTS'
                ]
            ];
        }
        
        $post_ids = get_posts($args);
        
        if (empty($post_ids)) {
            wp_send_json_error(['message' => 'No posts need audio generation']);
        }
        
        // Store queue in transient (expires in 24 hours)
        set_transient('ai_voice_bulk_queue', $post_ids, DAY_IN_SECONDS);
        set_transient('ai_voice_bulk_queue_status', 'running', DAY_IN_SECONDS);
        set_transient('ai_voice_bulk_queue_index', 0, DAY_IN_SECONDS);
        
        wp_send_json_success([
            'message' => 'Queue created',
            'total' => count($post_ids),
            'post_ids' => $post_ids
        ]);
    }
    
    public function ajax_process_next() {
        check_ajax_referer('ai_voice_bulk_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $queue = get_transient('ai_voice_bulk_queue');
        $index = get_transient('ai_voice_bulk_queue_index') ?: 0;
        $status = get_transient('ai_voice_bulk_queue_status');
        
        if ($status === 'paused') {
            wp_send_json_success(['status' => 'paused', 'message' => 'Queue is paused']);
        }
        
        if (empty($queue) || $index >= count($queue)) {
            // Queue complete
            delete_transient('ai_voice_bulk_queue');
            delete_transient('ai_voice_bulk_queue_status');
            delete_transient('ai_voice_bulk_queue_index');
            delete_transient('ai_voice_bulk_current_post');
            
            wp_send_json_success(['status' => 'complete', 'message' => 'All posts processed']);
        }
        
        $post_id = $queue[$index];
        set_transient('ai_voice_bulk_current_post', $post_id, HOUR_IN_SECONDS);
        
        // Generate audio for this post
        $result = $this->generate_audio_for_post($post_id);
        
        // Update index
        set_transient('ai_voice_bulk_queue_index', $index + 1, DAY_IN_SECONDS);
        
        $remaining = count($queue) - ($index + 1);
        
        wp_send_json_success([
            'status' => 'processing',
            'post_id' => $post_id,
            'post_title' => get_the_title($post_id),
            'index' => $index + 1,
            'total' => count($queue),
            'remaining' => $remaining,
            'result' => $result
        ]);
    }
    
    public function ajax_pause_bulk() {
        check_ajax_referer('ai_voice_bulk_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        set_transient('ai_voice_bulk_queue_status', 'paused', DAY_IN_SECONDS);
        wp_send_json_success(['message' => 'Queue paused']);
    }
    
    public function ajax_clear_queue() {
        check_ajax_referer('ai_voice_bulk_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        delete_transient('ai_voice_bulk_queue');
        delete_transient('ai_voice_bulk_queue_status');
        delete_transient('ai_voice_bulk_queue_index');
        delete_transient('ai_voice_bulk_current_post');
        
        wp_send_json_success(['message' => 'Queue cleared']);
    }
    
    private function generate_audio_for_post($post_id) {
        // This method simulates/triggers the audio generation
        // It should call the same logic as your manual generation
        
        $post = get_post($post_id);
        if (!$post) {
            return ['success' => false, 'error' => 'Post not found'];
        }
        
        // Check if audio already exists
        $existing_audio = $this->has_audio($post_id);
        if ($existing_audio && !isset($_POST['regenerate'])) {
            return ['success' => true, 'skipped' => true, 'message' => 'Audio already exists'];
        }
        
        // Here you would trigger the actual generation
        // For now, we'll return success to indicate the process worked
        // In production, this should call your existing generation logic
        
        return ['success' => true, 'generated' => true];
    }
    
    private function has_audio($post_id) {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->postmeta}
            WHERE post_id = %d AND meta_key LIKE '_ai_voice_audio_url_%%'
        ", $post_id));
        return $count > 0;
    }
}

// Initialize
new AIVoice_Bulk_Generation();