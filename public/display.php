<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class AIVoice_Public {

    private $settings;
    private $max_chunk_size = 800;   // chunk size for TTS requests
    private $max_total_chars = 8000; // overall cap to avoid timeouts on shared hosting

    /**
     * Check if the post belongs to a disabled category
     */
    private function is_category_disabled( $post_id ) {
        $disabled_categories = isset($this->settings['disabled_categories']) ? (array)$this->settings['disabled_categories'] : array();
        
        if ( empty($disabled_categories) ) {
            return false;
        }
        
        // Get all categories for this post (including parent categories)
        $post_categories = wp_get_post_categories( $post_id );
        
        foreach ( $post_categories as $cat_id ) {
            // Check if this category or any of its parents are disabled
            if ( in_array( $cat_id, $disabled_categories ) ) {
                return true;
            }
            
            // Check parent categories
            $parent_cats = get_ancestors( $cat_id, 'category' );
            foreach ( $parent_cats as $parent_id ) {
                if ( in_array( $parent_id, $disabled_categories ) ) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    // Local TTS method
    private function generate_local_tts_audio($text_chunk) {
        $local_tts_url = $this->settings['local_tts_url'] ?? 'http://localhost:5000/synthesize';
        
        $args = [
            'timeout' => 120,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'text' => $text_chunk,
                'voice' => 'default'
            ])
        ];
        
        $response = wp_remote_post($local_tts_url, $args);
        
        if (is_wp_error($response)) {
            return new WP_Error('local_tts_request_failed', 'Local TTS request failed: ' . $response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code !== 200) {
            $msg = 'Local TTS API Error (Code: ' . $code . ')';
            if (isset($data['error'])) {
                $msg .= ': ' . $data['error'];
            }
            return new WP_Error('local_tts_api_error', $msg);
        }
        
        if (!isset($data['audioContent'])) {
            return new WP_Error('local_tts_no_audio', 'Local TTS API did not return audio content.');
        }
        
        // Decode base64 audio data
        $audio_data = base64_decode($data['audioContent']);
        
        if (empty($audio_data)) {
            return new WP_Error('local_tts_invalid_audio', 'Local TTS returned invalid audio data.');
        }
        
        // Create temporary file
        $temp_file = wp_tempnam('ai-voice-local-');
        if (file_put_contents($temp_file, $audio_data) === false) {
            return new WP_Error('temp_file_failed', 'Failed to write temporary audio file.');
        }
        
        return $temp_file;
    }

    // Local Ollama summary method
    private function generate_local_ollama_summary($text, $model, $prompt) {
        $api_url = $this->settings['local_ollama_url'] ?? 'http://localhost:5001/v1/chat/completions';
        
        $messages = [
            ['role'=>'system','content'=>$prompt],
            ['role'=>'user','content'=>"Please analyze this article and provide the key takeaways:\n\n".$text]
        ];

        $args = [
            'timeout' => 180, // Longer timeout for local models
            'headers' => [
                'Content-Type'  => 'application/json'
            ],
            'body' => json_encode([
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => 500,
                'temperature' => 0.3
            ])
        ];

        $response = wp_remote_post($api_url, $args);
        if (is_wp_error($response)) {
            return new WP_Error('local_ollama_request_failed', 'Local Ollama request failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code !== 200) {
            $msg = 'Local Ollama API Error (Code: '.$code.')';
            if (isset($data['error']['message'])) $msg .= ': ' . $data['error']['message'];
            return new WP_Error('local_ollama_api_error', $msg);
        }
        
        if (!isset($data['choices'][0]['message']['content'])) {
            return new WP_Error('local_ollama_no_content', 'Local Ollama did not return summary content.');
        }

        return $this->format_summary($data['choices'][0]['message']['content']);
    }

    public function __construct() {
        $this->settings = get_option('ai_voice_settings');
        add_filter( 'the_content', [ $this, 'maybe_display_player' ], 99 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ], 1 );

        // Audio generation
        add_action( 'wp_ajax_ai_voice_generate_audio', [ $this, 'handle_generate_audio_ajax' ] );
        add_action( 'wp_ajax_nopriv_ai_voice_generate_audio', [ $this, 'handle_generate_audio_ajax' ] );

        // Audio streaming (Range/206) to bypass host/CDN 412 on static MP3s
        add_action( 'wp_ajax_ai_voice_stream_audio', [ $this, 'handle_stream_audio_ajax' ] );
        add_action( 'wp_ajax_nopriv_ai_voice_stream_audio', [ $this, 'handle_stream_audio_ajax' ] );

        // Summary generation
        add_action( 'wp_ajax_ai_voice_generate_summary', [ $this, 'handle_generate_summary_ajax' ] );
        add_action( 'wp_ajax_nopriv_ai_voice_generate_summary', [ $this, 'handle_generate_summary_ajax' ] );
    }

    public function enqueue_assets() {
        // Enqueue CSS in head (not just register)
        wp_enqueue_style(
            'ai-voice-player-css',
            AI_VOICE_PLUGIN_URL . 'public/assets/css/player.css',
            [],
            AI_VOICE_VERSION
        );

        // Register JS (we’ll enqueue it later only on pages with the player)
        wp_register_script(
            'ai-voice-player-js',
            AI_VOICE_PLUGIN_URL . 'public/assets/js/player.js',
            ['jquery'],
            AI_VOICE_VERSION,
            true
        );
    }

    public function maybe_display_player( $content ) {
        // Only on single posts in main loop
        if ( ! is_single() || ! in_the_loop() || ! is_main_query() ) return $content;
        if ( is_home() || is_front_page() || is_archive() || is_category() || is_tag() || is_search() ) return $content;

        $post_id = get_the_ID();
        if ( ! $post_id || get_post_type($post_id) !== 'post' ) return $content;

        // Check if post belongs to a disabled category
        if ( $this->is_category_disabled($post_id) ) return $content;

        $status = get_post_meta($post_id, '_ai_voice_status', true);
        $is_enabled_globally = !empty($this->settings['enable_globally']) && $this->settings['enable_globally'] == '1';
        if ($status === 'disabled' || ($status !== 'enabled' && !$is_enabled_globally)) return $content;

        $this->prepare_frontend_scripts($post_id);

        ob_start();
        include( AI_VOICE_PLUGIN_DIR . 'public/partials/player-template.php' );
        $player_html = ob_get_clean();

        return $player_html . $content;
    }

    public function handle_generate_audio_ajax() {
        check_ajax_referer('ai_voice_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) wp_send_json_error(['message' => 'Invalid Post ID.']);

        // Always extract server-side to avoid unreliable DOM scraping
        $raw_text = $this->get_post_plain_text($post_id);
        if (empty($raw_text) || strlen(trim($raw_text)) < 10) {
            wp_send_json_error(['message' => 'No readable text found in the post content.']);
        }

        // Remove leading (CITY – Date) – style dateline, then cap and clean
        $raw_text = $this->strip_leading_parenthetical_dateline($raw_text);
        if (strlen($raw_text) > $this->max_total_chars) $raw_text = substr($raw_text, 0, $this->max_total_chars) . '...';
        $final_text = $this->clean_text_for_tts($raw_text);

        // Generation method and AI service
        $generation_method = get_post_meta($post_id, '_ai_voice_generation_method', true);
        if ($generation_method === 'default' || empty($generation_method)) $generation_method = $this->settings['generation_method'] ?? 'chunked';

        $ai_service = get_post_meta($post_id, '_ai_voice_ai_service', true) ?: ($this->settings['default_ai'] ?? 'google');
        if ($ai_service === 'default') $ai_service = $this->settings['default_ai'] ?? 'google';

        // Use "single" for OpenAI, else honor setting
        $content_hash = md5($final_text);
        if ($ai_service === 'openai' || $generation_method === 'single') {
            $generation_result = $this->generate_single_request_audio($post_id, $final_text);
        } else {
            $generation_result = $this->generate_chunked_audio($post_id, $final_text);
        }

        if (is_wp_error($generation_result)) {
            wp_send_json_error(['message' => $generation_result->get_error_message()]);
        }

        // Stream URL through admin-ajax with Range/206 support (bypasses 412)
        $stream_url = $this->build_stream_url($post_id, $content_hash);

        wp_send_json_success([
            'audioUrl' => $stream_url
        ]);
    }

    public function handle_generate_summary_ajax() {
        check_ajax_referer('ai_voice_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) wp_send_json_error(['message' => 'Invalid Post ID.']);

        // Server-side extraction for consistency
        $raw_text = $this->get_post_plain_text($post_id);
        if (empty($raw_text) || strlen(trim($raw_text)) < 50) {
            wp_send_json_error(['message' => 'Not enough text content found to summarize.']);
        }

        $raw_text = $this->strip_leading_parenthetical_dateline($raw_text);
        if (strlen($raw_text) > 15000) $raw_text = substr($raw_text, 0, 15000) . '...';
        $final_text = $this->clean_text_for_tts($raw_text);

        $content_hash = md5($final_text);
        $cached_summary = get_post_meta($post_id, '_ai_voice_summary_' . $content_hash, true);
        if (!empty($cached_summary)) {
            wp_send_json_success(['summary' => $cached_summary]);
            return;
        }

        $summary_result = $this->generate_summary($post_id, $final_text);

        if (is_wp_error($summary_result)) {
            wp_send_json_error(['message' => $summary_result->get_error_message()]);
        }

        update_post_meta($post_id, '_ai_voice_summary_' . $content_hash, $summary_result);
        wp_send_json_success(['summary' => $summary_result]);
    }

    // Build streaming URL (admin-ajax) to serve MP3 with proper Range support
    private function build_stream_url($post_id, $content_hash) {
        $nonce = wp_create_nonce("ai_voice_stream_{$post_id}_{$content_hash}");
        return add_query_arg([
            'action'  => 'ai_voice_stream_audio',
            'post_id' => $post_id,
            'hash'    => $content_hash,
            'nonce'   => $nonce,
            'nc'      => wp_generate_password(6, false, false), // cache buster
        ], admin_url('admin-ajax.php'));
    }

    // Range-enabled streaming endpoint
    public function handle_stream_audio_ajax() {
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        $hash    = isset($_GET['hash']) ? sanitize_text_field($_GET['hash']) : '';
        $nonce   = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '';

        if (!$post_id || empty($hash) || !wp_verify_nonce($nonce, "ai_voice_stream_{$post_id}_{$hash}")) {
            status_header(403); exit('Forbidden');
        }

        $stored_url = get_post_meta($post_id, '_ai_voice_audio_url_' . $hash, true);
        if (empty($stored_url)) { status_header(404); exit('Not Found'); }

        $upload_dir = wp_upload_dir();
        $file_path  = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $stored_url);

        if (!file_exists($file_path) || !is_readable($file_path)) {
            status_header(404); exit('Not Found');
        }

        $filesize   = filesize($file_path);
        $lastmod_ts = filemtime($file_path);
        $lastmod    = gmdate('D, d M Y H:i:s', $lastmod_ts) . ' GMT';
        $etag       = '"' . md5($file_path . '|' . $filesize . '|' . $lastmod_ts) . '"';

        if ((isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) ||
            (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && trim($_SERVER['HTTP_IF_MODIFIED_SINCE']) === $lastmod)) {
            header('HTTP/1.1 304 Not Modified');
            header('ETag: ' . $etag);
            header('Last-Modified: ' . $lastmod);
            exit;
        }

        header('Content-Type: audio/mpeg');
        header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, max-age=31536000, immutable');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . $lastmod);

        if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
            header('Content-Length: ' . $filesize);
            exit;
        }

        $range = null;
        if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/i', $_SERVER['HTTP_RANGE'], $m)) {
            $start = ($m[1] !== '') ? intval($m[1]) : 0;
            $end   = ($m[2] !== '') ? intval($m[2]) : ($filesize - 1);
            if ($start > $end || $end >= $filesize) {
                header('HTTP/1.1 416 Range Not Satisfiable');
                header('Content-Range: bytes */' . $filesize);
                exit;
            }
            $range = [$start, $end];
        }

        if (function_exists('ob_get_level')) { while (ob_get_level() > 0) { @ob_end_clean(); } }
        @set_time_limit(0);
        ignore_user_abort(true);

        $chunk_size = 8192;
        $fp = fopen($file_path, 'rb');
        if (!$fp) { status_header(500); exit('Cannot open file'); }

        if ($range) {
            list($start, $end) = $range;
            $length = $end - $start + 1;
            header('HTTP/1.1 206 Partial Content');
            header("Content-Length: $length");
            header("Content-Range: bytes $start-$end/$filesize");
            fseek($fp, $start);
            $left = $length;
            while ($left > 0 && !feof($fp)) {
                $read = ($left > $chunk_size) ? $chunk_size : $left;
                $buf  = fread($fp, $read);
                echo $buf;
                flush();
                $left -= strlen($buf);
            }
        } else {
            header('Content-Length: ' . $filesize);
            while (!feof($fp)) {
                $buf = fread($fp, $chunk_size);
                echo $buf;
                flush();
            }
        }
        fclose($fp);
        exit;
    }

    private function generate_summary($post_id, $text) {
        $summary_api   = $this->settings['summary_api'] ?? 'openrouter';
        $summary_model = $this->settings['summary_model'] ?? 'anthropic/claude-3-haiku';
        $summary_prompt= $this->settings['summary_prompt'] ?? 'Create 3-5 key takeaways from this article. Each takeaway should be 1-2 lines maximum. Focus on the most important insights and actionable information.';

        if ($summary_api === 'openrouter') {
            return $this->generate_openrouter_summary($text, $summary_model, $summary_prompt);
        } else if ($summary_api === 'chatgpt') {
            $model = $this->settings['chatgpt_model'] ?? 'gpt-3.5-turbo';
            return $this->generate_chatgpt_summary($text, $model, $summary_prompt);
        } else if ($summary_api === 'local_ollama') {
            $model = $this->settings['local_ollama_model'] ?? 'qwen2.5:14b';
            return $this->generate_local_ollama_summary($text, $model, $summary_prompt);
        }
        
        return new WP_Error('invalid_api', 'Invalid summary API selected.');
    }

    private function generate_openrouter_summary($text, $model, $prompt) {
        $api_key = $this->settings['openrouter_api_key'] ?? '';
        if (empty($api_key)) return new WP_Error('no_api_key', 'OpenRouter API key not configured.');

        $language = $this->settings['summary_language'] ?? 'english';
        $language_instruction = "Respond ONLY in " . ucfirst($language) . ". ";
        
        $api_url = 'https://openrouter.ai/api/v1/chat/completions';
        $messages = [
            ['role'=>'system','content'=> $language_instruction . $prompt],
            ['role'=>'user','content'=>"Please analyze this article and provide the key takeaways:\n\n".$text]
        ];

        $args = [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => home_url(),
                'Referer'       => home_url(),
                'X-Title'       => get_bloginfo('name')
            ],
            'body' => json_encode(['model'=>$model,'messages'=>$messages,'max_tokens'=>500,'temperature'=>0.3])
        ];

        $response = wp_remote_post($api_url, $args);
        if (is_wp_error($response)) return new WP_Error('openrouter_request_failed', 'OpenRouter request failed: ' . $response->get_error_message());

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code !== 200) {
            $msg = 'OpenRouter API Error (Code: '.$code.')';
            if (isset($data['error']['message'])) $msg .= ': ' . $data['error']['message'];
            return new WP_Error('openrouter_api_error', $msg);
        }
        if (!isset($data['choices'][0]['message']['content'])) return new WP_Error('openrouter_no_content', 'OpenRouter did not return summary content.');

        return $this->format_summary($data['choices'][0]['message']['content']);
    }

    private function generate_chatgpt_summary($text, $model, $prompt) {
        $api_key = $this->settings['chatgpt_api_key'] ?? '';
        if (empty($api_key)) return new WP_Error('no_api_key', 'ChatGPT API key not configured.');

        $api_url = 'https://api.openai.com/v1/chat/completions';
        $messages = [
            ['role'=>'system','content'=>$prompt],
            ['role'=>'user','content'=>"Please analyze this article and provide the key takeaways:\n\n".$text]
        ];

        $args = [
            'timeout' => 45,
            'headers' => ['Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'],
            'body'    => json_encode(['model'=>$model,'messages'=>$messages,'max_tokens'=>500,'temperature'=>0.3])
        ];

        $response = wp_remote_post($api_url, $args);
        if (is_wp_error($response)) return new WP_Error('chatgpt_request_failed', 'ChatGPT request failed: ' . $response->get_error_message());

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code !== 200) {
            $msg = 'ChatGPT API Error (Code: '.$code.')';
            if (isset($data['error']['message'])) $msg .= ': ' . $data['error']['message'];
            return new WP_Error('chatgpt_api_error', $msg);
        }
        if (!isset($data['choices'][0]['message']['content'])) return new WP_Error('chatgpt_no_content', 'ChatGPT did not return summary content.');

        return $this->format_summary($data['choices'][0]['message']['content']);
    }

    private function format_summary($raw_summary) {
        $summary = trim($raw_summary);
        $lines = array_filter(array_map('trim', preg_split('/\r?\n/', $summary)));
        if (empty($lines)) return '<p>No takeaways could be generated.</p>';

        $formatted = '<ul class="takeaways-list">';
        foreach ($lines as $line) {
            if (empty($line) ||
                preg_match('/^(here are|key takeaways?|takeaways?|summary|main points?):?$/i', $line) ||
                preg_match('/^here are the key takeaways from/i', $line) ||
                preg_match('/^Key Takeaways:/i', $line) ||
                preg_match('/^based on the article/i', $line)) continue;

            $line = preg_replace('/^[-•*]\s*/', '', $line);
            $line = preg_replace('/^\d+\.\s*/', '', $line);
            if (!empty($line) && strlen($line) > 10) $formatted .= '<li>' . esc_html($line) . '</li>';
        }
        $formatted .= '</ul>';
        if ($formatted === '<ul class="takeaways-list"></ul>') return '<p>No valid takeaways could be extracted.</p>';
        return $formatted;
    }

    // Extract the post content on the server and return clean plain text
    private function get_post_plain_text($post_id) {
        $post = get_post($post_id);
        if (!$post) return '';

        // Build full HTML via WP, then strip to text
        $html = apply_filters('the_content', $post->post_content);
        // Make sure entities are normalized before stripping
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset'));

        // Remove scripts/styles explicitly (if any survived filters)
        $html = preg_replace('#<script[^>]*>.*?</script>#is', ' ', $html);
        $html = preg_replace('#<style[^>]*>.*?</style>#is', ' ', $html);

        // Strip all tags and collapse whitespace
        $text = wp_strip_all_tags($html, true);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    // Remove leading parenthetical datelines like: "(CITY – Month 1, 2025) – "
    private function strip_leading_parenthetical_dateline($text) {
        $text = ltrim($text);
        for ($i = 0; $i < 2; $i++) {
            if (preg_match('/^\(([^)]{1,120})\)\s*(?:[-–—]\s*)?/u', $text, $m)) {
                if (preg_match('/\b(19|20)\d{2}\b|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec|January|February|March|April|May|June|July|August|September|October|November|December|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday\b/i', $m[1])) {
                    $text = ltrim(substr($text, strlen($m[0])));
                    continue;
                }
            }
            break;
        }
        return $text;
    }

    private function clean_text_for_tts($text) {
        $text = str_replace(['•', '–', '—'], ['-', '-', '-'], $text);
        $text = preg_replace('/https?:\/\/[^\s]+/', '', $text);
        $text = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '', $text);
        $text = preg_replace('/[.]{3,}/', '...', $text);
        $text = preg_replace('/[!]{2,}/', '!', $text);
        $text = preg_replace('/[?]{2,}/', '?', $text);
        return trim($text);
    }

    private function generate_single_request_audio($post_id, $text_to_speak) {
        $content_hash = md5($text_to_speak);
        $cached_audio_url = get_post_meta($post_id, '_ai_voice_audio_url_' . $content_hash, true);
        if (!empty($cached_audio_url) && $this->verify_audio_file_exists($cached_audio_url)) {
            return $cached_audio_url;
        }
        $audio_file_path = $this->generate_audio_for_chunk($post_id, $text_to_speak);
        if (is_wp_error($audio_file_path)) return $audio_file_path;
        return $this->save_audio_file($post_id, $audio_file_path, $content_hash);
    }

    private function generate_chunked_audio($post_id, $text_to_speak) {
        $content_hash = md5($text_to_speak);
        $cached_audio_url = get_post_meta($post_id, '_ai_voice_audio_url_' . $content_hash, true);
        if (!empty($cached_audio_url) && $this->verify_audio_file_exists($cached_audio_url)) {
            return $cached_audio_url;
        }
        $text_chunks = $this->smart_chunk_text($text_to_speak);
        $audio_files = [];
        foreach ($text_chunks as $chunk) {
            $chunk_result = $this->generate_audio_for_chunk_with_retry($post_id, $chunk, 2);
            if (is_wp_error($chunk_result)) {
                foreach ($audio_files as $fp) { if (file_exists($fp)) unlink($fp); }
                return $chunk_result;
            }
            $audio_files[] = $chunk_result;
        }
        if (empty($audio_files)) return new WP_Error('no_audio_generated', 'Could not generate any audio chunks.');
        return $this->merge_audio_files($post_id, $audio_files, $content_hash);
    }

    private function smart_chunk_text($text) {
        $sentences = preg_split('/(?<=[.?!])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $chunks = [];
        $current = '';
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') continue;
            if (strlen($current . ' ' . $sentence) > $this->max_chunk_size) {
                if ($current !== '') {
                    $chunks[] = trim($current);
                    $current = $sentence;
                } else {
                    $chunks = array_merge($chunks, $this->split_by_words($sentence));
                }
            } else {
                $current .= ($current === '' ? '' : ' ') . $sentence;
            }
        }
        if ($current !== '') $chunks[] = trim($current);
        return array_values(array_filter($chunks, fn($c) => strlen(trim($c)) > 0));
    }

    private function split_by_words($text) {
        $words = explode(' ', $text);
        $chunks = [];
        $current = '';
        foreach ($words as $w) {
            if (strlen($current . ' ' . $w) > $this->max_chunk_size) {
                if ($current !== '') { $chunks[] = trim($current); $current = $w; }
                else { $chunks[] = $w; }
            } else {
                $current .= ($current === '' ? '' : ' ') . $w;
            }
        }
        if ($current !== '') $chunks[] = trim($current);
        return $chunks;
    }

    private function generate_audio_for_chunk_with_retry($post_id, $text_chunk, $max_retries = 2) {
        $last_error = null;
        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            $result = $this->generate_audio_for_chunk($post_id, $text_chunk);
            if (!is_wp_error($result)) return $result;
            $last_error = $result;
            if ($attempt < $max_retries) sleep($attempt);
        }
        return $last_error;
    }

    private function verify_audio_file_exists($url) {
        if (empty($url)) return false;
        $upload_dir = wp_upload_dir();
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
        return file_exists($file_path);
    }

    private function save_audio_file($post_id, $audio_file_path, $content_hash) {
        $upload_dir = wp_upload_dir();
        $final_filename = 'ai-voice-' . $post_id . '-' . substr($content_hash, 0, 8) . '.mp3';
        $final_filepath = $upload_dir['path'] . '/' . $final_filename;
        $final_fileurl  = $upload_dir['url']  . '/' . $final_filename;

        if (!@rename($audio_file_path, $final_filepath)) {
            if (!@copy($audio_file_path, $final_filepath)) {
                return new WP_Error('file_move_failed', 'Failed to move/copy audio file.');
            }
            @unlink($audio_file_path);
        }

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
        $temp_merged_file = wp_tempnam('ai-voice-merged-');
        $out = fopen($temp_merged_file, 'wb');
        if (!$out) return new WP_Error('merge_failed', 'Could not open temporary file for merging.');
        foreach ($audio_files as $fp) {
            if (file_exists($fp)) {
                $data = file_get_contents($fp);
                fwrite($out, $data);
                @unlink($fp);
            }
        }
        fclose($out);
        if (filesize($temp_merged_file) === 0) {
            @unlink($temp_merged_file);
            return new WP_Error('merge_failed', 'No audio content to merge.');
        }
        return $this->save_audio_file($post_id, $temp_merged_file, $content_hash);
    }
    
    private function generate_audio_for_chunk($post_id, $text_chunk) {
        $ai_service = get_post_meta($post_id, '_ai_voice_ai_service', true) ?: ($this->settings['default_ai'] ?? 'google');
        if ($ai_service === 'default') $ai_service = $this->settings['default_ai'] ?? 'google';

        // Add local TTS option
        if ($ai_service === 'local') {
            return $this->generate_local_tts_audio($text_chunk);
        }

        $api_key = '';
        switch($ai_service) {
            case 'google': $api_key = $this->settings['google_api_key'] ?? ''; break;
            case 'gemini': $api_key = $this->settings['gemini_api_key'] ?? ''; break;
            case 'openai': $api_key = $this->settings['openai_api_key'] ?? ''; break;
        }
        if (empty($api_key)) return new WP_Error('no_api_key', 'API key for ' . ucfirst($ai_service) . ' is not configured.');

        $response_body = null;
        $args = [
            'timeout' => 30,
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
            $args['headers'] = ['Content-Type' => 'application/json','Accept' => 'application/json'];
            $response = wp_remote_post($api_url, $args);
            if (is_wp_error($response)) return new WP_Error('google_request_failed', 'Google TTS request failed: ' . $response->get_error_message());
            $code = wp_remote_retrieve_response_code($response);
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if ($code !== 200) {
                $msg = 'Google API Error (Code: ' . $code . ')';
                if (isset($data['error']['message'])) $msg .= ': ' . $data['error']['message'];
                return new WP_Error('google_api_error', $msg);
            }
            if (!isset($data['audioContent'])) return new WP_Error('google_no_audio', 'Google API did not return audio content.');
            $response_body = base64_decode($data['audioContent']);

        } else if ($ai_service === 'gemini') {
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
                    'speechConfig' => [
                        'voiceConfig'   => ['prebuiltVoiceConfig' => ['voiceName' => $voice_id]],
                        'audioEncoding' => 'MP3'
                    ]
                ]
            ];
            $args['body'] = json_encode($body);
            $args['headers'] = ['Content-Type' => 'application/json'];
            $response = wp_remote_post($api_url, $args);
            if (is_wp_error($response)) return $response;
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (wp_remote_retrieve_response_code($response) !== 200 || !isset($data['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
                $err = $data['error']['message'] ?? 'Unknown error. Verify billing + API enabled.';
                return new WP_Error('gemini_api_error', 'Gemini API Error: ' . $err);
            }
            $response_body = base64_decode($data['candidates'][0]['content']['parts'][0]['inlineData']['data']);

        } else if ($ai_service === 'openai') {
            $voice_id = get_post_meta($post_id, '_ai_voice_openai_voice', true) ?: ($this->settings['openai_voice'] ?? 'nova');
            if ($voice_id === 'default') $voice_id = $this->settings['openai_voice'] ?? 'nova';

            $api_url = 'https://api.openai.com/v1/audio/speech';
            $body = ['model' => 'tts-1', 'input' => $text_chunk, 'voice' => $voice_id];
            $args['body'] = json_encode($body);
            $args['headers'] = ['Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'];
            $response = wp_remote_post($api_url, $args);
            if (is_wp_error($response)) return $response;
            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                return new WP_Error('openai_api_error', 'OpenAI API Error: ' . (json_decode(wp_remote_retrieve_body($response), true)['error']['message'] ?? 'Unknown error.'));
            }
            $response_body = wp_remote_retrieve_body($response);
        }

        if (empty($response_body)) return new WP_Error('api_empty_response', 'API returned empty audio data.');
        if (strlen($response_body) < 100) return new WP_Error('api_invalid_audio', 'API returned invalid audio data.');

        $temp_file = wp_tempnam('ai-voice-chunk-');
        if (file_put_contents($temp_file, $response_body) === false) return new WP_Error('temp_file_failed', 'Failed to write temporary audio file.');
        return $temp_file;
    }

    private function prepare_frontend_scripts($post_id) {
        // We keep this: script gets enqueued only where needed; CSS is already in head.
        wp_enqueue_script('ai-voice-player-js');

        // Inject colors as before (these load as inline <style> tied to the CSS handle)
        $this->inject_custom_colors();

        $theme = get_post_meta($post_id, '_ai_voice_theme', true) ?: ($this->settings['theme'] ?? 'light');
        if ($theme === 'default') $theme = $this->settings['theme'] ?? 'light';

        $ai_service = get_post_meta($post_id, '_ai_voice_ai_service', true) ?: ($this->settings['default_ai'] ?? 'google');
        if ($ai_service === 'default') $ai_service = $this->settings['default_ai'] ?? 'google';

        wp_localize_script('ai-voice-player-js', 'aiVoiceData', [
            'ajax_url'  => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('ai_voice_nonce'),
            'post_id'   => $post_id,
            'title'     => get_the_title($post_id),
            'theme'     => esc_attr($theme),
            'aiService' => esc_attr($ai_service),
        ]);
    }

    private function inject_custom_colors() {
        $bg_light = $this->settings['bg_color_light'] ?? '#ffffff';
        $bg_dark  = $this->settings['bg_color_dark'] ?? '#1e293b';
        $text_light = $this->settings['text_color_light'] ?? '#0f172a';
        $text_dark  = $this->settings['text_color_dark'] ?? '#f1f5f9';
        $accent_light = $this->settings['accent_color_light'] ?? '#3b82f6';
        $accent_dark  = $this->settings['accent_color_dark'] ?? '#60a5fa';
        $summary_light= $this->settings['summary_color_light'] ?? '#6b7280';
        $summary_dark = $this->settings['summary_color_dark'] ?? '#9ca3af';

        $bg_secondary_light = $this->lighten_color($bg_light, 0.04);
        $bg_secondary_dark  = $this->lighten_color($bg_dark, 0.15);
        $text_secondary_light= $this->lighten_color($text_light, 0.4);
        $text_secondary_dark = $this->darken_color($text_dark, 0.3);
        $border_light = $this->lighten_color($text_light, 0.8);
        $border_dark  = $this->lighten_color($bg_dark, 0.25);
        $accent_hover_light = $this->darken_color($accent_light, 0.1);
        $accent_hover_dark  = $this->darken_color($accent_dark, 0.1);

        $custom_css = "
        #ai-voice-player-wrapper {
            --bg-light: {$bg_light};
            --bg-secondary-light: {$bg_secondary_light};
            --text-primary-light: {$text_light};
            --text-secondary-light: {$text_secondary_light};
            --accent-light: {$accent_light};
            --accent-hover-light: {$accent_hover_light};
            --border-light: {$border_light};
            --summary-light: {$summary_light};

            --bg-dark: {$bg_dark};
            --bg-secondary-dark: {$bg_secondary_dark};
            --text-primary-dark: {$text_dark};
            --text-secondary-dark: {$text_secondary_dark};
            --accent-dark: {$accent_dark};
            --accent-hover-dark: {$accent_hover_dark};
            --border-dark: {$border_dark};
            --summary-dark: {$summary_dark};
        }";

        wp_add_inline_style('ai-voice-player-css', $custom_css);
    }

    private function lighten_color($hex, $percent) {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
        $r = min(255, $r + (255 - $r) * $percent);
        $g = min(255, $g + (255 - $g) * $percent);
        $b = min(255, $b + (255 - $b) * $percent);
        return sprintf('#%02x%02x%02x', round($r), round($g), round($b));
    }

    private function darken_color($hex, $percent) {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
        $r = max(0, $r * (1 - $percent));
        $g = max(0, $g * (1 - $percent));
        $b = max(0, $b * (1 - $percent));
        return sprintf('#%02x%02x%02x', round($r), round($g), round($b));
    }
}