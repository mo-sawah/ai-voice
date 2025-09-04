<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class AIVoice_Public {

    private $settings;
    private $max_chunk_size = 800; // Reduced from default chunking
    private $max_total_chars = 8000; // Limit total text length

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

        // Limit text length to prevent timeouts
        if (strlen($text_to_speak) > $this->max_total_chars) {
            $text_to_speak = substr($text_to_speak, 0, $this->max_total_chars) . '...';
        }

        // Clean up text for better processing
        $text_to_speak = $this->clean_text_for_tts($text_to_speak);

        $generation_method = get_post_meta($post_id, '_ai_voice_generation_method', true);
        if (empty($generation_method) || $generation_method === 'default') {
            $generation_method = $this->settings['generation_method'] ?? 'chunked';
        }

        // Force chunked method for better reliability
        $generation_result = $this->generate_chunked_audio($post_id, $text_to_speak);

        if (is_wp_error($generation_result)) {
            wp_send_json_error( [ 'message' => $generation_result->get_error_message() ] );
        } else {
            wp_send_json_success( [ 'audioUrl' => $generation_result ] );
        }
    }

    private function clean_text_for_tts($text) {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove URLs
        $text = preg_replace('/https?:\/\/[^\s]+/', '', $text);
        
        // Remove email addresses
        $text = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '', $text);
        
        // Remove excessive punctuation
        $text = preg_replace('/[.]{3,}/', '...', $text);
        $text = preg_replace('/[!]{2,}/', '!', $text);
        $text = preg_replace('/[?]{2,}/', '?', $text);
        
        // Clean up special characters that might cause issues
        $text = str_replace(['•', '–', '—'], ['-', '-', '-'], $text);
        
        return trim($text);
    }

    private function generate_single_request_audio($post_id, $text_to_speak) {
        $content_hash = md5($text_to_speak);
        $cached_audio_url = get_post_meta($post_id, '_ai_voice_audio_url_' . $content_hash, true);
        if (!empty($cached_audio_url) && $this->verify_audio_file_exists($cached_audio_url)) {
            return $cached_audio_url;
        }

        $audio_file_path = $this->generate_audio_for_chunk($post_id, $text_to_speak);
        if (is_wp_error($audio_file_path)) {
            return $audio_file_path;
        }

        return $this->save_audio_file($post_id, $audio_file_path, $content_hash);
    }

    private function generate_chunked_audio($post_id, $text_to_speak) {
        $content_hash = md5($text_to_speak);
        $cached_audio_url = get_post_meta($post_id, '_ai_voice_audio_url_' . $content_hash, true);
        if (!empty($cached_audio_url) && $this->verify_audio_file_exists($cached_audio_url)) {
            return $cached_audio_url;
        }

        // Improved chunking strategy
        $text_chunks = $this->smart_chunk_text($text_to_speak);
        $audio_files = [];
        $upload_dir = wp_upload_dir();

        // Process chunks with retry mechanism
        foreach ($text_chunks as $index => $chunk) {
            $chunk_result = $this->generate_audio_for_chunk_with_retry($post_id, $chunk, 2);
            if (is_wp_error($chunk_result)) {
                // Clean up any successful chunks
                foreach ($audio_files as $file_path) {
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                return $chunk_result;
            }
            $audio_files[] = $chunk_result;
        }
        
        if (empty($audio_files)) {
             return new WP_Error('no_audio_generated', 'Could not generate any audio chunks.');
        }

        // Merge audio files
        return $this->merge_audio_files($post_id, $audio_files, $content_hash);
    }

    private function smart_chunk_text($text) {
        // Split by sentences first
        $sentences = preg_split('/(?<=[.?!])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $chunks = [];
        $current_chunk = '';
        
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (empty($sentence)) continue;
            
            // If adding this sentence would exceed chunk size, start a new chunk
            if (strlen($current_chunk . ' ' . $sentence) > $this->max_chunk_size) {
                if (!empty($current_chunk)) {
                    $chunks[] = trim($current_chunk);
                    $current_chunk = $sentence;
                } else {
                    // Single sentence is too long, split by words
                    $word_chunks = $this->split_by_words($sentence);
                    $chunks = array_merge($chunks, $word_chunks);
                }
            } else {
                $current_chunk .= (empty($current_chunk) ? '' : ' ') . $sentence;
            }
        }
        
        if (!empty($current_chunk)) {
            $chunks[] = trim($current_chunk);
        }
        
        return array_filter($chunks, function($chunk) {
            return strlen(trim($chunk)) > 0;
        });
    }

    private function split_by_words($text) {
        $words = explode(' ', $text);
        $chunks = [];
        $current_chunk = '';
        
        foreach ($words as $word) {
            if (strlen($current_chunk . ' ' . $word) > $this->max_chunk_size) {
                if (!empty($current_chunk)) {
                    $chunks[] = trim($current_chunk);
                    $current_chunk = $word;
                } else {
                    // Single word is too long, just add it
                    $chunks[] = $word;
                }
            } else {
                $current_chunk .= (empty($current_chunk) ? '' : ' ') . $word;
            }
        }
        
        if (!empty($current_chunk)) {
            $chunks[] = trim($current_chunk);
        }
        
        return $chunks;
    }

    private function generate_audio_for_chunk_with_retry($post_id, $text_chunk, $max_retries = 2) {
        $last_error = null;
        
        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            $result = $this->generate_audio_for_chunk($post_id, $text_chunk);
            
            if (!is_wp_error($result)) {
                return $result;
            }
            
            $last_error = $result;
            
            // Wait before retry (exponential backoff)
            if ($attempt < $max_retries) {
                sleep($attempt);
            }
        }
        
        return $last_error;
    }

    private function verify_audio_file_exists($url) {
        if (empty($url)) return false;
        
        // Convert URL to file path
        $upload_dir = wp_upload_dir();
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
        
        return file_exists($file_path);
    }

    private function save_audio_file($post_id, $audio_file_path, $content_hash) {
        $upload_dir = wp_upload_dir();
        $final_filename = 'ai-voice-' . $post_id . '-' . substr($content_hash, 0, 8) . '.mp3';
        $final_filepath = $upload_dir['path'] . '/' . $final_filename;
        $final_fileurl = $upload_dir['url'] . '/' . $final_filename;

        if (!rename($audio_file_path, $final_filepath)) {
            return new WP_Error('file_move_failed', 'Failed to move audio file.');
        }

        // Create WordPress attachment
        $attachment = [
            'guid' => $final_fileurl,
            'post_mime_type' => 'audio/mpeg',
            'post_title' => 'AI Voice for ' . get_the_title($post_id),
            'post_content' => '',
            'post_status' => 'inherit'
        ];
        
        $attach_id = wp_insert_attachment($attachment, $final_filepath, $post_id);
        
        if (!is_wp_error($attach_id)) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attach_id, $final_filepath);
            wp_update_attachment_metadata($attach_id, $attach_data);
        }

        update_post_meta($post_id, '_ai_voice_audio_url_' . $content_hash, $final_fileurl);

        return $final_fileurl;
    }

    private function merge_audio_files($post_id, $audio_files, $content_hash) {
        $upload_dir = wp_upload_dir();
        $final_filename = 'ai-voice-' . $post_id . '-' . substr($content_hash, 0, 8) . '.mp3';
        $final_filepath = $upload_dir['path'] . '/' . $final_filename;
        $final_fileurl = $upload_dir['url'] . '/' . $final_filename;

        // Simple concatenation for MP3 files
        $final_audio_content = '';
        foreach ($audio_files as $file_path) {
            if (file_exists($file_path)) {
                $final_audio_content .= file_get_contents($file_path);
                unlink($file_path);
            }
        }
        
        if (empty($final_audio_content)) {
            return new WP_Error('merge_failed', 'No audio content to merge.');
        }

        if (file_put_contents($final_filepath, $final_audio_content) === false) {
            return new WP_Error('file_write_failed', 'Failed to write merged audio file.');
        }

        return $this->save_audio_file($post_id, $final_filepath, $content_hash);
    }
    
    private function generate_audio_for_chunk($post_id, $text_chunk) {
        $ai_service = get_post_meta($post_id, '_ai_voice_ai_service', true) ?: ($this->settings['default_ai'] ?? 'google');
        if ($ai_service === 'default') $ai_service = $this->settings['default_ai'] ?? 'google';
        
        $api_key = '';
        switch($ai_service) {
            case 'google': $api_key = $this->settings['google_api_key'] ?? ''; break;
            case 'gemini': $api_key = $this->settings['gemini_api_key'] ?? ''; break;
            case 'openai': $api_key = $this->settings['openai_api_key'] ?? ''; break;
        }

        if (empty($api_key)) return new WP_Error('no_api_key', 'API key for ' . ucfirst($ai_service) . ' is not configured.');
        
        $response_body = null;
        
        // Optimized timeout settings
        $args = [
            'timeout' => 30, // Reduced from 45 seconds
            'redirection' => 5,
            'httpversion' => '1.1',
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            'reject_unsafe_urls' => false,
            'blocking' => true,
            'sslverify' => true
        ];

        if ($ai_service === 'google') {
            $voice_id = get_post_meta($post_id, '_ai_voice_google_voice', true) ?: ($this->settings['google_voice'] ?? 'en-US-Studio-O');
            if ($voice_id === 'default') $voice_id = $this->settings['google_voice'] ?? 'en-US-Studio-O';

            $api_url = 'https://texttospeech.googleapis.com/v1/text:synthesize?key=' . $api_key;
            
            // Optimized request body
            $body = [
                'input' => ['text' => $text_chunk],
                'voice' => [
                    'languageCode' => substr($voice_id, 0, 5),
                    'name' => $voice_id
                ],
                'audioConfig' => [
                    'audioEncoding' => 'MP3',
                    'speakingRate' => 1.0,
                    'pitch' => 0.0
                ]
            ];
            
            $args['body'] = json_encode($body);
            $args['headers'] = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];
            
            $response = wp_remote_post($api_url, $args);

            if (is_wp_error($response)) {
                return new WP_Error('google_request_failed', 'Google TTS request failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_data = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($response_code !== 200) {
                $error_message = 'Google API Error (Code: ' . $response_code . ')';
                if (isset($response_data['error']['message'])) {
                    $error_message .= ': ' . $response_data['error']['message'];
                }
                return new WP_Error('google_api_error', $error_message);
            }
            
            if (!isset($response_data['audioContent'])) {
                return new WP_Error('google_no_audio', 'Google API did not return audio content.');
            }
            
            $response_body = base64_decode($response_data['audioContent']);

        } else if ($ai_service === 'gemini') {
            // Gemini implementation (keeping existing code but with improved error handling)
            $voice_id = get_post_meta($post_id, '_ai_voice_gemini_voice', true) ?: ($this->settings['gemini_voice'] ?? 'Kore');
            if ($voice_id === 'default') $voice_id = $this->settings['gemini_voice'] ?? 'Kore';
            
            $gemini_tone = get_post_meta($post_id, '_ai_voice_gemini_tone', true) ?: ($this->settings['gemini_tone'] ?? 'neutral');
            if ($gemini_tone === 'default') $gemini_tone = $this->settings['gemini_tone'] ?? 'neutral';

            $tone_prompt = '';
            switch ($gemini_tone) {
                case 'newscaster': $tone_prompt = 'Say in a professional, newscaster voice: '; break;
                case 'conversational': $tone_prompt = 'Say in a casual, conversational tone: '; break;
                case 'calm': $tone_prompt = 'Say in a calm and soothing voice: '; break;
            }
            $final_text = $tone_prompt . $text_chunk;

            $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-tts:generateContent?key=' . $api_key;
            $body = [
                'model' => 'gemini-2.5-flash-preview-tts',
                'contents' => [['parts' => [['text' => $final_text]]]],
                'generationConfig' => [
                    'responseModalities' => ["AUDIO"],
                    'speechConfig' => ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $voice_id]]]
                ]
            ];
            $args['body'] = json_encode($body);
            $args['headers'] = ['Content-Type' => 'application/json'];
            $response = wp_remote_post($api_url, $args);

            if (is_wp_error($response)) return $response;
            
            $response_data = json_decode(wp_remote_retrieve_body($response), true);
            
            if (wp_remote_retrieve_response_code($response) !== 200 || !isset($response_data['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
                $error_message = $response_data['error']['message'] ?? 'Unknown error. Verify Google Cloud Project has billing enabled and the Generative Language API is active.';
                return new WP_Error('gemini_api_error', 'Gemini API Error: ' . $error_message);
            }
            $response_body = base64_decode($response_data['candidates'][0]['content']['parts'][0]['inlineData']['data']);

        } else if ($ai_service === 'openai') {
            // OpenAI implementation (keeping existing code)
            $voice_id = get_post_meta($post_id, '_ai_voice_openai_voice', true) ?: ($this->settings['openai_voice'] ?? 'nova');
            if ($voice_id === 'default') $voice_id = $this->settings['openai_voice'] ?? 'nova';

            $api_url = 'https://api.openai.com/v1/audio/speech';
            $body = ['model' => 'tts-1', 'input' => $text_chunk, 'voice' => $voice_id];
            $args['body'] = json_encode($body);
            $args['headers'] = ['Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'];
            $response = wp_remote_post($api_url, $args);

            if (is_wp_error($response)) return $response;
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                 return new WP_Error('openai_api_error', 'OpenAI API Error: ' . (json_decode(wp_remote_retrieve_body($response), true)['error']['message'] ?? 'Unknown error.'));
            }
            $response_body = wp_remote_retrieve_body($response);
        }

        if (empty($response_body)) {
            return new WP_Error('api_empty_response', 'API returned empty audio data.');
        }

        // Validate audio data
        if (strlen($response_body) < 100) { // MP3 files should be at least 100 bytes
            return new WP_Error('api_invalid_audio', 'API returned invalid audio data.');
        }

        $temp_file = wp_tempnam('ai-voice-chunk-');
        if (file_put_contents($temp_file, $response_body) === false) {
            return new WP_Error('temp_file_failed', 'Failed to write temporary audio file.');
        }
        
        return $temp_file;
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