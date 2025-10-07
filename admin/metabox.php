<?php
// Complete metabox.php with Edge TTS multi-language support
// Replace your existing metabox.php with this file

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
        
        // Edge TTS settings
        $edge_language = get_post_meta( $post->ID, '_ai_voice_edge_language', true );
        $edge_voice = get_post_meta( $post->ID, '_ai_voice_edge_voice', true );
        
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
                <option value="local" <?php selected( $ai_service, 'local' ); ?>>Local Edge TTS</option>
            </select>
        </p>
        
        <!-- ========================================
            EDGE TTS SETTINGS (Local)
        ========================================= -->
        <p class="ai-voice-setting-row-postbox" data-service="local">
            <label for="ai_voice_edge_language"><strong>Edge TTS Language</strong></label><br>
            <select name="ai_voice_edge_language" id="ai_voice_edge_language" style="width:100%;">
                <option value="default" <?php selected( $edge_language, 'default' ); ?>>Use Global Setting</option>
                <option value="en" <?php selected( $edge_language, 'en' ); ?>>English</option>
                <option value="ar" <?php selected( $edge_language, 'ar' ); ?>>Arabic (العربية)</option>
                <option value="bg" <?php selected( $edge_language, 'bg' ); ?>>Bulgarian (Български)</option>
                <option value="ca" <?php selected( $edge_language, 'ca' ); ?>>Catalan (Català)</option>
                <option value="cs" <?php selected( $edge_language, 'cs' ); ?>>Czech (Čeština)</option>
                <option value="da" <?php selected( $edge_language, 'da' ); ?>>Danish (Dansk)</option>
                <option value="de" <?php selected( $edge_language, 'de' ); ?>>German (Deutsch)</option>
                <option value="el" <?php selected( $edge_language, 'el' ); ?>>Greek (Ελληνικά)</option>
                <option value="es" <?php selected( $edge_language, 'es' ); ?>>Spanish (Español)</option>
                <option value="fi" <?php selected( $edge_language, 'fi' ); ?>>Finnish (Suomi)</option>
                <option value="fr" <?php selected( $edge_language, 'fr' ); ?>>French (Français)</option>
                <option value="he" <?php selected( $edge_language, 'he' ); ?>>Hebrew (עברית)</option>
                <option value="hi" <?php selected( $edge_language, 'hi' ); ?>>Hindi (हिन्दी)</option>
                <option value="hr" <?php selected( $edge_language, 'hr' ); ?>>Croatian (Hrvatski)</option>
                <option value="hu" <?php selected( $edge_language, 'hu' ); ?>>Hungarian (Magyar)</option>
                <option value="id" <?php selected( $edge_language, 'id' ); ?>>Indonesian (Bahasa Indonesia)</option>
                <option value="it" <?php selected( $edge_language, 'it' ); ?>>Italian (Italiano)</option>
                <option value="ja" <?php selected( $edge_language, 'ja' ); ?>>Japanese (日本語)</option>
                <option value="ko" <?php selected( $edge_language, 'ko' ); ?>>Korean (한국어)</option>
                <option value="nl" <?php selected( $edge_language, 'nl' ); ?>>Dutch (Nederlands)</option>
                <option value="no" <?php selected( $edge_language, 'no' ); ?>>Norwegian (Norsk)</option>
                <option value="pl" <?php selected( $edge_language, 'pl' ); ?>>Polish (Polski)</option>
                <option value="pt" <?php selected( $edge_language, 'pt' ); ?>>Portuguese (Português)</option>
                <option value="ro" <?php selected( $edge_language, 'ro' ); ?>>Romanian (Română)</option>
                <option value="ru" <?php selected( $edge_language, 'ru' ); ?>>Russian (Русский)</option>
                <option value="sk" <?php selected( $edge_language, 'sk' ); ?>>Slovak (Slovenčina)</option>
                <option value="sl" <?php selected( $edge_language, 'sl' ); ?>>Slovenian (Slovenščina)</option>
                <option value="sv" <?php selected( $edge_language, 'sv' ); ?>>Swedish (Svenska)</option>
                <option value="th" <?php selected( $edge_language, 'th' ); ?>>Thai (ไทย)</option>
                <option value="tr" <?php selected( $edge_language, 'tr' ); ?>>Turkish (Türkçe)</option>
                <option value="uk" <?php selected( $edge_language, 'uk' ); ?>>Ukrainian (Українська)</option>
                <option value="vi" <?php selected( $edge_language, 'vi' ); ?>>Vietnamese (Tiếng Việt)</option>
                <option value="zh" <?php selected( $edge_language, 'zh' ); ?>>Chinese (中文)</option>
            </select>
            <span class="description" style="display: block; margin-top: 5px; font-size: 12px; color: #646970;">
                Select a language to see available voices below
            </span>
        </p>
        
        <p class="ai-voice-setting-row-postbox" data-service="local">
            <label for="ai_voice_edge_voice"><strong>Edge TTS Voice</strong></label><br>
            <select name="ai_voice_edge_voice" id="ai_voice_edge_voice" style="width:100%;">
                <option value="default" <?php selected( $edge_voice, 'default' ); ?>>Use Global Setting</option>
                <?php
                // Show some default voices as fallback
                if (empty($edge_language) || $edge_language === 'default' || $edge_language === 'en') {
                    ?>
                    <optgroup label="English (United States)">
                        <option value="en-US-JennyNeural" <?php selected( $edge_voice, 'en-US-JennyNeural' ); ?>>Jenny (Female)</option>
                        <option value="en-US-GuyNeural" <?php selected( $edge_voice, 'en-US-GuyNeural' ); ?>>Guy (Male)</option>
                        <option value="en-US-AriaNeural" <?php selected( $edge_voice, 'en-US-AriaNeural' ); ?>>Aria (Female)</option>
                    </optgroup>
                    <optgroup label="English (United Kingdom)">
                        <option value="en-GB-SoniaNeural" <?php selected( $edge_voice, 'en-GB-SoniaNeural' ); ?>>Sonia (Female)</option>
                        <option value="en-GB-RyanNeural" <?php selected( $edge_voice, 'en-GB-RyanNeural' ); ?>>Ryan (Male)</option>
                    </optgroup>
                    <?php
                } elseif ($edge_language === 'el') {
                    ?>
                    <optgroup label="Greek (Greece)">
                        <option value="el-GR-AthinaNeural" <?php selected( $edge_voice, 'el-GR-AthinaNeural' ); ?>>Athina (Female)</option>
                        <option value="el-GR-NestorasNeural" <?php selected( $edge_voice, 'el-GR-NestorasNeural' ); ?>>Nestoras (Male)</option>
                    </optgroup>
                    <?php
                } elseif ($edge_language === 'ar') {
                    ?>
                    <optgroup label="Arabic (Saudi Arabia)">
                        <option value="ar-SA-ZariyahNeural" <?php selected( $edge_voice, 'ar-SA-ZariyahNeural' ); ?>>Zariyah (Female)</option>
                        <option value="ar-SA-HamedNeural" <?php selected( $edge_voice, 'ar-SA-HamedNeural' ); ?>>Hamed (Male)</option>
                    </optgroup>
                    <?php
                } elseif ($edge_language === 'tr') {
                    ?>
                    <optgroup label="Turkish (Turkey)">
                        <option value="tr-TR-EmelNeural" <?php selected( $edge_voice, 'tr-TR-EmelNeural' ); ?>>Emel (Female)</option>
                        <option value="tr-TR-AhmetNeural" <?php selected( $edge_voice, 'tr-TR-AhmetNeural' ); ?>>Ahmet (Male)</option>
                    </optgroup>
                    <?php
                } elseif ($edge_language === 'es') {
                    ?>
                    <optgroup label="Spanish (Spain)">
                        <option value="es-ES-ElviraNeural" <?php selected( $edge_voice, 'es-ES-ElviraNeural' ); ?>>Elvira (Female)</option>
                        <option value="es-ES-AlvaroNeural" <?php selected( $edge_voice, 'es-ES-AlvaroNeural' ); ?>>Alvaro (Male)</option>
                    </optgroup>
                    <?php
                } elseif ($edge_language === 'fr') {
                    ?>
                    <optgroup label="French (France)">
                        <option value="fr-FR-DeniseNeural" <?php selected( $edge_voice, 'fr-FR-DeniseNeural' ); ?>>Denise (Female)</option>
                        <option value="fr-FR-HenriNeural" <?php selected( $edge_voice, 'fr-FR-HenriNeural' ); ?>>Henri (Male)</option>
                    </optgroup>
                    <?php
                } elseif ($edge_language === 'de') {
                    ?>
                    <optgroup label="German (Germany)">
                        <option value="de-DE-KatjaNeural" <?php selected( $edge_voice, 'de-DE-KatjaNeural' ); ?>>Katja (Female)</option>
                        <option value="de-DE-ConradNeural" <?php selected( $edge_voice, 'de-DE-ConradNeural' ); ?>>Conrad (Male)</option>
                    </optgroup>
                    <?php
                } elseif ($edge_language === 'it') {
                    ?>
                    <optgroup label="Italian (Italy)">
                        <option value="it-IT-ElsaNeural" <?php selected( $edge_voice, 'it-IT-ElsaNeural' ); ?>>Elsa (Female)</option>
                        <option value="it-IT-DiegoNeural" <?php selected( $edge_voice, 'it-IT-DiegoNeural' ); ?>>Diego (Male)</option>
                    </optgroup>
                    <?php
                } elseif ($edge_language === 'pt') {
                    ?>
                    <optgroup label="Portuguese (Brazil)">
                        <option value="pt-BR-FranciscaNeural" <?php selected( $edge_voice, 'pt-BR-FranciscaNeural' ); ?>>Francisca (Female)</option>
                        <option value="pt-BR-AntonioNeural" <?php selected( $edge_voice, 'pt-BR-AntonioNeural' ); ?>>Antonio (Male)</option>
                    </optgroup>
                    <?php
                } elseif ($edge_language === 'ru') {
                    ?>
                    <optgroup label="Russian (Russia)">
                        <option value="ru-RU-SvetlanaNeural" <?php selected( $edge_voice, 'ru-RU-SvetlanaNeural' ); ?>>Svetlana (Female)</option>
                        <option value="ru-RU-DmitryNeural" <?php selected( $edge_voice, 'ru-RU-DmitryNeural' ); ?>>Dmitry (Male)</option>
                    </optgroup>
                    <?php
                } elseif ($edge_language === 'zh') {
                    ?>
                    <optgroup label="Chinese (Simplified)">
                        <option value="zh-CN-XiaoxiaoNeural" <?php selected( $edge_voice, 'zh-CN-XiaoxiaoNeural' ); ?>>Xiaoxiao (Female)</option>
                        <option value="zh-CN-YunxiNeural" <?php selected( $edge_voice, 'zh-CN-YunxiNeural' ); ?>>Yunxi (Male)</option>
                    </optgroup>
                    <?php
                } elseif ($edge_language === 'ja') {
                    ?>
                    <optgroup label="Japanese (Japan)">
                        <option value="ja-JP-NanamiNeural" <?php selected( $edge_voice, 'ja-JP-NanamiNeural' ); ?>>Nanami (Female)</option>
                        <option value="ja-JP-KeitaNeural" <?php selected( $edge_voice, 'ja-JP-KeitaNeural' ); ?>>Keita (Male)</option>
                    </optgroup>
                    <?php
                } elseif ($edge_language === 'ko') {
                    ?>
                    <optgroup label="Korean (Korea)">
                        <option value="ko-KR-SunHiNeural" <?php selected( $edge_voice, 'ko-KR-SunHiNeural' ); ?>>SunHi (Female)</option>
                        <option value="ko-KR-InJoonNeural" <?php selected( $edge_voice, 'ko-KR-InJoonNeural' ); ?>>InJoon (Male)</option>
                    </optgroup>
                    <?php
                } elseif ($edge_language === 'hi') {
                    ?>
                    <optgroup label="Hindi (India)">
                        <option value="hi-IN-SwaraNeural" <?php selected( $edge_voice, 'hi-IN-SwaraNeural' ); ?>>Swara (Female)</option>
                        <option value="hi-IN-MadhurNeural" <?php selected( $edge_voice, 'hi-IN-MadhurNeural' ); ?>>Madhur (Male)</option>
                    </optgroup>
                    <?php
                }
                ?>
            </select>
            <span class="description" style="display: block; margin-top: 5px; font-size: 12px; color: #646970;">
                💡 Voices will load automatically when you select a language. For more voices, visit Settings → AI Voice and click "Refresh Voices from Server"
            </span>
        </p>
        
        <!-- ========================================
            GEMINI SETTINGS
        ========================================= -->
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
        
        <p class="ai-voice-setting-row-postbox" data-service="gemini">
            <label for="ai_voice_gemini_voice"><strong>Gemini Voice</strong></label><br>
            <select name="ai_voice_gemini_voice" id="ai_voice_gemini_voice" style="width:100%;">
                <option value="default" <?php selected( $gemini_voice, 'default' ); ?>>Use Global Setting</option>
                <option value="Kore" <?php selected( $gemini_voice, 'Kore' ); ?>>Kore (Firm)</option>
                <option value="Puck" <?php selected( $gemini_voice, 'Puck' ); ?>>Puck (Upbeat)</option>
                <option value="Charon" <?php selected( $gemini_voice, 'Charon' ); ?>>Charon (Informative)</option>
                <option value="Leda" <?php selected( $gemini_voice, 'Leda' ); ?>>Leda (Youthful)</option>
                <option value="Enceladus" <?php selected( $gemini_voice, 'Enceladus' ); ?>>Enceladus (Breathy)</option>
            </select>
        </p>
        
        <!-- ========================================
            GOOGLE CLOUD TTS SETTINGS
        ========================================= -->
        <p class="ai-voice-setting-row-postbox" data-service="google">
            <label for="ai_voice_google_voice"><strong>Google Voice</strong></label><br>
            <select name="ai_voice_google_voice" id="ai_voice_google_voice" style="width:100%;">
                <option value="default" <?php selected( $google_voice, 'default' ); ?>>Use Global Setting</option>
                <optgroup label="English (US) - Studio">
                    <option value="en-US-Studio-M" <?php selected($google_voice, 'en-US-Studio-M'); ?>>Male</option>
                    <option value="en-US-Studio-O" <?php selected($google_voice, 'en-US-Studio-O'); ?>>Female</option>
                </optgroup>
                <optgroup label="English (US) - Neural2">
                    <option value="en-US-Neural2-A" <?php selected($google_voice, 'en-US-Neural2-A'); ?>>Male A</option>
                    <option value="en-US-Neural2-C" <?php selected($google_voice, 'en-US-Neural2-C'); ?>>Female C</option>
                    <option value="en-US-Neural2-D" <?php selected($google_voice, 'en-US-Neural2-D'); ?>>Male D</option>
                    <option value="en-US-Neural2-F" <?php selected($google_voice, 'en-US-Neural2-F'); ?>>Female F</option>
                    <option value="en-US-Neural2-J" <?php selected($google_voice, 'en-US-Neural2-J'); ?>>Male J</option>
                </optgroup>
                <optgroup label="English (UK)">
                    <option value="en-GB-Neural2-A" <?php selected($google_voice, 'en-GB-Neural2-A'); ?>>Female A</option>
                    <option value="en-GB-Neural2-B" <?php selected($google_voice, 'en-GB-Neural2-B'); ?>>Male B</option>
                </optgroup>
            </select>
        </p>
        
        <!-- ========================================
            OPENAI TTS SETTINGS
        ========================================= -->
        <p class="ai-voice-setting-row-postbox" data-service="openai">
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
        
        <!-- ========================================
            THEME SETTINGS
        ========================================= -->
        <p>
            <label for="ai_voice_theme"><strong>Player Theme</strong></label><br>
            <select name="ai_voice_theme" id="ai_voice_theme" style="width:100%;">
                <option value="default" <?php selected( $theme, 'default' ); ?>>Use Global Setting</option>
                <option value="light" <?php selected( $theme, 'light' ); ?>>Light</option>
                <option value="dark" <?php selected( $theme, 'dark' ); ?>>Dark</option>
            </select>
        </p>
        
        <style>
            /* Hide service-specific settings by default */
            .ai-voice-setting-row-postbox {
                display: none;
            }
            
            /* Metabox styling */
            #ai_voice_settings_metabox .inside label {
                font-weight: 500;
            }
            
            #ai_voice_settings_metabox .inside select {
                margin-top: 5px;
            }
            
            #ai_voice_settings_metabox .inside p {
                margin: 15px 0;
                padding-bottom: 15px;
                border-bottom: 1px solid #f0f0f1;
            }
            
            #ai_voice_settings_metabox .inside p:last-child {
                border-bottom: none;
            }
            
            #ai_voice_settings_metabox .description {
                font-size: 12px;
                color: #646970;
                font-style: italic;
            }
        </style>
        <?php
    }

    public function save_meta_box_data( $post_id ) {
        if ( ! isset( $_POST['ai_voice_meta_box_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['ai_voice_meta_box_nonce'], 'ai_voice_save_meta_box_data' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $fields = [
            'ai_voice_status',
            'ai_voice_ai_service',
            'ai_voice_gemini_tone',
            'ai_voice_google_voice',
            'ai_voice_gemini_voice',
            'ai_voice_openai_voice',
            'ai_voice_edge_language',  // NEW
            'ai_voice_edge_voice',     // NEW
            'ai_voice_theme'
        ];
        
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