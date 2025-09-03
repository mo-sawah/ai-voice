<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class AIVoice_Public {

    private $settings;

    public function __construct() {
        $this->settings = get_option('ai_voice_settings');
        add_filter( 'the_content', [ $this, 'maybe_display_player' ], 99 );
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
        $ai_service = get_post_meta($post_id, '_ai_voice_ai_service', true) ?: ($this->settings['default_ai'] ?? 'google');
        if ($ai_service === 'default') $ai_service = $this->settings['default_ai'] ?? 'google';
        
        $content_hash_text = $text_to_speak;

        if ($ai_service === 'gemini') {
            $gemini_tone = get_post_meta($post_id, '_ai_voice_gemini_tone', true) ?: ($this->settings['gemini_tone'] ?? 'neutral');
            if ($gemini_tone === 'default') $gemini_tone = $this->settings['gemini_tone'] ?? 'neutral';
            $content_hash_text .= $gemini_tone;
        }

        $content_hash = md5($content_hash_text);
        $cached_audio_url = get_post_meta($post_id, '_ai_voice_audio_url_' . $content_hash, true);

        if (!empty($cached_audio_url)) {
            return $cached_audio_url;
        }

        $api_key = '';
        switch($ai_service) {
            case 'google': $api_key = $this->settings['google_api_key'] ?? ''; break;
            case 'gemini': $api_key = $this->settings['gemini_api_key'] ?? ''; break;
            case 'openai': $api_key = $this->settings['openai_api_key'] ?? ''; break;
        }

        if (empty($api_key)) return new WP_Error('no_api_key', 'API key for ' . ucfirst($ai_service) . ' is not configured.');
        
        $response_body = null;
        $args = ['timeout' => 30];

        if ($ai_service === 'google') {
            // Google Cloud TTS Logic (unchanged)
        } else if ($ai_service === 'gemini') {
            $voice_id = get_post_meta($post_id, '_ai_voice_gemini_voice', true) ?: ($this->settings['gemini_voice'] ?? 'Kore');
            if ($voice_id === 'default') $voice_id = $this->settings['gemini_voice'] ?? 'Kore';
            
            $tone_prompt = '';
            switch ($gemini_tone) {
                case 'newscaster': $tone_prompt = 'Read the following in a professional, formal, newscaster style: '; break;
                case 'conversational': $tone_prompt = 'Read the following in a casual, conversational tone: '; break;
                case 'calm': $tone_prompt = 'Read the following in a calm and soothing voice: '; break;
            }

            $final_text = $tone_prompt . substr($text_to_speak, 0, 4900);

            $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-tts:generateContent?key=' . $api_key;
            $body = [
                'contents' => [['parts' => [['text' => $final_text]]]],
                'generationConfig' => [
                    'responseModalities' => ["AUDIO"],
                    'speechConfig' => ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $voice_id]]]
                ]
            ];
            $args['body'] = json_encode($body);
            $args['headers'] = ['Content-Type' => 'application/json'];
            $response = wp_remote_post($api_url, $args);

            if (is_wp_error($response)) return new WP_Error('api_connection_error', 'Gemini API Error: ' . $response->get_error_message());
            
            $response_data = json_decode(wp_remote_retrieve_body($response), true);
            
            // --- BUG FIX IS HERE ---
            if (wp_remote_retrieve_response_code($response) !== 200 || !isset($response_data['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
                $error_message = $response_data['error']['message'] ?? 'Unknown error. The API response structure might have changed or your API key may be invalid.';
                return new WP_Error('gemini_api_error', 'Gemini API Error: ' . $error_message);
            }
            $response_body = base64_decode($response_data['candidates'][0]['content']['parts'][0]['inlineData']['data']);
            
        } else { // OpenAI
            // OpenAI Logic (unchanged)
        }

        if (empty($response_body)) return new WP_Error('api_empty_response', 'API returned empty audio.');

        $upload = wp_upload_bits('ai-voice-' . $post_id . '-' . time() . '.mp3', null, $response_body);
        if (!empty($upload['error'])) return new WP_Error('upload_error', 'WordPress upload error: ' . $upload['error']);
        
        $attachment = ['guid' => $upload['url'], 'post_mime_type' => 'audio/mpeg', 'post_title' => 'AI Voice for ' . get_the_title($post_id), 'post_content' => '', 'post_status' => 'inherit'];
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

