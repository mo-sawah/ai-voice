<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class AIVoice_Settings {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
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
                <p>Enter your API keys from Google Cloud and OpenAI.</p>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="ai_voice_settings[google_api_key]">Google API Key</label></th>
                            <td><input type="text" name="ai_voice_settings[google_api_key]" value="<?php echo esc_attr( $options['google_api_key'] ?? '' ); ?>" class="regular-text"></td>
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
                                <select name="ai_voice_settings[default_ai]">
                                    <option value="google" <?php selected( $options['default_ai'] ?? 'google', 'google' ); ?>>Google</option>
                                    <option value="openai" <?php selected( $options['default_ai'] ?? 'google', 'openai' ); ?>>OpenAI</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
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
                                     <optgroup label="English (UK) - Studio">
                                        <option value="en-GB-Studio-B" <?php selected($options['google_voice'] ?? '', 'en-GB-Studio-B'); ?>>Male</option>
                                        <option value="en-GB-Studio-C" <?php selected($options['google_voice'] ?? '', 'en-GB-Studio-C'); ?>>Female</option>
                                    </optgroup>
                                    <optgroup label="English (UK) - Neural2">
                                        <option value="en-GB-Neural2-B" <?php selected($options['google_voice'] ?? '', 'en-GB-Neural2-B'); ?>>Male</option>
                                        <option value="en-GB-Neural2-C" <?php selected($options['google_voice'] ?? '', 'en-GB-Neural2-C'); ?>>Female</option>
                                    </optgroup>
                                    <optgroup label="English (AU) - Neural2">
                                        <option value="en-AU-Neural2-B" <?php selected($options['google_voice'] ?? '', 'en-AU-Neural2-B'); ?>>Male</option>
                                        <option value="en-AU-Neural2-C" <?php selected($options['google_voice'] ?? '', 'en-AU-Neural2-C'); ?>>Female</option>
                                    </optgroup>
                                </select>
                            </td>
                        </tr>
                         <tr>
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

                <!-- Color Settings remain unchanged -->

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

