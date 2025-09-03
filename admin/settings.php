<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class AIVoice_Settings {

    private $options;

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_plugin_page' ] );
        add_action( 'admin_init', [ $this, 'page_init' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    public function enqueue_admin_assets($hook) {
        if ($hook != 'settings_page_ai-voice-settings') {
            return;
        }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_script( 'ai-voice-admin-js', AI_VOICE_PLUGIN_URL . 'admin/admin.js', ['jquery', 'wp-color-picker'], AI_VOICE_VERSION, true );
    }

    public function add_plugin_page() {
        add_options_page(
            'AI Voice Settings',
            'AI Voice',
            'manage_options',
            'ai-voice-settings',
            [ $this, 'create_admin_page' ]
        );
    }

    public function create_admin_page() {
        $this->options = get_option( 'ai_voice_settings' );
        ?>
        <div class="wrap">
            <h1>AI Voice Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'ai_voice_option_group' );
                do_settings_sections( 'ai-voice-setting-admin' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function page_init() {
        register_setting(
            'ai_voice_option_group',
            'ai_voice_settings',
            [ $this, 'sanitize' ]
        );

        // Sections
        add_settings_section('setting_section_api', 'API Keys', null, 'ai-voice-setting-admin');
        add_settings_section('setting_section_general', 'General Settings', null, 'ai-voice-setting-admin');
        add_settings_section('setting_section_appearance', 'Player Appearance', null, 'ai-voice-setting-admin');
        add_settings_section('setting_section_text', 'Player Text', null, 'ai-voice-setting-admin');

        // Fields
        $this->add_fields();
    }
    
    public function sanitize( $input ) {
		// A real plugin should have more robust sanitization.
        return $input;
    }

    private function add_fields() {
        add_settings_field('google_api_key', 'Google TTS API Key', [$this, 'google_api_key_callback'], 'ai-voice-setting-admin', 'setting_section_api');
        add_settings_field('openai_api_key', 'OpenAI API Key', [$this, 'openai_api_key_callback'], 'ai-voice-setting-admin', 'setting_section_api');
        
        add_settings_field('enable_globally', 'Enable Player on All Posts', [$this, 'enable_globally_callback'], 'ai-voice-setting-admin', 'setting_section_general');
        add_settings_field('default_ai', 'Default AI Service', [$this, 'default_ai_callback'], 'ai-voice-setting-admin', 'setting_section_general');
        add_settings_field('google_voice', 'Default Google Voice', [$this, 'google_voice_callback'], 'ai-voice-setting-admin', 'setting_section_general');
        add_settings_field('openai_voice', 'Default OpenAI Voice', [$this, 'openai_voice_callback'], 'ai-voice-setting-admin', 'setting_section_general');

        add_settings_field('theme', 'Default Theme', [$this, 'theme_callback'], 'ai-voice-setting-admin', 'setting_section_appearance');
        add_settings_field('bg_color_light', 'Background (Light)', [$this, 'color_picker_callback'], 'ai-voice-setting-admin', 'setting_section_appearance', ['id' => 'bg_color_light', 'default' => '#ffffff']);
        add_settings_field('text_color_light', 'Text (Light)', [$this, 'color_picker_callback'], 'ai-voice-setting-admin', 'setting_section_appearance', ['id' => 'text_color_light', 'default' => '#0f172a']);
        add_settings_field('accent_color_light', 'Accent (Light)', [$this, 'color_picker_callback'], 'ai-voice-setting-admin', 'setting_section_appearance', ['id' => 'accent_color_light', 'default' => '#3b82f6']);
        add_settings_field('bg_color_dark', 'Background (Dark)', [$this, 'color_picker_callback'], 'ai-voice-setting-admin', 'setting_section_appearance', ['id' => 'bg_color_dark', 'default' => '#1e293b']);
        add_settings_field('text_color_dark', 'Text (Dark)', [$this, 'color_picker_callback'], 'ai-voice-setting-admin', 'setting_section_appearance', ['id' => 'text_color_dark', 'default' => '#f1f5f9']);
        add_settings_field('accent_color_dark', 'Accent (Dark)', [$this, 'color_picker_callback'], 'ai-voice-setting-admin', 'setting_section_appearance', ['id' => 'accent_color_dark', 'default' => '#60a5fa']);
        
        add_settings_field('article_title_text', 'Article Title', [$this, 'text_callback'], 'ai-voice-setting-admin', 'setting_section_text', ['id' => 'article_title_text', 'default' => 'Listen to this article']);

    }

    public function google_api_key_callback() { $this->text_input('google_api_key', 'password'); }
    public function openai_api_key_callback() { $this->text_input('openai_api_key', 'password'); }
    public function enable_globally_callback() { $this->checkbox_input('enable_globally'); }
    public function text_callback($args) { $this->text_input($args['id'], 'text', $args['default']); }
    public function color_picker_callback($args) { $this->color_picker($args['id'], $args['default']); }

    public function default_ai_callback() {
        $val = isset( $this->options['default_ai'] ) ? $this->options['default_ai'] : 'google';
        echo '<select id="default_ai" name="ai_voice_settings[default_ai]">';
        echo '<option value="google"' . selected( $val, 'google', false ) . '>Google TTS</option>';
        echo '<option value="openai"' . selected( $val, 'openai', false ) . '>OpenAI TTS</option>';
        echo '</select>';
    }

    public function google_voice_callback() {
        // In a real plugin, these would be fetched from the API. Hardcoded for simplicity.
        $voices = ['en-US-Wavenet-F' => 'Aria (Female)', 'en-US-Wavenet-D' => 'Leo (Male)', 'en-GB-Wavenet-A' => 'Amelia (Female, UK)'];
        $val = isset( $this->options['google_voice'] ) ? $this->options['google_voice'] : 'en-US-Wavenet-F';
        echo '<select id="google_voice" name="ai_voice_settings[google_voice]">';
        foreach($voices as $id => $name) {
            echo '<option value="' . esc_attr($id) . '"' . selected( $val, $id, false ) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
    }

    public function openai_voice_callback() {
        $voices = ['alloy' => 'Alloy', 'echo' => 'Echo', 'fable' => 'Fable', 'onyx' => 'Onyx', 'nova' => 'Nova', 'shimmer' => 'Shimmer'];
        $val = isset( $this->options['openai_voice'] ) ? $this->options['openai_voice'] : 'alloy';
        echo '<select id="openai_voice" name="ai_voice_settings[openai_voice]">';
        foreach($voices as $id => $name) {
            echo '<option value="' . esc_attr($id) . '"' . selected( $val, $id, false ) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
    }

    public function theme_callback() {
        $val = isset( $this->options['theme'] ) ? $this->options['theme'] : 'light';
        echo '<label><input type="radio" name="ai_voice_settings[theme]" value="light"' . checked($val, 'light', false) . '> Light</label><br>';
        echo '<label><input type="radio" name="ai_voice_settings[theme]" value="dark"' . checked($val, 'dark', false) . '> Dark</label>';
    }

    private function text_input($id, $type = 'text', $default = '') {
        $value = isset( $this->options[$id] ) ? esc_attr( $this->options[$id] ) : $default;
        printf('<input type="%s" id="%s" name="ai_voice_settings[%s]" value="%s" class="regular-text" />', $type, $id, $id, $value);
    }
    
    private function checkbox_input($id) {
        $checked = isset( $this->options[$id] ) && $this->options[$id] == '1' ? 'checked' : '';
        printf('<input type="checkbox" id="%s" name="ai_voice_settings[%s]" value="1" %s />', $id, $id, $checked);
    }

    private function color_picker($id, $default) {
        $value = isset( $this->options[$id] ) ? esc_attr( $this->options[$id] ) : $default;
        printf('<input type="text" id="%s" name="ai_voice_settings[%s]" value="%s" class="ai-voice-color-picker" data-default-color="%s" />', $id, $id, $value, $default);
    }
}

// Add JS for the color picker
function ai_voice_admin_inline_js() {
    if (get_current_screen()->id !== 'settings_page_ai-voice-settings') return;
    ?>
    <script>
    jQuery(document).ready(function($){
        $('.ai-voice-color-picker').wpColorPicker();
    });
    </script>
    <?php
}
add_action('admin_footer', 'ai_voice_admin_inline_js');
