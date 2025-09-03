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

    public function add_meta_box( $post_type ) {
        // Limit meta box to certain post types
        $post_types = [ 'post', 'page' ];
        if ( in_array( $post_type, $post_types ) ) {
            add_meta_box(
                'ai_voice_metabox',
                'AI Voice Player Settings',
                [ $this, 'render_meta_box_content' ],
                $post_type,
                'side',
                'low'
            );
        }
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
                <?php
                $voices = ['en-US-Wavenet-F' => 'Aria (Female)', 'en-US-Wavenet-D' => 'Leo (Male)', 'en-GB-Wavenet-A' => 'Amelia (Female, UK)'];
                foreach($voices as $id => $name) {
                    echo '<option value="' . esc_attr($id) . '"' . selected( $google_voice, $id, false ) . '>' . esc_html($name) . '</option>';
                }
                ?>
            </select>
        </p>
         <p>
            <label for="ai_voice_openai_voice"><strong>OpenAI Voice</strong></label><br>
             <select name="ai_voice_openai_voice" id="ai_voice_openai_voice" style="width:100%;">
                <option value="default" <?php selected( $openai_voice, 'default' ); ?>>Use Global Setting</option>
                <?php
                $voices = ['alloy' => 'Alloy', 'echo' => 'Echo', 'fable' => 'Fable', 'onyx' => 'Onyx', 'nova' => 'Nova', 'shimmer' => 'Shimmer'];
                foreach($voices as $id => $name) {
                    echo '<option value="' . esc_attr($id) . '"' . selected( $openai_voice, $id, false ) . '>' . esc_html($name) . '</option>';
                }
                ?>
            </select>
        </p>
        <p>
            <label for="ai_voice_theme"><strong>Theme</strong></label><br>
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

        $fields = ['ai_voice_status', 'ai_voice_ai_service', 'ai_voice_google_voice', 'ai_voice_openai_voice', 'ai_voice_theme'];

        foreach ($fields as $field) {
            if ( isset( $_POST[$field] ) ) {
                update_post_meta( $post_id, '_' . $field, sanitize_text_field( $_POST[$field] ) );
            }
        }
    }
}
