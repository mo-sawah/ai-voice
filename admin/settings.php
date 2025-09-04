<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class AIVoice_Settings {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_ai-voice') {
            return;
        }
        wp_enqueue_script('ai-voice-admin-js', AI_VOICE_PLUGIN_URL . 'admin/assets/js/settings.js', ['jquery'], AI_VOICE_VERSION, true);
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
        $openrouter_models = $this->get_openrouter_models();
        $chatgpt_models = $this->get_chatgpt_models();
        ?>
        <div class="wrap">
            <h1>AI Voice Settings</h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'ai_voice_group' );
                ?>
                
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
                                </select>
                                <p class="description">OpenRouter provides access to multiple AI models. ChatGPT uses OpenAI's API directly.</p>
                            </td>
                        </tr>
                        <tr class="ai-voice-summary-row" data-api="openrouter">
                            <th scope="row"><label for="ai_voice_settings[summary_model]">OpenRouter Model</label></th>
                            <td>
                                <select name="ai_voice_settings[summary_model]">
                                    <?php
                                    $current_model = $options['summary_model'] ?? 'anthropic/claude-3-haiku';
                                    foreach ($openrouter_models as $category => $models) {
                                        echo '<optgroup label="' . esc_attr($category) . '">';
                                        foreach ($models as $model_id => $model_name) {
                                            echo '<option value="' . esc_attr($model_id) . '" ' . selected($current_model, $model_id, false) . '>' . esc_html($model_name) . '</option>';
                                        }
                                        echo '</optgroup>';
                                    }
                                    ?>
                                </select>
                                <p class="description">Claude and GPT models work best for generating concise takeaways.</p>
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
                                </select>
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

    public function get_openrouter_models() {
        return [
            'Anthropic (Recommended)' => [
                'anthropic/claude-3-haiku' => 'Claude 3 Haiku (Fast & Affordable)',
                'anthropic/claude-3-sonnet' => 'Claude 3 Sonnet (Balanced)',
                'anthropic/claude-3-opus' => 'Claude 3 Opus (Highest Quality)',
            ],
            'OpenAI' => [
                'openai/gpt-3.5-turbo' => 'GPT-3.5 Turbo (Fast)',
                'openai/gpt-4' => 'GPT-4 (High Quality)',
                'openai/gpt-4-turbo' => 'GPT-4 Turbo (Latest)',
            ],
            'Meta' => [
                'meta-llama/llama-2-70b-chat' => 'Llama 2 70B (Open Source)',
            ],
            'Google' => [
                'google/gemini-pro' => 'Gemini Pro',
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