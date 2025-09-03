<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class AIVoice_Public {

    private $settings;

    public function __construct() {
        $this->settings = get_option('ai_voice_settings');
        add_filter( 'the_content', [ $this, 'maybe_display_player' ], 99 ); // Set priority to run later
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_ai_voice_generate_audio', [ $this, 'handle_generate_audio_ajax' ] );
        add_action( 'wp_ajax_nopriv_ai_voice_generate_audio', [ $this, 'handle_generate_audio_ajax' ] );
    }

    public function enqueue_assets() {
        wp_register_style( 'ai-voice-player-css', AI_VOICE_PLUGIN_URL . 'public/assets/css/player.css', [], AI_VOICE_VERSION );
        wp_register_script( 'ai-voice-player-js', AI_VOICE_PLUGIN_URL . 'public/assets/js/player.js', ['jquery'], AI_VOICE_VERSION, true );
    }

    public function maybe_display_player( $content ) {
        if ( ! is_singular( ['post', 'page'] ) || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        $post_id = get_the_ID();
        $status = get_post_meta($post_id, '_ai_voice_status', true);
        
        $is_enabled_globally = isset($this->settings['enable_globally']) && $this->settings['enable_globally'] == '1';

        if ($status === 'disabled' || ($status !== 'enabled' && !$is_enabled_globally)) {
            return $content;
        }
        
        $this->prepare_frontend_scripts($post_id);
        
        ob_start();
        include( AI_VOICE_PLUGIN_DIR . 'public/partials/player-template.php' );
        $player_html = ob_get_clean();

        return $player_html . $content;
    }

    public function handle_generate_audio_ajax() {
        check_ajax_referer( 'ai_voice_nonce', 'nonce' );

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error( [ 'message' => 'Invalid Post ID.' ] );
        }
        
        $text_to_speak = isset($_POST['text_to_speak']) ? wp_kses_post(stripslashes($_POST['text_to_speak'])) : '';
        if (empty(trim($text_to_speak))) {
            wp_send_json_error( [ 'message' => 'No visible text content was found to read.' ] );
        }

        $generation_result = $this->generate_audio($post_id, $text_to_speak);

        if (is_wp_error($generation_result)) {
            wp_send_json_error( [ 'message' => $generation_result->get_error_message() ] );
        } else {
            wp_send_json_success( [ 'audioUrl' => $generation_result ] );
        }
    }
    
    private function generate_audio($post_id, $text_to_speak) {
        $content_hash = md5($text_to_speak);
        $cached_audio_url = get_post_meta($post_id, '_ai_voice_audio_url_' . $content_hash, true);

        if (!empty($cached_audio_url)) {
            return $cached_audio_url;
        }

        $post = get_post($post_id);

        $ai_service = get_post_meta($post_id, '_ai_voice_ai_service', true) ?: ($this->settings['default_ai'] ?? 'google');
        if ($ai_service === 'default') $ai_service = $this->settings['default_ai'] ?? 'google';

        $api_key = ($ai_service === 'google') ? ($this->settings['google_api_key'] ?? '') : ($this->settings['openai_api_key'] ?? '');
        if (empty($api_key)) return new WP_Error('no_api_key', 'API key for ' . ucfirst($ai_service) . ' is not configured.');
        
        $response_body = null;
        $args = ['timeout' => 30];

        if ($ai_service === 'google') {
            $voice_id = get_post_meta($post_id, '_ai_voice_google_voice', true) ?: ($this->settings['google_voice'] ?? 'en-US-Studio-O');
            if ($voice_id === 'default') $voice_id = $this->settings['google_voice'] ?? 'en-US-Studio-O';

            $api_url = 'https://texttospeech.googleapis.com/v1/text:synthesize?key=' . $api_key;
            $body = ['input' => ['text' => substr($text_to_speak, 0, 5000)], 'voice' => ['languageCode' => substr($voice_id, 0, 5), 'name' => $voice_id], 'audioConfig' => ['audioEncoding' => 'MP3']];
            $args['body'] = json_encode($body);
            $args['headers'] = ['Content-Type' => 'application/json'];
            $response = wp_remote_post($api_url, $args);

            if (is_wp_error($response)) return new WP_Error('api_connection_error', 'Google API Error: ' . $response->get_error_message());
            $response_data = json_decode(wp_remote_retrieve_body($response), true);
            if (wp_remote_retrieve_response_code($response) !== 200 || !isset($response_data['audioContent'])) return new WP_Error('google_api_error', 'Google API Error: ' . ($response_data['error']['message'] ?? 'Unknown error.'));
            $response_body = base64_decode($response_data['audioContent']);

        } else { // OpenAI
            $voice_id = get_post_meta($post_id, '_ai_voice_openai_voice', true) ?: ($this->settings['openai_voice'] ?? 'nova');
            if ($voice_id === 'default') $voice_id = $this->settings['openai_voice'] ?? 'nova';

            $api_url = 'https://api.openai.com/v1/audio/speech';
            $body = ['model' => 'tts-1', 'input' => substr($text_to_speak, 0, 4096), 'voice' => $voice_id];
            $args['body'] = json_encode($body);
            $args['headers'] = ['Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'];
            $response = wp_remote_post($api_url, $args);

            if (is_wp_error($response)) return new WP_Error('api_connection_error', 'OpenAI API Error: ' . $response->get_error_message());
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) return new WP_Error('openai_api_error', 'OpenAI API Error: ' . (json_decode(wp_remote_retrieve_body($response), true)['error']['message'] ?? 'Unknown error.'));
            $response_body = wp_remote_retrieve_body($response);
        }

        if (empty($response_body)) return new WP_Error('api_empty_response', 'API returned empty audio.');

        $upload = wp_upload_bits('ai-voice-' . $post_id . '-' . time() . '.mp3', null, $response_body);
        if (!empty($upload['error'])) return new WP_Error('upload_error', 'WordPress upload error: ' . $upload['error']);

        $attachment = ['guid' => $upload['url'], 'post_mime_type' => 'audio/mpeg', 'post_title' => 'AI Voice for ' . $post->post_title, 'post_content' => '', 'post_status' => 'inherit'];
        $attach_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
        
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        update_post_meta($post_id, '_ai_voice_audio_url_' . $content_hash, $upload['url']);

        return $upload['url'];
    }

    private function prepare_frontend_scripts($post_id) {
        wp_enqueue_style('ai-voice-player-css');
        wp_enqueue_script('ai-voice-player-js');

        $theme = get_post_meta($post_id, '_ai_voice_theme', true) ?: ($this->settings['theme'] ?? 'light');
        if ($theme === 'default') $theme = $this->settings['theme'] ?? 'light';

        $ai_service = get_post_meta($post_id, '_ai_voice_ai_service', true) ?: ($this->settings['default_ai'] ?? 'google');
        if ($ai_service === 'default') $ai_service = $this->settings['default_ai'] ?? 'google';
        
        wp_localize_script('ai-voice-player-js', 'aiVoiceData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_voice_nonce'),
            'post_id' => $post_id,
            'title' => get_the_title($post_id),
            'theme' => esc_attr($theme),
            'aiService' => esc_attr($ai_service),
        ]);
    }
}

