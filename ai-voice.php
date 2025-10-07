<?php
/**
 * Plugin Name:       AI Voice
 * Plugin URI:        https://sawahsolutions.com
 * Description:       Generates beautiful, AI-powered audio players for your articles using Google TTS and OpenAI TTS APIs.
 * Version:           3.1.31
 * Author:            Mohamed Sawah
 * Author URI:        https://sawahsolutions.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ai-voice
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'AI_VOICE_VERSION', '3.1.31' );
define( 'AI_VOICE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_VOICE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required files
require_once AI_VOICE_PLUGIN_DIR . 'admin/settings.php';
require_once AI_VOICE_PLUGIN_DIR . 'admin/metabox.php';
require_once AI_VOICE_PLUGIN_DIR . 'public/display.php';

// Auto-generation system
require_once AI_VOICE_PLUGIN_DIR . 'admin/ai-voice-auto-generation.php';

// Bulk generation page
require_once AI_VOICE_PLUGIN_DIR . 'admin/ai-voice-bulk-generation.php';

// Initialize plugin classes
if ( class_exists( 'AIVoice_Settings' ) ) {
	new AIVoice_Settings();
}

if ( class_exists( 'AIVoice_Metabox' ) ) {
	new AIVoice_Metabox();
}

if ( class_exists( 'AIVoice_Public' ) ) {
	new AIVoice_Public();
}

// Initialize auto-generation
if ( class_exists( 'AIVoice_Auto_Generation' ) ) {
    new AIVoice_Auto_Generation();
}

// Initialize bulk generation
if ( class_exists( 'AIVoice_Bulk_Generation' ) ) {
    new AIVoice_Bulk_Generation();
}

if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'admin/ai-voice-purge-tools.php';
}