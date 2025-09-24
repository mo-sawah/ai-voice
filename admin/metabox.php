<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class AIVoice_Metabox {

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
        add_action( 'save_post', [ $this, 'save_meta_box_data' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }

    public function enqueue_admin_scripts($hook) {
        if ( in_array($hook, ['post.php', 'post-new.php']) ) {
            wp_enqueue_script('ai-voice-admin-js', AI_VOICE_PLUGIN_URL . 'admin/assets/js/settings.js', ['jquery'], AI_VOICE_VERSION, true);
        }
    }

    public function add_meta_box() {
        add_meta_box(
            'ai_voice_settings_metabox',
            'AI Voice Player Settings',
            [ $this, 'render_meta_box_content' ],
            ['post', 'page'],
            'side',
            'high'
        );
    }

    public function render_meta_box_content( $post ) {
        wp_nonce_field( 'ai_voice_save_meta_box_data', 'ai_voice_meta_box_nonce' );

        $status = get_post_meta( $post->ID, '_ai_voice_status', true );
        $ai_service = get_post_meta( $post->ID, '_ai_voice_ai_service', true );
        $gemini_tone = get_post_meta( $post->ID, '_ai_voice_gemini_tone', true );
        $google_voice = get_post_meta( $post->ID, '_ai_voice_google_voice', true );
        $gemini_voice = get_post_meta( $post->ID, '_ai_voice_gemini_voice', true );
        $openai_voice = get_post_meta( $post->ID, '_ai_voice_openai_voice', true );
        $theme = get_post_meta( $post->ID, '_ai_voice_theme', true );
        ?>
        <p>
            <label for="ai_voice_status"><strong>Player Status</strong></label><br>
            <select name="ai_voice_status" id="ai_voice_status" style="width:100%;">
                <option value="default" <?php selected( $status, 'default' ); ?>>Use Global Setting</option>
                <option value="enabled" <?php selected( $status, 'enabled' ); ?>>Enabled</option>
                <option value="disabled" <?php selected( $status, 'disabled' ); ?>>Disabled</option>
            </select>
        </p>
        <p>
            <label for="ai_voice_ai_service"><strong>AI Service</strong></label><br>
            <select name="ai_voice_ai_service" id="ai_voice_ai_service" style="width:100%;">
                <option value="default" <?php selected( $ai_service, 'default' ); ?>>Use Global Setting</option>
                <option value="google" <?php selected( $ai_service, 'google' ); ?>>Google Cloud TTS</option>
                <option value="gemini" <?php selected( $ai_service, 'gemini' ); ?>>Google AI (Gemini)</option>
                <option value="openai" <?php selected( $ai_service, 'openai' ); ?>>OpenAI</option>
                <option value="local" <?php selected( $ai_service, 'local' ); ?>>Local TTS (Coqui)</option>
            </select>
        </p>
        <p class="ai-voice-setting-row-postbox" data-service="gemini">
            <label for="ai_voice_gemini_tone"><strong>Gemini Tone</strong></label><br>
            <select name="ai_voice_gemini_tone" id="ai_voice_gemini_tone" style="width:100%;">
                <option value="default" <?php selected( $gemini_tone, 'default' ); ?>>Use Global Setting</option>
                <option value="neutral" <?php selected( $gemini_tone, 'neutral' ); ?>>Neutral / Standard</option>
                <option value="newscaster" <?php selected( $gemini_tone, 'newscaster' ); ?>>Professional Newscaster</option>
                <option value="conversational" <?php selected( $gemini_tone, 'conversational' ); ?>>Casual Conversational</option>
                <option value="calm" <?php selected( $gemini_tone, 'calm' ); ?>>Calm & Soothing</option>
            </select>
        </p>
         <p class="ai-voice-setting-row-postbox" data-service="google">
            <label for="ai_voice_google_voice"><strong>Google Voice</strong></label><br>
            <select name="ai_voice_google_voice" id="ai_voice_google_voice" style="width:100%;">
                <option value="default" <?php selected( $google_voice, 'default' ); ?>>Use Global Setting</option>
                <optgroup label="English (US) - Studio">
                    <option value="en-US-Studio-M" <?php selected($google_voice, 'en-US-Studio-M'); ?>>Male</option>
                    <option value="en-US-Studio-O" <?php selected($google_voice, 'en-US-Studio-O'); ?>>Female</option>
                </optgroup>
            </select>
        </p>
        <p class="ai-voice-setting-row-postbox" data-service="gemini">
            <label for="ai_voice_gemini_voice"><strong>Gemini Voice</strong></label><br>
            <select name="ai_voice_gemini_voice" id="ai_voice_gemini_voice" style="width:100%;">
                <option value="default" <?php selected( $gemini_voice, 'default' ); ?>>Use Global Setting</option>
                <option value="Kore" <?php selected( $gemini_voice, 'Kore' ); ?>>Kore (Firm)</option>
                <option value="Puck" <?php selected( $gemini_voice, 'Puck' ); ?>>Puck (Upbeat)</option>
            </select>
        </p>
        <p class="ai-voice-setting-row-postbox" data-service="openai">
            <label for="ai_voice_openai_voice"><strong>OpenAI Voice</strong></label><br>
            <select name="ai_voice_openai_voice" id="ai_voice_openai_voice" style="width:100%;">
                <option value="default" <?php selected( $openai_voice, 'default' ); ?>>Use Global Setting</option>
                <option value="alloy" <?php selected( $openai_voice, 'alloy' ); ?>>Alloy</option>
                <option value="nova" <?php selected( $openai_voice, 'nova' ); ?>>Nova</option>
            </select>
        </p>
        <p>
            <label for="ai_voice_theme"><strong>Player Theme</strong></label><br>
            <select name="ai_voice_theme" id="ai_voice_theme" style="width:100%;">
                <option value="default" <?php selected( $theme, 'default' ); ?>>Use Global Setting</option>
                <option value="light" <?php selected( $theme, 'light' ); ?>>Light</option>
                <option value="dark" <?php selected( $theme, 'dark' ); ?>>Dark</option>
            </select>
        </p>
        <?php
    }

    public function save_meta_box_data( $post_id ) {
        if ( ! isset( $_POST['ai_voice_meta_box_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['ai_voice_meta_box_nonce'], 'ai_voice_save_meta_box_data' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $fields = [ 'ai_voice_status', 'ai_voice_ai_service', 'ai_voice_gemini_tone', 'ai_voice_google_voice', 'ai_voice_gemini_voice', 'ai_voice_openai_voice', 'ai_voice_theme' ];
        foreach ($fields as $field) {
            if ( array_key_exists( $field, $_POST ) ) {
                update_post_meta( $post_id, '_' . $field, sanitize_text_field( $_POST[ $field ] ) );
            }
        }
        
        // When settings are changed, clear all related audio caches
        $meta_keys = get_post_meta($post_id, '', true);
        foreach ($meta_keys as $key => $value) {
            if (strpos($key, '_ai_voice_audio_url_') === 0) {
                delete_post_meta($post_id, $key);
            }
        }
    }
}

