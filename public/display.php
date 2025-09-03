<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class AIVoice_Public {

    private $settings;

    public function __construct() {
        $this->settings = get_option('ai_voice_settings');
        add_filter( 'the_content', [ $this, 'maybe_display_player' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets() {
        // These will only be loaded if the player is displayed on the page
        wp_register_style( 'ai-voice-player-css', AI_VOICE_PLUGIN_URL . 'public/assets/css/player.css', [], AI_VOICE_VERSION );
        wp_register_script( 'ai-voice-player-js', AI_VOICE_PLUGIN_URL . 'public/assets/js/player.js', [], AI_VOICE_VERSION, true );
    }

    public function maybe_display_player( $content ) {
        if ( ! is_singular( ['post', 'page'] ) || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        $post_id = get_the_ID();
        $status = get_post_meta($post_id, '_ai_voice_status', true);
        
        $is_enabled_globally = isset($this->settings['enable_globally']) && $this->settings['enable_globally'] == '1';

        // Decide if we should show the player
        if ($status === 'disabled') return $content;
        if ($status !== 'enabled' && !$is_enabled_globally) return $content;

        // At this point, we are showing the player.
        $player_html = $this->get_player_html($post_id);

        if ($player_html) {
             return $player_html . $content;
        }

        return $content;
    }

    private function get_player_html($post_id) {
        // Try to get existing audio URL
        $audio_url = get_post_meta($post_id, '_ai_voice_audio_url', true);
        $last_content_hash = get_post_meta($post_id, '_ai_voice_content_hash', true);
        $current_content_hash = md5(get_the_content($post_id));

        // If content has changed, we need to regenerate
        if ($audio_url && $last_content_hash === $current_content_hash) {
            // Audio exists and is up-to-date
        } else {
            // Generate new audio
            $audio_url = $this->generate_audio($post_id);
            if (is_wp_error($audio_url) || !$audio_url) {
                // Log error for admin if needed
                return ''; 
            }
            update_post_meta($post_id, '_ai_voice_audio_url', $audio_url);
            update_post_meta($post_id, '_ai_voice_content_hash', $current_content_hash);
        }

        // We have an audio URL, now prepare and enqueue assets
        $this->prepare_frontend_scripts($post_id, $audio_url);

        ob_start();
        include(AI_VOICE_PLUGIN_DIR . 'public/partials/player-template.php');
        return ob_get_clean();
    }
    
    private function generate_audio($post_id) {
        $post = get_post($post_id);
        $text_to_speak = wp_strip_all_tags($post->post_content);
        if (empty($text_to_speak)) return new WP_Error('empty_content', 'Post content is empty.');

        // Get settings
        $ai_service = get_post_meta($post_id, '_ai_voice_ai_service', true);
        if (!$ai_service || $ai_service === 'default') {
            $ai_service = $this->settings['default_ai'] ?? 'google';
        }
        
        $api_key = ($ai_service === 'google') ? $this->settings['google_api_key'] : $this->settings['openai_api_key'];
        if (empty($api_key)) return new WP_Error('no_api_key', 'API key is not set for ' . $ai_service);
        
        $response_body = null;

        if ($ai_service === 'google') {
            $voice_id = get_post_meta($post_id, '_ai_voice_google_voice', true);
            if (!$voice_id || $voice_id === 'default') {
                $voice_id = $this->settings['google_voice'] ?? 'en-US-Wavenet-F';
            }
            $api_url = 'https://texttospeech.googleapis.com/v1/text:synthesize?key=' . $api_key;
            $body = [
                'input' => ['text' => $text_to_speak],
                'voice' => ['languageCode' => substr($voice_id, 0, 5), 'name' => $voice_id],
                'audioConfig' => ['audioEncoding' => 'MP3']
            ];
            $response = wp_remote_post($api_url, ['body' => json_encode($body), 'headers' => ['Content-Type' => 'application/json']]);
            $response_data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($response_data['audioContent'])) {
                $response_body = base64_decode($response_data['audioContent']);
            }

        } else { // OpenAI
            $voice_id = get_post_meta($post_id, '_ai_voice_openai_voice', true);
            if (!$voice_id || $voice_id === 'default') {
                $voice_id = $this->settings['openai_voice'] ?? 'alloy';
            }
            $api_url = 'https://api.openai.com/v1/audio/speech';
            $body = [
                'model' => 'tts-1',
                'input' => $text_to_speak,
                'voice' => $voice_id,
            ];
            $response = wp_remote_post($api_url, [
                'body' => json_encode($body),
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ]
            ]);
            $response_body = wp_remote_retrieve_body($response);
        }

        if (is_wp_error($response) || empty($response_body)) {
            return new WP_Error('api_error', 'Failed to generate audio.');
        }

        // Save file to media library
        $upload = wp_upload_bits('ai-voice-' . $post_id . '.mp3', null, $response_body);
        if ($upload['error']) {
            return new WP_Error('upload_error', $upload['error']);
        }

        $attachment = [
            'guid'           => $upload['url'],
            'post_mime_type' => 'audio/mpeg',
            'post_title'     => 'AI Voice for ' . $post->post_title,
            'post_content'   => '',
            'post_status'    => 'inherit'
        ];
        $attach_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return $upload['url'];
    }

    private function prepare_frontend_scripts($post_id, $audio_url) {
        wp_enqueue_style('ai-voice-player-css');
        wp_enqueue_script('ai-voice-player-js');

        // Get all settings (global + post override)
        $theme = get_post_meta($post_id, '_ai_voice_theme', true);
        if (!$theme || $theme === 'default') $theme = $this->settings['theme'] ?? 'light';
        
        $ai_service = get_post_meta($post_id, '_ai_voice_ai_service', true);
        if (!$ai_service || $ai_service === 'default') $ai_service = $this->settings['default_ai'] ?? 'google';
        
        $player_data = [
            'audioUrl' => $audio_url,
            'title' => get_the_title($post_id),
            'theme' => $theme,
            'aiService' => $ai_service,
            // Add more data as needed
        ];
        wp_localize_script('ai-voice-player-js', 'aiVoiceData', $player_data);
        
        // Dynamic CSS for colors
        $css = ":root {
            --bg-light: " . ($this->settings['bg_color_light'] ?? '#ffffff') . ";
            --text-primary-light: " . ($this->settings['text_color_light'] ?? '#0f172a') . ";
            --accent-light: " . ($this->settings['accent_color_light'] ?? '#3b82f6') . ";
            --bg-dark: " . ($this->settings['bg_color_dark'] ?? '#1e293b') . ";
            --text-primary-dark: " . ($this->settings['text_color_dark'] ?? '#f1f5f9') . ";
            --accent-dark: " . ($this->settings['accent_color_dark'] ?? '#60a5fa') . ";
            /* Add other colors here */
        }";
        wp_add_inline_style('ai-voice-player-css', $css);
    }
}

// Partial template for the player
function ai_voice_player_template_include() {
    // This is a bit of a hack to define the partial in the same file.
    // In a larger plugin, this would be its own file.
    $template_path = AI_VOICE_PLUGIN_DIR . 'public/partials/player-template.php';
    if (file_exists($template_path)) {
        ob_start();
        include $template_path;
        return ob_get_clean();
    }
    return '';
}
