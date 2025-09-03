<?php
/**
 * Plugin Name:       AI Voice - Google TTS
 * Plugin URI:        https://sawahsolutions.com
 * Description:       High-quality audio players using Google Cloud Text-to-Speech API with premium voices.
 * Version:           2.0.0
 * Author:            Mohamed Sawah
 * Author URI:        https://sawahsolutions.com
 * License:           GPL-2.0+
 * Text Domain:       ai-voice
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Define constants
define('AI_VOICE_VERSION', '2.0.0');
define('AI_VOICE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_VOICE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Main plugin class
class AIVoice_Plugin {
    
    private $settings;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    public function init() {
        $this->settings = get_option('ai_voice_settings', array());
        
        // Admin
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
            add_action('add_meta_boxes', array($this, 'add_meta_box'));
            add_action('save_post', array($this, 'save_meta_box'));
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        }
        
        // Frontend
        if (!is_admin()) {
            add_filter('the_content', array($this, 'maybe_display_player'), 99);
            add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
        }
        
        // AJAX
        add_action('wp_ajax_ai_voice_generate', array($this, 'handle_ajax'));
        add_action('wp_ajax_nopriv_ai_voice_generate', array($this, 'handle_ajax'));
    }
    
    public function activate() {
        $default_settings = array(
            'api_key' => '',
            'enable_globally' => 0,
            'voice' => 'en-US-Studio-O',
            'theme' => 'light'
        );
        
        if (!get_option('ai_voice_settings')) {
            add_option('ai_voice_settings', $default_settings);
        }
    }
    
    public function add_admin_menu() {
        add_options_page(
            'AI Voice Settings',
            'AI Voice',
            'manage_options',
            'ai-voice-settings',
            array($this, 'settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('ai_voice_group', 'ai_voice_settings');
    }
    
    public function settings_page() {
        $settings = get_option('ai_voice_settings', array());
        ?>
        <div class="wrap">
            <h1>AI Voice Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('ai_voice_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Google Cloud TTS API Key</th>
                        <td>
                            <input type="text" name="ai_voice_settings[api_key]" 
                                   value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>" 
                                   class="regular-text" />
                            <p class="description">Get your API key from Google Cloud Console</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Enable Globally</th>
                        <td>
                            <input type="checkbox" name="ai_voice_settings[enable_globally]" 
                                   value="1" <?php checked($settings['enable_globally'] ?? 0, 1); ?> />
                            <label>Enable audio player on all posts by default</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Default Voice</th>
                        <td>
                            <select name="ai_voice_settings[voice]">
                                <?php echo $this->get_voice_options($settings['voice'] ?? 'en-US-Studio-O'); ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Player Theme</th>
                        <td>
                            <select name="ai_voice_settings[theme]">
                                <option value="light" <?php selected($settings['theme'] ?? 'light', 'light'); ?>>Light</option>
                                <option value="dark" <?php selected($settings['theme'] ?? 'light', 'dark'); ?>>Dark</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    public function get_voice_options($selected = '') {
        $voices = array(
            // English US - Studio (Premium)
            'en-US-Studio-O' => 'English (US) - Studio Female',
            'en-US-Studio-M' => 'English (US) - Studio Male',
            
            // English US - Neural2 (High Quality)
            'en-US-Neural2-A' => 'English (US) - Neural2 Male A',
            'en-US-Neural2-C' => 'English (US) - Neural2 Female C',
            'en-US-Neural2-D' => 'English (US) - Neural2 Male D',
            'en-US-Neural2-F' => 'English (US) - Neural2 Female F',
            'en-US-Neural2-G' => 'English (US) - Neural2 Female G',
            'en-US-Neural2-H' => 'English (US) - Neural2 Female H',
            'en-US-Neural2-I' => 'English (US) - Neural2 Male I',
            'en-US-Neural2-J' => 'English (US) - Neural2 Male J',
            
            // English US - Journey (Premium)
            'en-US-Journey-D' => 'English (US) - Journey Male',
            'en-US-Journey-F' => 'English (US) - Journey Female',
            
            // English UK - Studio & Neural2
            'en-GB-Studio-B' => 'English (UK) - Studio Male',
            'en-GB-Studio-C' => 'English (UK) - Studio Female',
            'en-GB-Neural2-A' => 'English (UK) - Neural2 Male A',
            'en-GB-Neural2-B' => 'English (UK) - Neural2 Male B',
            'en-GB-Neural2-C' => 'English (UK) - Neural2 Female C',
            'en-GB-Neural2-D' => 'English (UK) - Neural2 Female D',
            'en-GB-Neural2-F' => 'English (UK) - Neural2 Female F',
            
            // English Australia
            'en-AU-Neural2-A' => 'English (Australia) - Neural2 Male A',
            'en-AU-Neural2-B' => 'English (Australia) - Neural2 Male B',
            'en-AU-Neural2-C' => 'English (Australia) - Neural2 Female C',
            'en-AU-Neural2-D' => 'English (Australia) - Neural2 Female D',
            
            // English India
            'en-IN-Neural2-A' => 'English (India) - Neural2 Male A',
            'en-IN-Neural2-B' => 'English (India) - Neural2 Male B',
            'en-IN-Neural2-C' => 'English (India) - Neural2 Female C',
            'en-IN-Neural2-D' => 'English (India) - Neural2 Female D',
            
            // French
            'fr-FR-Neural2-A' => 'French - Neural2 Female A',
            'fr-FR-Neural2-B' => 'French - Neural2 Male B',
            'fr-FR-Neural2-C' => 'French - Neural2 Female C',
            'fr-FR-Neural2-D' => 'French - Neural2 Male D',
            
            // German
            'de-DE-Neural2-A' => 'German - Neural2 Female A',
            'de-DE-Neural2-B' => 'German - Neural2 Male B',
            'de-DE-Neural2-C' => 'German - Neural2 Female C',
            'de-DE-Neural2-D' => 'German - Neural2 Male D',
            
            // Spanish
            'es-ES-Neural2-A' => 'Spanish - Neural2 Female A',
            'es-ES-Neural2-B' => 'Spanish - Neural2 Male B',
            'es-ES-Neural2-C' => 'Spanish - Neural2 Female C',
            'es-ES-Neural2-D' => 'Spanish - Neural2 Male D',
            
            // Italian
            'it-IT-Neural2-A' => 'Italian - Neural2 Female A',
            'it-IT-Neural2-C' => 'Italian - Neural2 Male C',
            
            // Portuguese
            'pt-BR-Neural2-A' => 'Portuguese (Brazil) - Neural2 Female A',
            'pt-BR-Neural2-B' => 'Portuguese (Brazil) - Neural2 Male B',
            'pt-BR-Neural2-C' => 'Portuguese (Brazil) - Neural2 Female C',
        );
        
        $options = '';
        foreach ($voices as $value => $label) {
            $options .= '<option value="' . esc_attr($value) . '"' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
        }
        
        return $options;
    }
    
    public function add_meta_box() {
        add_meta_box(
            'ai-voice-settings',
            'AI Voice Settings',
            array($this, 'meta_box_html'),
            array('post', 'page'),
            'side',
            'high'
        );
    }
    
    public function meta_box_html($post) {
        wp_nonce_field('ai_voice_meta', 'ai_voice_nonce');
        
        $status = get_post_meta($post->ID, '_ai_voice_status', true);
        $voice = get_post_meta($post->ID, '_ai_voice_voice', true);
        
        ?>
        <p>
            <label><strong>Player Status:</strong></label><br>
            <select name="ai_voice_status" style="width:100%">
                <option value="default" <?php selected($status, 'default'); ?>>Use Global Setting</option>
                <option value="enabled" <?php selected($status, 'enabled'); ?>>Enabled</option>
                <option value="disabled" <?php selected($status, 'disabled'); ?>>Disabled</option>
            </select>
        </p>
        <p>
            <label><strong>Voice:</strong></label><br>
            <select name="ai_voice_voice" style="width:100%">
                <option value="default">Use Global Setting</option>
                <?php echo $this->get_voice_options($voice); ?>
            </select>
        </p>
        <?php
    }
    
    public function save_meta_box($post_id) {
        if (!isset($_POST['ai_voice_nonce']) || !wp_verify_nonce($_POST['ai_voice_nonce'], 'ai_voice_meta')) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (isset($_POST['ai_voice_status'])) {
            update_post_meta($post_id, '_ai_voice_status', sanitize_text_field($_POST['ai_voice_status']));
        }
        
        if (isset($_POST['ai_voice_voice'])) {
            update_post_meta($post_id, '_ai_voice_voice', sanitize_text_field($_POST['ai_voice_voice']));
        }
        
        // Clear cache
        delete_post_meta($post_id, '_ai_voice_cache');
    }
    
    public function admin_scripts($hook) {
        if ($hook === 'settings_page_ai-voice-settings' || in_array($hook, array('post.php', 'post-new.php'))) {
            wp_enqueue_script('jquery');
        }
    }
    
    public function frontend_scripts() {
        wp_register_style('ai-voice-style', AI_VOICE_PLUGIN_URL . 'assets/player.css', array(), AI_VOICE_VERSION);
        wp_register_script('ai-voice-script', AI_VOICE_PLUGIN_URL . 'assets/player.js', array('jquery'), AI_VOICE_VERSION, true);
    }
    
    public function maybe_display_player($content) {
        if (!is_singular(array('post', 'page')) || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        
        $post_id = get_the_ID();
        $status = get_post_meta($post_id, '_ai_voice_status', true);
        $global_enabled = $this->settings['enable_globally'] ?? 0;
        
        // Check if should display
        if ($status === 'disabled' || ($status !== 'enabled' && !$global_enabled)) {
            return $content;
        }
        
        // Check if API key exists
        if (empty($this->settings['api_key'])) {
            return $content;
        }
        
        // Enqueue assets
        wp_enqueue_style('ai-voice-style');
        wp_enqueue_script('ai-voice-script');
        
        // Localize script
        wp_localize_script('ai-voice-script', 'aiVoiceData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_voice_nonce'),
            'post_id' => $post_id,
            'title' => get_the_title($post_id),
            'theme' => $this->settings['theme'] ?? 'light'
        ));
        
        // Generate player HTML
        ob_start();
        ?>
        <div id="ai-voice-player" data-theme="<?php echo esc_attr($this->settings['theme'] ?? 'light'); ?>">
            <div class="ai-voice-container">
                <button id="ai-voice-play-btn" class="play-button">
                    <span class="loader" style="display:none;"></span>
                    <span class="play-icon">▶</span>
                    <span class="pause-icon" style="display:none;">⏸</span>
                </button>
                <div class="ai-voice-info">
                    <div class="title"><?php echo esc_html(get_the_title($post_id)); ?></div>
                    <div class="controls">
                        <span class="current-time">0:00</span>
                        <input type="range" class="progress-bar" value="0" min="0" max="100">
                        <span class="total-time">0:00</span>
                        <button class="speed-btn">1.0x</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $player_html = ob_get_clean();
        
        return $player_html . $content;
    }
    
    public function handle_ajax() {
        check_ajax_referer('ai_voice_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }
        
        // Get text content
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Post not found');
        }
        
        $text = wp_strip_all_tags($post->post_content);
        $text = trim(preg_replace('/\s+/', ' ', $text));
        
        if (empty($text)) {
            wp_send_json_error('No content found');
        }
        
        // Check cache
        $cache_key = '_ai_voice_cache_' . md5($text);
        $cached_url = get_post_meta($post_id, $cache_key, true);
        
        if ($cached_url && file_exists(str_replace(wp_upload_dir()['url'], wp_upload_dir()['path'], $cached_url))) {
            wp_send_json_success(array('audio_url' => $cached_url));
        }
        
        // Generate audio
        $audio_url = $this->generate_audio($post_id, $text);
        
        if (is_wp_error($audio_url)) {
            wp_send_json_error($audio_url->get_error_message());
        }
        
        // Cache result
        update_post_meta($post_id, $cache_key, $audio_url);
        
        wp_send_json_success(array('audio_url' => $audio_url));
    }
    
    private function generate_audio($post_id, $text) {
        $api_key = $this->settings['api_key'] ?? '';
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'Google Cloud TTS API key not configured');
        }
        
        // Get voice setting
        $voice = get_post_meta($post_id, '_ai_voice_voice', true);
        if ($voice === 'default' || empty($voice)) {
            $voice = $this->settings['voice'] ?? 'en-US-Studio-O';
        }
        
        // Determine language code from voice
        $language_code = substr($voice, 0, 5); // e.g., "en-US"
        
        // Prepare API request
        $api_url = 'https://texttospeech.googleapis.com/v1/text:synthesize?key=' . $api_key;
        
        $body = array(
            'input' => array('text' => $text),
            'voice' => array(
                'languageCode' => $language_code,
                'name' => $voice
            ),
            'audioConfig' => array('audioEncoding' => 'MP3')
        );
        
        $args = array(
            'body' => json_encode($body),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 60
        );
        
        $response = wp_remote_post($api_url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        if ($response_code !== 200) {
            $error_message = $response_data['error']['message'] ?? 'Google TTS API error';
            return new WP_Error('api_error', $error_message);
        }
        
        if (!isset($response_data['audioContent'])) {
            return new WP_Error('no_audio', 'No audio content in response');
        }
        
        // Save audio file
        $upload_dir = wp_upload_dir();
        $filename = 'ai-voice-' . $post_id . '-' . time() . '.mp3';
        $filepath = $upload_dir['path'] . '/' . $filename;
        $fileurl = $upload_dir['url'] . '/' . $filename;
        
        $audio_data = base64_decode($response_data['audioContent']);
        
        if (file_put_contents($filepath, $audio_data) === false) {
            return new WP_Error('save_failed', 'Failed to save audio file');
        }
        
        return $fileurl;
    }
}

// Initialize plugin
new AIVoice_Plugin();