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
        ?>
        <div class="wrap">
            <h1>AI Voice Settings</h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'ai_voice_group' );
                ?>
                <h2 class="title">API Keys</h2>
                <p>Enter your API keys from Google Cloud, Google AI (Gemini), and OpenAI.</p>
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
                                    <optgroup label="English (US) - Studio">
                                        <option value="en-US-Studio-M" <?php selected($options['google_voice'] ?? '', 'en-US-Studio-M'); ?>>Male</option>
                                        <option value="en-US-Studio-O" <?php selected($options['google_voice'] ?? '', 'en-US-Studio-O'); ?>>Female</option>
                                    </optgroup>
                                    <optgroup label="English (US) - Neural2">
                                        <option value="en-US-Neural2-J" <?php selected($options['google_voice'] ?? '', 'en-US-Neural2-J'); ?>>Male 1</option>
                                        <option value="en-US-Neural2-A" <?php selected($options['google_voice'] ?? '', 'en-US-Neural2-A'); ?>>Male 2</option>
                                        <option value="en-US-Neural2-I" <?php selected($options['google_voice'] ?? '', 'en-US-Neural2-I'); ?>>Female 1</option>
                                        <option value="en-US-Neural2-C" <?php selected($options['google_voice'] ?? '', 'en-US-Neural2-C'); ?>>Female 2</option>
                                    </optgroup>
                                    <optgroup label="English (US) - WaveNet">
                                        <option value="en-US-Wavenet-D" <?php selected($options['google_voice'] ?? 'en-US-Wavenet-D', 'en-US-Wavenet-D'); ?>>Male 1</option>
                                        <option value="en-US-Wavenet-B" <?php selected($options['google_voice'] ?? '', 'en-US-Wavenet-B'); ?>>Male 2</option>
                                        <option value="en-US-Wavenet-F" <?php selected($options['google_voice'] ?? '', 'en-US-Wavenet-F'); ?>>Female 1</option>
                                        <option value="en-US-Wavenet-C" <?php selected($options['google_voice'] ?? '', 'en-US-Wavenet-C'); ?>>Female 2</option>
                                    </optgroup>
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
                    </tbody>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

