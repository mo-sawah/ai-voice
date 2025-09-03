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
        if ($status === 'disabled') {
            return $content;
        }
        if ($status !== 'enabled' && !$is_enabled_globally) {
            return $content;
        }

        // At this point, we attempt to show the player.
        $player_output = $this->get_player_html($post_id);

        if (is_wp_error($player_output)) {
            // If there's an error, show it ONLY to admins.
            if (current_user_can('manage_options')) {
                $error_message = $player_output->get_error_message();
                $error_html = '<div style="border: 2px solid #d63638; background-color: #f8d7da; color: #721c24; padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.5rem;"><strong>AI Voice Plugin Error:</strong> ' . esc_html($error_message) . '</div>';
                return $error_html . $content;
            }
            // Fail silently for regular users.
            return $content;
        }

        return $player_output . $content;
    }

    private function get_player_html($post_id) {
        $last_content_hash = get_post_meta($post_id, '_ai_voice_content_hash', true);
        $current_content_hash = md5(get_the_content($post_id));
        $audio_url = get_post_meta($post_id, '_ai_voice_audio_url', true);
        
        // Regenerate if content has changed, or if audio_url doesn't exist.
        if ( empty($audio_url) || $last_content_hash !== $current_content_hash ) {
            $generation_result = $this->generate_audio($post_id);
            if (is_wp_error($generation_result)) {
                return $generation_result; // Return the WP_Error object to be handled by the caller.
            }
            $audio_url = $generation_result;
            update_post_meta($post_id, '_ai_voice_audio_url', $audio_url);
            update_post_meta($post_id, '_ai_voice_content_hash', $current_content_hash);
        }

        // We have a valid audio URL, now prepare and enqueue assets
        $this->prepare_frontend_scripts($post_id, $audio_url);

        ob_start();
        include( AI_VOICE_PLUGIN_DIR . 'public/partials/player-template.php' );
        return ob_get_clean();
    }
    
    private function generate_audio($post_id) {
        $post = get_post($post_id);
        $text_to_speak = wp_strip_all_tags(strip_shortcodes($post->post_content));
        
        if (empty(trim($text_to_speak))) {
            return new WP_Error('empty_content', 'The post content is empty or contains only shortcodes/HTML.');
        }

        // Get settings, preferring post meta over global settings
        $ai_service = get_post_meta($post_id, '_ai_voice_ai_service', true);
        if (empty($ai_service) || $ai_service === 'default') {
            $ai_service = $this->settings['default_ai'] ?? 'google';
        }
        
        $api_key = ($ai_service === 'google') ? ($this->settings['google_api_key'] ?? '') : ($this->settings['openai_api_key'] ?? '');
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'The API key for ' . ucfirst($ai_service) . ' is not configured in the AI Voice settings page.');
        }
        
        $response_body = null;

        if ($ai_service === 'google') {
            $voice_id = get_post_meta($post_id, '_ai_voice_google_voice', true);
            if (empty($voice_id) || $voice_id === 'default') $voice_id = $this->settings['google_voice'] ?? 'en-US-Wavenet-F';
            
            $api_url = 'https://texttospeech.googleapis.com/v1/text:synthesize?key=' . $api_key;
            $body = ['input' => ['text' => substr($text_to_speak, 0, 5000)], 'voice' => ['languageCode' => substr($voice_id, 0, 5), 'name' => $voice_id], 'audioConfig' => ['audioEncoding' => 'MP3']];
            $response = wp_remote_post($api_url, [
                'body' => json_encode($body), 
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 30 // Increased timeout from 5 to 30 seconds
            ]);
            
            if (is_wp_error($response)) return new WP_Error('api_connection_error', 'Could not connect to Google API: ' . $response->get_error_message());
            
            $response_data = json_decode(wp_remote_retrieve_body($response), true);
            if (wp_remote_retrieve_response_code($response) !== 200 || !isset($response_data['audioContent'])) {
                $error_message = $response_data['error']['message'] ?? 'An unknown error occurred with the Google API.';
                return new WP_Error('google_api_error', 'Google API Error: ' . $error_message);
            }
            $response_body = base64_decode($response_data['audioContent']);

        } else { // OpenAI
            $voice_id = get_post_meta($post_id, '_ai_voice_openai_voice', true);
            if (empty($voice_id) || $voice_id === 'default') $voice_id = $this->settings['openai_voice'] ?? 'alloy';
            
            $api_url = 'https://api.openai.com/v1/audio/speech';
            $body = ['model' => 'tts-1', 'input' => substr($text_to_speak, 0, 4096), 'voice' => $voice_id];
            $response = wp_remote_post($api_url, [
                'body' => json_encode($body), 
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key, 
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 30 // Increased timeout from 5 to 30 seconds
            ]);
            
            if (is_wp_error($response)) return new WP_Error('api_connection_error', 'Could not connect to OpenAI API: ' . $response->get_error_message());
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $error_data = json_decode(wp_remote_retrieve_body($response), true);
                $error_message = $error_data['error']['message'] ?? 'An unknown error occurred with the OpenAI API.';
                return new WP_Error('openai_api_error', 'OpenAI API Error: ' . $error_message);
            }
            $response_body = wp_remote_retrieve_body($response);
        }

        if (empty($response_body)) {
            return new WP_Error('api_empty_response', 'The API returned an empty audio response. The content may be too short or contain unsupported characters.');
        }

        $upload = wp_upload_bits('ai-voice-' . $post_id . '-' . time() . '.mp3', null, $response_body);
        if (!empty($upload['error'])) {
            return new WP_Error('upload_error', 'WordPress upload error: ' . $upload['error']);
        }

        $attachment = ['guid' => $upload['url'], 'post_mime_type' => 'audio/mpeg', 'post_title' => 'AI Voice for ' . $post->post_title, 'post_content' => '', 'post_status' => 'inherit'];
        $attach_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return $upload['url'];
    }

    private function prepare_frontend_scripts($post_id, $audio_url) {
        wp_enqueue_style('ai-voice-player-css');
        wp_enqueue_script('ai-voice-player-js');

        $theme = get_post_meta($post_id, '_ai_voice_theme', true);
        if (empty($theme) || $theme === 'default') $theme = $this->settings['theme'] ?? 'light';
        
        $ai_service = get_post_meta($post_id, '_ai_voice_ai_service', true);
        if (empty($ai_service) || $ai_service === 'default') $ai_service = $this->settings['default_ai'] ?? 'google';
        
        wp_localize_script('ai-voice-player-js', 'aiVoiceData', [
            'audioUrl' => esc_url($audio_url),
            'title' => get_the_title($post_id),
            'theme' => esc_attr($theme),
            'aiService' => esc_attr($ai_service),
        ]);
        
        // Dynamic CSS for colors - simplified for brevity in this example
        $css = ":root {
            --bg-light: " . esc_attr($this->settings['bg_color_light'] ?? '#ffffff') . ";
            --bg-secondary-light: " . esc_attr(self::adjust_brightness($this->settings['bg_color_light'] ?? '#ffffff', -10)) . ";
            --text-primary-light: " . esc_attr($this->settings['text_color_light'] ?? '#0f172a') . ";
            --text-secondary-light: " . esc_attr(self::adjust_brightness($this->settings['text_color_light'] ?? '#0f172a', 100)) . ";
            --accent-light: " . esc_attr($this->settings['accent_color_light'] ?? '#3b82f6') . ";
            --border-light: " . esc_attr(self::adjust_brightness($this->settings['bg_color_light'] ?? '#ffffff', -20)) . ";

            --bg-dark: " . esc_attr($this->settings['bg_color_dark'] ?? '#1e2b3b') . ";
            --bg-secondary-dark: " . esc_attr(self::adjust_brightness($this->settings['bg_color_dark'] ?? '#1e2b3b', 20)) . ";
            --text-primary-dark: " . esc_attr($this->settings['text_color_dark'] ?? '#f1f5f9') . ";
            --text-secondary-dark: " . esc_attr(self::adjust_brightness($this->settings['text_color_dark'] ?? '#f1f5f9', -80)) . ";
            --accent-dark: " . esc_attr($this->settings['accent_color_dark'] ?? '#60a5fa') . ";
            --border-dark: " . esc_attr(self::adjust_brightness($this->settings['bg_color_dark'] ?? '#1e2b3b', 30)) . ";
        }";
        wp_add_inline_style('ai-voice-player-css', $css);
    }

    // Helper function to calculate secondary colors
    public static function adjust_brightness($hex, $steps) {
        $steps = max(-255, min(255, $steps));
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $hex = str_repeat(substr($hex,0,1), 2).str_repeat(substr($hex,1,1), 2).str_repeat(substr($hex,2,1), 2);
        }
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
        $r = max(0,min(255,$r + $steps));
        $g = max(0,min(255,$g + $steps));
        $b = max(0,min(255,$b + $steps));
        return '#'.str_pad(dechex($r), 2, '0', STR_PAD_LEFT).str_pad(dechex($g), 2, '0', STR_PAD_LEFT).str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
    }
}

