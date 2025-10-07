<?php
// Complete settings.php with Edge TTS multi-language support
// Replace your existing settings.php with this file

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AIVoice_Settings {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        
        // AJAX endpoint to fetch voices from Edge TTS server
        add_action( 'wp_ajax_ai_voice_fetch_edge_voices', [ $this, 'fetch_edge_voices_ajax' ] );
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_ai-voice') {
            return;
        }
        wp_enqueue_script('ai-voice-admin-js', AI_VOICE_PLUGIN_URL . 'admin/assets/js/settings.js', ['jquery'], AI_VOICE_VERSION, true);
        
        // Add AJAX URL for fetching voices
        wp_localize_script('ai-voice-admin-js', 'aiVoiceAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_voice_admin_nonce')
        ]);
    }
    
    // AJAX handler to fetch voices from Edge TTS server
    public function fetch_edge_voices_ajax() {
        check_ajax_referer('ai_voice_admin_nonce', 'nonce');
        
        $language = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : '';
        $settings = get_option('ai_voice_settings');
        $tts_url = $settings['local_tts_url'] ?? 'http://localhost:6000';
        
        // Remove /synthesize if it's there
        $base_url = str_replace('/synthesize', '', $tts_url);
        $voices_url = $base_url . '/voices';
        
        if (!empty($language)) {
            $voices_url .= '?language=' . $language;
        }
        
        $response = wp_remote_get($voices_url, ['timeout' => 10]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Could not connect to TTS server']);
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        wp_send_json_success($data);
    }

    public function add_admin_menu() {
        add_options_page(
            'AI Voice Settings',
            'AI Voice',
            'manage_options',
            'ai-voice',
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'ai_voice_group', 'ai_voice_settings' );
    }

    public function render_settings_page() {
        $options = get_option( 'ai_voice_settings' );
        $google_voices = $this->get_google_voices();
        $chatgpt_models = $this->get_chatgpt_models();
        ?>
        <div class="wrap">
            <h1>AI Voice Settings</h1>
            <form action="options.php" method="post">
                <?php settings_fields( 'ai_voice_group' ); ?>
                
                <h2 class="title">API Keys</h2>
                <p>Enter your API keys from Google Cloud, Google AI (Gemini), OpenAI, and summary services.</p>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[google_api_key]">Google Cloud TTS API Key</label></th>
                            <td><input type="text" name="ai_voice_settings[google_api_key]" value="<?php echo esc_attr( $options['google_api_key'] ?? '' ); ?>" class="regular-text">
                            <p class="description">For legacy voices (WaveNet, Studio, etc.).</p></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[gemini_api_key]">Google AI (Gemini) API Key</label></th>
                            <td><input type="text" name="ai_voice_settings[gemini_api_key]" value="<?php echo esc_attr( $options['gemini_api_key'] ?? '' ); ?>" class="regular-text">
                            <p class="description">For modern Gemini TTS voices.</p></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[openai_api_key]">OpenAI API Key</label></th>
                            <td><input type="text" name="ai_voice_settings[openai_api_key]" value="<?php echo esc_attr( $options['openai_api_key'] ?? '' ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[openrouter_api_key]">OpenRouter API Key</label></th>
                            <td><input type="text" name="ai_voice_settings[openrouter_api_key]" value="<?php echo esc_attr( $options['openrouter_api_key'] ?? '' ); ?>" class="regular-text">
                            <p class="description">For AI-powered article summaries via OpenRouter.</p></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[chatgpt_api_key]">ChatGPT API Key</label></th>
                            <td><input type="text" name="ai_voice_settings[chatgpt_api_key]" value="<?php echo esc_attr( $options['chatgpt_api_key'] ?? '' ); ?>" class="regular-text">
                            <p class="description">Alternative to OpenRouter for summaries (uses OpenAI directly).</p></td>
                        </tr>
                    </tbody>
                </table>

                <h2 class="title">Frontend Text Customization</h2>
                <p>Customize all text displayed to users on the frontend player.</p>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[text_listen_to_article]">Player Title Text</label></th>
                            <td>
                                <input type="text" name="ai_voice_settings[text_listen_to_article]" value="<?php echo esc_attr( $options['text_listen_to_article'] ?? 'Listen to the article' ); ?>" class="regular-text">
                                <p class="description">Default text shown in the player header.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[text_generating_audio]">Generating Audio Text</label></th>
                            <td>
                                <input type="text" name="ai_voice_settings[text_generating_audio]" value="<?php echo esc_attr( $options['text_generating_audio'] ?? 'Generating audio...' ); ?>" class="regular-text">
                                <p class="description">Text shown while audio is being generated.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[text_key_takeaways]">Key Takeaways Title</label></th>
                            <td>
                                <input type="text" name="ai_voice_settings[text_key_takeaways]" value="<?php echo esc_attr( $options['text_key_takeaways'] ?? 'Key Takeaways' ); ?>" class="regular-text">
                                <p class="description">Title for the summary section.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[text_play_pause_label]">Play/Pause Button Label</label></th>
                            <td>
                                <input type="text" name="ai_voice_settings[text_play_pause_label]" value="<?php echo esc_attr( $options['text_play_pause_label'] ?? 'Play/Pause Audio' ); ?>" class="regular-text">
                                <p class="description">Accessibility label for the play/pause button.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[text_summary_button_label]">Summary Button Label</label></th>
                            <td>
                                <input type="text" name="ai_voice_settings[text_summary_button_label]" value="<?php echo esc_attr( $options['text_summary_button_label'] ?? 'Generate Article Summary' ); ?>" class="regular-text">
                                <p class="description">Accessibility label for the summary button.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[text_change_voice_label]">Change Voice Label</label></th>
                            <td>
                                <input type="text" name="ai_voice_settings[text_change_voice_label]" value="<?php echo esc_attr( $options['text_change_voice_label'] ?? 'Change Voice' ); ?>" class="regular-text">
                                <p class="description">Accessibility label for the voice selection button.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[text_toggle_theme_label]">Toggle Theme Label</label></th>
                            <td>
                                <input type="text" name="ai_voice_settings[text_toggle_theme_label]" value="<?php echo esc_attr( $options['text_toggle_theme_label'] ?? 'Toggle Theme' ); ?>" class="regular-text">
                                <p class="description">Accessibility label for the theme toggle button.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[text_close_summary_label]">Close Summary Label</label></th>
                            <td>
                                <input type="text" name="ai_voice_settings[text_close_summary_label]" value="<?php echo esc_attr( $options['text_close_summary_label'] ?? 'Close Summary' ); ?>" class="regular-text">
                                <p class="description">Accessibility label for the close summary button.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[text_playback_speed]">Playback Speed Modal Title</label></th>
                            <td>
                                <input type="text" name="ai_voice_settings[text_playback_speed]" value="<?php echo esc_attr( $options['text_playback_speed'] ?? 'Playback Speed' ); ?>" class="regular-text">
                                <p class="description">Title for the playback speed selection modal.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[text_select_voice]">Select Voice Modal Title</label></th>
                            <td>
                                <input type="text" name="ai_voice_settings[text_select_voice]" value="<?php echo esc_attr( $options['text_select_voice'] ?? 'Select a Voice' ); ?>" class="regular-text">
                                <p class="description">Title for the voice selection modal.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[text_voiced_by_google]">Voiced by Google Text</label></th>
                            <td>
                                <input type="text" name="ai_voice_settings[text_voiced_by_google]" value="<?php echo esc_attr( $options['text_voiced_by_google'] ?? 'Voiced by Google Cloud' ); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[text_voiced_by_gemini]">Voiced by Gemini Text</label></th>
                            <td>
                                <input type="text" name="ai_voice_settings[text_voiced_by_gemini]" value="<?php echo esc_attr( $options['text_voiced_by_gemini'] ?? 'Voiced by Gemini' ); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[text_voiced_by_openai]">Voiced by OpenAI Text</label></th>
                            <td>
                                <input type="text" name="ai_voice_settings[text_voiced_by_openai]" value="<?php echo esc_attr( $options['text_voiced_by_openai'] ?? 'Voiced by OpenAI' ); ?>" class="regular-text">
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <h2 class="title">Summary Settings</h2>
                <p>Configure the AI-powered summary feature that generates key takeaways from articles.</p>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[enable_summary]">Enable Summary Feature</label></th>
                            <td><input type="checkbox" name="ai_voice_settings[enable_summary]" value="1" <?php checked( isset($options['enable_summary']) ? $options['enable_summary'] : 0, 1 ); ?>> <span class="description">Show the summary button next to the play button.</span></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[summary_api]">Summary API Service</label></th>
                            <td>
                                <select id="ai_voice_summary_api" name="ai_voice_settings[summary_api]">
                                    <option value="openrouter" <?php selected( $options['summary_api'] ?? 'openrouter', 'openrouter' ); ?>>OpenRouter</option>
                                    <option value="chatgpt" <?php selected( $options['summary_api'] ?? 'openrouter', 'chatgpt' ); ?>>ChatGPT (OpenAI Direct)</option>
                                    <option value="local_ollama" <?php selected( $options['summary_api'] ?? 'openrouter', 'local_ollama' ); ?>>Local Ollama</option>
                                </select>
                            </td>
                        </tr>
                        <tr class="ai-voice-summary-row" data-api="local_ollama">
                            <th scope="row"><label for="ai_voice_settings[local_ollama_url]">Local Ollama API URL</label></th>
                            <td>
                                <input type="text" name="ai_voice_settings[local_ollama_url]" value="<?php echo esc_attr( $options['local_ollama_url'] ?? 'http://localhost:5001/v1/chat/completions' ); ?>" class="regular-text">
                                <p class="description">URL of your local Ollama API server</p>
                            </td>
                        </tr>
                        <tr class="ai-voice-summary-row" data-api="local_ollama">
                            <th scope="row"><label for="ai_voice_settings[local_ollama_model]">Local Ollama Model</label></th>
                            <td>
                                <select name="ai_voice_settings[local_ollama_model]">
                                    <option value="qwen2.5:14b" <?php selected( $options['local_ollama_model'] ?? 'qwen2.5:14b', 'qwen2.5:14b' ); ?>>Qwen2.5 14B (Best Quality)</option>
                                    <option value="llama3.1:8b" <?php selected( $options['local_ollama_model'] ?? 'qwen2.5:14b', 'llama3.1:8b' ); ?>>Llama 3.1 8B (Balanced)</option>
                                    <option value="llama3.2:1b" <?php selected( $options['local_ollama_model'] ?? 'qwen2.5:14b', 'llama3.2:1b' ); ?>>Llama 3.2 1B (Ultra Fast)</option>
                                    <option value="dolphin-llama3:8b" <?php selected( $options['local_ollama_model'] ?? 'qwen2.5:14b', 'dolphin-llama3:8b' ); ?>>Dolphin Llama3 8B (Fast)</option>
                                </select>
                            </td>
                        </tr>
                        <tr class="ai-voice-summary-row" data-api="openrouter">
                            <th scope="row"><label for="ai_voice_settings[summary_model]">OpenRouter Model</label></th>
                            <td>
                                <input type="text" name="ai_voice_settings[summary_model]" value="<?php echo esc_attr( $options['summary_model'] ?? 'x-ai/grok-2-1212:free' ); ?>" class="regular-text">
                                <p class="description">Enter the OpenRouter model ID. Examples: <code>x-ai/grok-2-1212:free</code>, <code>google/gemini-flash-1.5:free</code>, <code>mistralai/mistral-7b-instruct:free</code>, <code>anthropic/claude-3-haiku</code></p>
                            </td>
                        </tr>
                        <tr class="ai-voice-summary-row" data-api="chatgpt">
                            <th scope="row"><label for="ai_voice_settings[chatgpt_model]">ChatGPT Model</label></th>
                            <td>
                                <select name="ai_voice_settings[chatgpt_model]">
                                    <?php
                                    $current_chatgpt_model = $options['chatgpt_model'] ?? 'gpt-3.5-turbo';
                                    foreach ($chatgpt_models as $model_id => $model_name) {
                                        echo '<option value="' . esc_attr($model_id) . '" ' . selected($current_chatgpt_model, $model_id, false) . '>' . esc_html($model_name) . '</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description">GPT-3.5 is faster and cheaper, GPT-4 provides higher quality summaries.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[summary_language]">Summary Language</label></th>
                            <td>
                                <select name="ai_voice_settings[summary_language]">
                                    <option value="english" <?php selected( $options['summary_language'] ?? 'english', 'english' ); ?>>English</option>
                                    <option value="spanish" <?php selected( $options['summary_language'] ?? 'english', 'spanish' ); ?>>Spanish</option>
                                    <option value="french" <?php selected( $options['summary_language'] ?? 'english', 'french' ); ?>>French</option>
                                    <option value="german" <?php selected( $options['summary_language'] ?? 'english', 'german' ); ?>>German</option>
                                    <option value="italian" <?php selected( $options['summary_language'] ?? 'english', 'italian' ); ?>>Italian</option>
                                    <option value="portuguese" <?php selected( $options['summary_language'] ?? 'english', 'portuguese' ); ?>>Portuguese</option>
                                    <option value="dutch" <?php selected( $options['summary_language'] ?? 'english', 'dutch' ); ?>>Dutch</option>
                                    <option value="russian" <?php selected( $options['summary_language'] ?? 'english', 'russian' ); ?>>Russian</option>
                                    <option value="chinese" <?php selected( $options['summary_language'] ?? 'english', 'chinese' ); ?>>Chinese (Simplified)</option>
                                    <option value="japanese" <?php selected( $options['summary_language'] ?? 'english', 'japanese' ); ?>>Japanese</option>
                                    <option value="korean" <?php selected( $options['summary_language'] ?? 'english', 'korean' ); ?>>Korean</option>
                                    <option value="arabic" <?php selected( $options['summary_language'] ?? 'english', 'arabic' ); ?>>Arabic</option>
                                    <option value="turkish" <?php selected( $options['summary_language'] ?? 'english', 'turkish' ); ?>>Turkish</option>
                                    <option value="hindi" <?php selected( $options['summary_language'] ?? 'english', 'hindi' ); ?>>Hindi</option>
                                </select>
                                <p class="description">Force the AI to generate summaries in this language.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[summary_prompt]">Summary Prompt</label></th>
                            <td>
                                <textarea name="ai_voice_settings[summary_prompt]" rows="4" cols="50" class="large-text"><?php echo esc_textarea( $options['summary_prompt'] ?? 'Create 3-5 key takeaways from this article. Each takeaway should be 1-2 lines maximum. Focus on the most important insights and actionable information.' ); ?></textarea>
                                <p class="description">The prompt sent to the AI model to generate summaries. Keep it concise for best results.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2 class="title">Default Player Settings</h2>
                <p>These are the default settings for the audio player. They can be overridden for individual posts.</p>
                 <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[enable_globally]">Enable Player Globally</label></th>
                            <td><input type="checkbox" name="ai_voice_settings[enable_globally]" value="1" <?php checked( isset($options['enable_globally']) ? $options['enable_globally'] : 0, 1 ); ?>> <span class="description">Enable the audio player on all posts by default.</span></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[disabled_categories]">Disable for Categories</label></th>
                            <td>
                                <?php
                                $disabled_categories = $options['disabled_categories'] ?? array();
                                $categories = get_categories(array('hide_empty' => false));
                                
                                echo '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">';
                                foreach ($categories as $category) {
                                    $checked = in_array($category->term_id, (array)$disabled_categories) ? 'checked' : '';
                                    echo '<label style="display: block; margin-bottom: 5px;">';
                                    echo '<input type="checkbox" name="ai_voice_settings[disabled_categories][]" value="' . esc_attr($category->term_id) . '" ' . $checked . '> ';
                                    echo esc_html($category->name) . ' (' . $category->count . ' posts)';
                                    echo '</label>';
                                }
                                echo '</div>';
                                ?>
                                <p class="description">Select categories where the audio player should be disabled. This will affect all posts in these categories and their child categories.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[generation_method]">Generation Method</label></th>
                            <td>
                                <select name="ai_voice_settings[generation_method]">
                                    <option value="chunked" <?php selected( $options['generation_method'] ?? 'chunked', 'chunked' ); ?>>Reliable (Chunking)</option>
                                    <option value="single" <?php selected( $options['generation_method'] ?? 'chunked', 'single' ); ?>>Fast (Single Request)</option>
                                </select>
                                <p class="description">"Reliable" is recommended for most servers to avoid timeouts. "Fast" is quicker but may fail on some web hosts.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[default_ai]">Default AI Service</label></th>
                            <td>
                                <select id="ai_voice_default_ai_service" name="ai_voice_settings[default_ai]">
                                    <option value="google" <?php selected( $options['default_ai'] ?? 'google', 'google' ); ?>>Google Cloud TTS</option>
                                    <option value="gemini" <?php selected( $options['default_ai'] ?? 'google', 'gemini' ); ?>>Google AI (Gemini)</option>
                                    <option value="openai" <?php selected( $options['default_ai'] ?? 'google', 'openai' ); ?>>OpenAI</option>
                                    <option value="local" <?php selected( $options['default_ai'] ?? 'google', 'local' ); ?>>Local Edge TTS</option>
                                </select>
                            </td>
                        </tr>
                        
                        <!-- Edge TTS Settings -->
                        <tr class="ai-voice-setting-row" data-service="local">
                            <th scope="row"><label for="ai_voice_settings[local_tts_url]">Edge TTS Server URL</label></th>
                            <td>
                                <input type="text" name="ai_voice_settings[local_tts_url]" id="local_tts_url" value="<?php echo esc_attr( $options['local_tts_url'] ?? 'http://localhost:6000/synthesize' ); ?>" class="regular-text">
                                <p class="description">URL of your Edge TTS server (default: http://localhost:6000/synthesize)</p>
                            </td>
                        </tr>
                        
                        <tr class="ai-voice-setting-row" data-service="local">
                            <th scope="row"><label for="ai_voice_settings[edge_language]">Default Language</label></th>
                            <td>
                                <select name="ai_voice_settings[edge_language]" id="edge_language" class="regular-text">
                                    <option value="en" <?php selected( $options['edge_language'] ?? 'en', 'en' ); ?>>English</option>
                                    <option value="ar" <?php selected( $options['edge_language'] ?? 'en', 'ar' ); ?>>Arabic (العربية)</option>
                                    <option value="bg" <?php selected( $options['edge_language'] ?? 'en', 'bg' ); ?>>Bulgarian (Български)</option>
                                    <option value="ca" <?php selected( $options['edge_language'] ?? 'en', 'ca' ); ?>>Catalan (Català)</option>
                                    <option value="cs" <?php selected( $options['edge_language'] ?? 'en', 'cs' ); ?>>Czech (Čeština)</option>
                                    <option value="da" <?php selected( $options['edge_language'] ?? 'en', 'da' ); ?>>Danish (Dansk)</option>
                                    <option value="de" <?php selected( $options['edge_language'] ?? 'en', 'de' ); ?>>German (Deutsch)</option>
                                    <option value="el" <?php selected( $options['edge_language'] ?? 'en', 'el' ); ?>>Greek (Ελληνικά)</option>
                                    <option value="es" <?php selected( $options['edge_language'] ?? 'en', 'es' ); ?>>Spanish (Español)</option>
                                    <option value="fi" <?php selected( $options['edge_language'] ?? 'en', 'fi' ); ?>>Finnish (Suomi)</option>
                                    <option value="fr" <?php selected( $options['edge_language'] ?? 'en', 'fr' ); ?>>French (Français)</option>
                                    <option value="he" <?php selected( $options['edge_language'] ?? 'en', 'he' ); ?>>Hebrew (עברית)</option>
                                    <option value="hi" <?php selected( $options['edge_language'] ?? 'en', 'hi' ); ?>>Hindi (हिन्दी)</option>
                                    <option value="hr" <?php selected( $options['edge_language'] ?? 'en', 'hr' ); ?>>Croatian (Hrvatski)</option>
                                    <option value="hu" <?php selected( $options['edge_language'] ?? 'en', 'hu' ); ?>>Hungarian (Magyar)</option>
                                    <option value="id" <?php selected( $options['edge_language'] ?? 'en', 'id' ); ?>>Indonesian (Bahasa Indonesia)</option>
                                    <option value="it" <?php selected( $options['edge_language'] ?? 'en', 'it' ); ?>>Italian (Italiano)</option>
                                    <option value="ja" <?php selected( $options['edge_language'] ?? 'en', 'ja' ); ?>>Japanese (日本語)</option>
                                    <option value="ko" <?php selected( $options['edge_language'] ?? 'en', 'ko' ); ?>>Korean (한국어)</option>
                                    <option value="nl" <?php selected( $options['edge_language'] ?? 'en', 'nl' ); ?>>Dutch (Nederlands)</option>
                                    <option value="no" <?php selected( $options['edge_language'] ?? 'en', 'no' ); ?>>Norwegian (Norsk)</option>
                                    <option value="pl" <?php selected( $options['edge_language'] ?? 'en', 'pl' ); ?>>Polish (Polski)</option>
                                    <option value="pt" <?php selected( $options['edge_language'] ?? 'en', 'pt' ); ?>>Portuguese (Português)</option>
                                    <option value="ro" <?php selected( $options['edge_language'] ?? 'en', 'ro' ); ?>>Romanian (Română)</option>
                                    <option value="ru" <?php selected( $options['edge_language'] ?? 'en', 'ru' ); ?>>Russian (Русский)</option>
                                    <option value="sk" <?php selected( $options['edge_language'] ?? 'en', 'sk' ); ?>>Slovak (Slovenčina)</option>
                                    <option value="sl" <?php selected( $options['edge_language'] ?? 'en', 'sl' ); ?>>Slovenian (Slovenščina)</option>
                                    <option value="sv" <?php selected( $options['edge_language'] ?? 'en', 'sv' ); ?>>Swedish (Svenska)</option>
                                    <option value="th" <?php selected( $options['edge_language'] ?? 'en', 'th' ); ?>>Thai (ไทย)</option>
                                    <option value="tr" <?php selected( $options['edge_language'] ?? 'en', 'tr' ); ?>>Turkish (Türkçe)</option>
                                    <option value="uk" <?php selected( $options['edge_language'] ?? 'en', 'uk' ); ?>>Ukrainian (Українська)</option>
                                    <option value="vi" <?php selected( $options['edge_language'] ?? 'en', 'vi' ); ?>>Vietnamese (Tiếng Việt)</option>
                                    <option value="zh" <?php selected( $options['edge_language'] ?? 'en', 'zh' ); ?>>Chinese (中文)</option>
                                </select>
                                <p class="description">Select the default language for Edge TTS voices</p>
                            </td>
                        </tr>
                        
                        <tr class="ai-voice-setting-row" data-service="local">
                            <th scope="row"><label for="ai_voice_settings[edge_voice]">Default Voice</label></th>
                            <td>
                                <select name="ai_voice_settings[edge_voice]" id="edge_voice" class="regular-text">
                                    <option value="en-US-JennyNeural" <?php selected( $options['edge_voice'] ?? 'en-US-JennyNeural', 'en-US-JennyNeural' ); ?>>Jenny (Female, US)</option>
                                    <option value="en-US-GuyNeural" <?php selected( $options['edge_voice'] ?? 'en-US-JennyNeural', 'en-US-GuyNeural' ); ?>>Guy (Male, US)</option>
                                    <option value="en-US-AriaNeural" <?php selected( $options['edge_voice'] ?? 'en-US-JennyNeural', 'en-US-AriaNeural' ); ?>>Aria (Female, US)</option>
                                    <option value="el-GR-AthinaNeural" <?php selected( $options['edge_voice'] ?? 'en-US-JennyNeural', 'el-GR-AthinaNeural' ); ?>>Athina (Female, Greek)</option>
                                    <option value="el-GR-NestorasNeural" <?php selected( $options['edge_voice'] ?? 'en-US-JennyNeural', 'el-GR-NestorasNeural' ); ?>>Nestoras (Male, Greek)</option>
                                    <option value="ar-SA-ZariyahNeural" <?php selected( $options['edge_voice'] ?? 'en-US-JennyNeural', 'ar-SA-ZariyahNeural' ); ?>>Zariyah (Female, Arabic)</option>
                                    <option value="ar-SA-HamedNeural" <?php selected( $options['edge_voice'] ?? 'en-US-JennyNeural', 'ar-SA-HamedNeural' ); ?>>Hamed (Male, Arabic)</option>
                                    <option value="tr-TR-EmelNeural" <?php selected( $options['edge_voice'] ?? 'en-US-JennyNeural', 'tr-TR-EmelNeural' ); ?>>Emel (Female, Turkish)</option>
                                    <option value="tr-TR-AhmetNeural" <?php selected( $options['edge_voice'] ?? 'en-US-JennyNeural', 'tr-TR-AhmetNeural' ); ?>>Ahmet (Male, Turkish)</option>
                                </select>
                                <p class="description">Select the default voice. Change language above to see voices for that language.</p>
                                <button type="button" id="fetch_edge_voices" class="button" style="margin-top: 10px;">Refresh Voices from Server</button>
                            </td>
                        </tr>
                        
                        <tr class="ai-voice-setting-row" data-service="gemini">
                            <th scope="row"><label for="ai_voice_settings[gemini_tone]">Default Gemini Tone</label></th>
                            <td>
                                <select name="ai_voice_settings[gemini_tone]">
                                     <option value="neutral" <?php selected( $options['gemini_tone'] ?? 'neutral', 'neutral' ); ?>>Neutral / Standard</option>
                                     <option value="newscaster" <?php selected( $options['gemini_tone'] ?? 'neutral', 'newscaster' ); ?>>Professional Newscaster</option>
                                     <option value="conversational" <?php selected( $options['gemini_tone'] ?? 'neutral', 'conversational' ); ?>>Casual Conversational</option>
                                     <option value="calm" <?php selected( $options['gemini_tone'] ?? 'neutral', 'calm' ); ?>>Calm & Soothing</option>
                                </select>
                                <p class="description">Select the default speaking style for Gemini voices.</p>
                            </td>
                        </tr>
                        <tr class="ai-voice-setting-row" data-service="google">
                            <th scope="row"><label for="ai_voice_settings[google_voice]">Default Google Voice</label></th>
                            <td>
                                <select name="ai_voice_settings[google_voice]">
                                    <?php
                                    $current_google_voice = $options['google_voice'] ?? 'en-US-Studio-O';
                                    foreach ($google_voices as $group => $voices) {
                                        echo '<optgroup label="' . esc_attr($group) . '">';
                                        foreach ($voices as $id => $name) {
                                            echo '<option value="' . esc_attr($id) . '" ' . selected($current_google_voice, $id, false) . '>' . esc_html($name) . '</option>';
                                        }
                                        echo '</optgroup>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                         <tr class="ai-voice-setting-row" data-service="gemini">
                            <th scope="row"><label for="ai_voice_settings[gemini_voice]">Default Gemini Voice</label></th>
                            <td>
                                <select name="ai_voice_settings[gemini_voice]">
                                     <option value="Kore" <?php selected( $options['gemini_voice'] ?? 'Kore', 'Kore' ); ?>>Kore (Firm)</option>
                                     <option value="Puck" <?php selected( $options['gemini_voice'] ?? 'Kore', 'Puck' ); ?>>Puck (Upbeat)</option>
                                     <option value="Charon" <?php selected( $options['gemini_voice'] ?? 'Kore', 'Charon' ); ?>>Charon (Informative)</option>
                                     <option value="Leda" <?php selected( $options['gemini_voice'] ?? 'Kore', 'Leda' ); ?>>Leda (Youthful)</option>
                                     <option value="Enceladus" <?php selected( $options['gemini_voice'] ?? 'Kore', 'Enceladus' ); ?>>Enceladus (Breathy)</option>
                                </select>
                            </td>
                        </tr>
                         <tr class="ai-voice-setting-row" data-service="openai">
                            <th scope="row"><label for="ai_voice_settings[openai_voice]">Default OpenAI Voice</label></th>
                            <td>
                                <select name="ai_voice_settings[openai_voice]">
                                     <option value="alloy" <?php selected( $options['openai_voice'] ?? 'alloy', 'alloy' ); ?>>Alloy</option>
                                    <option value="echo" <?php selected( $options['openai_voice'] ?? 'alloy', 'echo' ); ?>>Echo</option>
                                    <option value="fable" <?php selected( $options['openai_voice'] ?? 'alloy', 'fable' ); ?>>Fable</option>
                                    <option value="onyx" <?php selected( $options['openai_voice'] ?? 'alloy', 'onyx' ); ?>>Onyx</option>
                                    <option value="nova" <?php selected( $options['openai_voice'] ?? 'alloy', 'nova' ); ?>>Nova</option>
                                    <option value="shimmer" <?php selected( $options['openai_voice'] ?? 'alloy', 'shimmer' ); ?>>Shimmer</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[theme]">Default Theme</label></th>
                            <td>
                                <select name="ai_voice_settings[theme]">
                                    <option value="light" <?php selected( $options['theme'] ?? 'light', 'light' ); ?>>Light</option>
                                    <option value="dark" <?php selected( $options['theme'] ?? 'light', 'dark' ); ?>>Dark</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2 class="title">Color Customization</h2>
                <table class="form-table">
                    <thead><tr><th></th><th>Light Theme</th><th>Dark Theme</th></tr></thead>
                     <tbody>
                        <tr>
                            <th scope="row">Background</th>
                            <td><input type="color" name="ai_voice_settings[bg_color_light]" value="<?php echo esc_attr( $options['bg_color_light'] ?? '#ffffff' ); ?>"></td>
                            <td><input type="color" name="ai_voice_settings[bg_color_dark]" value="<?php echo esc_attr( $options['bg_color_dark'] ?? '#1e293b' ); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row">Primary Text</th>
                            <td><input type="color" name="ai_voice_settings[text_color_light]" value="<?php echo esc_attr( $options['text_color_light'] ?? '#0f172a' ); ?>"></td>
                            <td><input type="color" name="ai_voice_settings[text_color_dark]" value="<?php echo esc_attr( $options['text_color_dark'] ?? '#f1f5f9' ); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row">Accent / Links</th>
                            <td><input type="color" name="ai_voice_settings[accent_color_light]" value="<?php echo esc_attr( $options['accent_color_light'] ?? '#3b82f6' ); ?>"></td>
                            <td><input type="color" name="ai_voice_settings[accent_color_dark]" value="<?php echo esc_attr( $options['accent_color_dark'] ?? '#60a5fa' ); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row">Summary Button</th>
                            <td><input type="color" name="ai_voice_settings[summary_color_light]" value="<?php echo esc_attr( $options['summary_color_light'] ?? '#6b7280' ); ?>"></td>
                            <td><input type="color" name="ai_voice_settings[summary_color_dark]" value="<?php echo esc_attr( $options['summary_color_dark'] ?? '#9ca3af' ); ?>"></td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(); ?>
            </form>

            <?php
            if ( function_exists( 'ai_voice_render_purge_box' ) ) {
                ai_voice_render_purge_box();
            }
            ?>
        </div>
        <?php
    }

    public function get_google_voices() {
        return [
            'English (US) - Premium' => [
                'en-US-Studio-O' => 'Female (Studio)',
                'en-US-Studio-M' => 'Male (Studio)',
                'en-US-Journey-D' => 'Male (Journey)',
                'en-US-Journey-F' => 'Female (Journey)',
            ],
            'English (US) - High Quality' => [
                'en-US-Neural2-A' => 'Male A (Neural2)',
                'en-US-Neural2-C' => 'Female C (Neural2)',
                'en-US-Neural2-D' => 'Male D (Neural2)',
                'en-US-Neural2-F' => 'Female F (Neural2)',
                'en-US-Neural2-G' => 'Female G (Neural2)',
                'en-US-Neural2-H' => 'Female H (Neural2)',
                'en-US-Neural2-I' => 'Male I (Neural2)',
                'en-US-Neural2-J' => 'Male J (Neural2)',
            ],
            'English (UK) - Premium' => [
                'en-GB-Studio-B' => 'Male (Studio)',
                'en-GB-Studio-C' => 'Female (Studio)',
                'en-GB-Neural2-A' => 'Female A (Neural2)',
                'en-GB-Neural2-B' => 'Male B (Neural2)',
                'en-GB-Neural2-C' => 'Female C (Neural2)',
                'en-GB-Neural2-D' => 'Male D (Neural2)',
                'en-GB-Neural2-F' => 'Female F (Neural2)',
            ],
            'English (Australia)' => [
                'en-AU-Neural2-A' => 'Female A (Neural2)',
                'en-AU-Neural2-B' => 'Male B (Neural2)',
                'en-AU-Neural2-C' => 'Female C (Neural2)',
                'en-AU-Neural2-D' => 'Male D (Neural2)',
            ],
            'French (France)' => [
                'fr-FR-Neural2-A' => 'Female A',
                'fr-FR-Neural2-B' => 'Male B',
                'fr-FR-Neural2-C' => 'Female C',
                'fr-FR-Neural2-D' => 'Male D',
            ],
            'German (Germany)' => [
                'de-DE-Neural2-B' => 'Male B',
                'de-DE-Neural2-C' => 'Female C',
                'de-DE-Neural2-D' => 'Male D',
                'de-DE-Neural2-F' => 'Female F',
            ],
            'Spanish (Spain)' => [
                'es-ES-Neural2-A' => 'Female A',
                'es-ES-Neural2-B' => 'Male B',
                'es-ES-Neural2-C' => 'Female C',
                'es-ES-Neural2-D' => 'Male D',
                'es-ES-Neural2-F' => 'Female F',
            ],
             'Portuguese (Brazil)' => [
                'pt-BR-Neural2-A' => 'Female A',
                'pt-BR-Neural2-B' => 'Male B',
                'pt-BR-Neural2-C' => 'Female C',
            ],
            'Italian (Italy)' => [
                'it-IT-Neural2-A' => 'Female A',
                'it-IT-Neural2-C' => 'Male C',
            ]
        ];
    }

    public function get_chatgpt_models() {
        return [
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Fast & Affordable)',
            'gpt-4' => 'GPT-4 (High Quality)',
            'gpt-4-turbo-preview' => 'GPT-4 Turbo (Latest)',
        ];
    }
}