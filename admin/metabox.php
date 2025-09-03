<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class AIVoice_Metabox {

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
        add_action( 'save_post', [ $this, 'save_meta_box_data' ] );
    }

    public function add_meta_box() {
        add_meta_box(
            'ai_voice_settings_metabox',
            'AI Voice Player Settings',
            [ $this, 'render_meta_box_content' ],
            ['post', 'page'],
            'side', // Context
            'high'  // Priority: 'high', 'core', 'default' or 'low'
        );
    }

    public function render_meta_box_content( $post ) {
        wp_nonce_field( 'ai_voice_save_meta_box_data', 'ai_voice_meta_box_nonce' );

        $status = get_post_meta( $post->ID, '_ai_voice_status', true );
        $ai_service = get_post_meta( $post->ID, '_ai_voice_ai_service', true );
        $google_voice = get_post_meta( $post->ID, '_ai_voice_google_voice', true );
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
                <option value="google" <?php selected( $ai_service, 'google' ); ?>>Google</option>
                <option value="openai" <?php selected( $ai_service, 'openai' ); ?>>OpenAI</option>
            </select>
        </p>
         <p>
            <label for="ai_voice_google_voice"><strong>Google Voice</strong></label><br>
            <select name="ai_voice_google_voice" id="ai_voice_google_voice" style="width:100%;">
                <option value="default" <?php selected( $google_voice, 'default' ); ?>>Use Global Setting</option>
                <optgroup label="English (US) - Studio">
                    <option value="en-US-Studio-M" <?php selected($google_voice, 'en-US-Studio-M'); ?>>Male</option>
                    <option value="en-US-Studio-O" <?php selected($google_voice, 'en-US-Studio-O'); ?>>Female</option>
                </optgroup>
                <optgroup label="English (US) - Neural2">
                    <option value="en-US-Neural2-J" <?php selected($google_voice, 'en-US-Neural2-J'); ?>>Male 1</option>
                    <option value="en-US-Neural2-A" <?php selected($google_voice, 'en-US-Neural2-A'); ?>>Male 2</option>
                    <option value="en-US-Neural2-I" <?php selected($google_voice, 'en-US-Neural2-I'); ?>>Female 1</option>
                    <option value="en-US-Neural2-C" <?php selected($google_voice, 'en-US-Neural2-C'); ?>>Female 2</option>
                </optgroup>
                <optgroup label="English (US) - WaveNet">
                    <option value="en-US-Wavenet-D" <?php selected($google_voice, 'en-US-Wavenet-D'); ?>>Male 1</option>
                    <option value="en-US-Wavenet-B" <?php selected($google_voice, 'en-US-Wavenet-B'); ?>>Male 2</option>
                    <option value="en-US-Wavenet-F" <?php selected($google_voice, 'en-US-Wavenet-F'); ?>>Female 1</option>
                    <option value="en-US-Wavenet-C" <?php selected($google_voice, 'en-US-Wavenet-C'); ?>>Female 2</option>
                </optgroup>
                 <optgroup label="English (UK) - Studio">
                    <option value="en-GB-Studio-B" <?php selected($google_voice, 'en-GB-Studio-B'); ?>>Male</option>
                    <option value="en-GB-Studio-C" <?php selected($google_voice, 'en-GB-Studio-C'); ?>>Female</option>
                </optgroup>
                <optgroup label="English (UK) - Neural2">
                    <option value="en-GB-Neural2-B" <?php selected($google_voice, 'en-GB-Neural2-B'); ?>>Male</option>
                    <option value="en-GB-Neural2-C" <?php selected($google_voice, 'en-GB-Neural2-C'); ?>>Female</option>
                </optgroup>
                <optgroup label="English (AU) - Neural2">
                    <option value="en-AU-Neural2-B" <?php selected($google_voice, 'en-AU-Neural2-B'); ?>>Male</option>
                    <option value="en-AU-Neural2-C" <?php selected($google_voice, 'en-AU-Neural2-C'); ?>>Female</option>
                </optgroup>
            </select>
        </p>
        <p>
            <label for="ai_voice_openai_voice"><strong>OpenAI Voice</strong></label><br>
            <select name="ai_voice_openai_voice" id="ai_voice_openai_voice" style="width:100%;">
                <option value="default" <?php selected( $openai_voice, 'default' ); ?>>Use Global Setting</option>
                <option value="alloy" <?php selected( $openai_voice, 'alloy' ); ?>>Alloy</option>
                <option value="echo" <?php selected( $openai_voice, 'echo' ); ?>>Echo</option>
                <option value="fable" <?php selected( $openai_voice, 'fable' ); ?>>Fable</option>
                <option value="onyx" <?php selected( $openai_voice, 'onyx' ); ?>>Onyx</option>
                <option value="nova" <?php selected( $openai_voice, 'nova' ); ?>>Nova</option>
                <option value="shimmer" <?php selected( $openai_voice, 'shimmer' ); ?>>Shimmer</option>
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

        $fields = [ 'ai_voice_status', 'ai_voice_ai_service', 'ai_voice_google_voice', 'ai_voice_openai_voice', 'ai_voice_theme' ];
        foreach ($fields as $field) {
            if ( array_key_exists( $field, $_POST ) ) {
                update_post_meta( $post_id, '_' . $field, sanitize_text_field( $_POST[ $field ] ) );
            }
        }
        
        // When settings are changed, clear the content hash to force regeneration
        update_post_meta($post_id, '_ai_voice_content_hash', '');
    }
}

