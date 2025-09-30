<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}
?>
<div id="ai-voice-player-wrapper">
    <div class="ai-voice-player-container" data-theme="light">
        <div class="player-inner-flex">

            <!-- Summary Button -->
            <button class="summary-button" id="ai-voice-summary-btn" aria-label="<?php echo esc_attr($text_settings['summary_button_label']); ?>">
                <div id="ai-voice-summary-loader" class="loader"></div>
                <img id="ai-voice-summary-icon" class="summary-icon-svg" src="<?php echo AI_VOICE_PLUGIN_URL; ?>public/assets/images/openai.svg" width="24" height="24" alt="Summary">
                <svg id="ai-voice-summary-check" class="summary-check-svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="display: none;">
                    <path d="M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2M11,16.5L18,9.5L16.59,8.09L11,13.67L7.91,10.59L6.5,12L11,16.5Z"/>
                </svg>
            </button>

            <!-- Play/Pause Button -->
            <button class="play-pause-button" id="ai-voice-play-pause-btn" aria-label="<?php echo esc_attr($text_settings['play_pause_label']); ?>">
                <div id="ai-voice-loader" class="loader"></div>
                <svg id="ai-voice-play-icon" class="play-icon-svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M8 5V19L19 12L8 5Z"></path></svg>
                <svg id="ai-voice-pause-icon" class="pause-icon-svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M6 19H10V5H6V19ZM14 5V19H18V5H14Z"></path></svg>
            </button>

            <!-- Main Info and Progress Bar -->
            <div class="info-progress-container">
                <div class="title-logo-flex">
                    <h3 id="ai-voice-article-title" class="article-title">Listen to the article</h3>
                    <div id="ai-voice-logo-container" class="ai-logo-container"></div>
                </div>
                <div class="progress-time-flex">
                    <span id="ai-voice-current-time" class="time-display">0:00</span>
                    <div class="progress-bar-container">
                        <input type="range" id="ai-voice-progress-bar" class="progress-bar" value="0" min="0" max="100">
                    </div>
                </div>
            </div>

            <!-- Total Time -->
            <span id="ai-voice-total-time" class="time-display total-time-display">0:00</span>
            
            <!-- Action Controls -->
            <div class="action-controls-container">
                <button id="ai-voice-speed-btn" class="control-button">1.0x</button>
                <button id="ai-voice-voice-btn" class="control-button" aria-label="<?php echo esc_attr($text_settings['change_voice_label']); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 14c1.66 0 2.99-1.34 2.99-3L15 5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-3c0 3-2.54 5.1-5.3 5.1S6.7 14 6.7 11H5c0 3.41 2.72 6.23 6 6.72V21h2v-3.28c3.28-.48 6-3.3 6-6.72h-1.7z"/></svg>
                </button>
                <button id="ai-voice-theme-toggle" class="control-button" aria-label="<?php echo esc_attr($text_settings['toggle_theme_label']); ?>">
                    <svg id="ai-voice-sun-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 7C14.76 7 17 9.24 17 12C17 14.76 14.76 17 12 17C9.24 17 7 14.76 7 12C7 9.24 9.24 7 12 7ZM12 9C10.34 9 9 10.34 9 12C9 13.66 10.34 15 12 15C13.66 15 15 13.66 15 12C15 10.34 13.66 9 12 9ZM2 13H4V11H2V13ZM20 13H22V11H20V13ZM11 2V4H13V2H11ZM11 22V20H13V22H11ZM5.64 6.36L4.22 4.93L2.81 6.34L4.22 7.78L5.64 6.36ZM18.36 19.07L19.78 20.5L21.19 19.09L19.78 17.66L18.36 19.07ZM19.78 6.34L21.19 4.93L19.78 3.51L18.36 4.93L19.78 6.34ZM4.22 17.66L2.81 19.07L4.22 20.5L5.64 19.09L4.22 17.66Z"></path></svg>
                    <!-- Fixed moon path -->
                    <svg id="ai-voice-moon-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="display: none;">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79z"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Summary Section (Initially Hidden) -->
        <div id="ai-voice-summary-section" class="summary-section" style="display: none;">
            <div class="summary-header">
                <h4 class="summary-title"><?php echo esc_html($text_settings['key_takeaways']); ?></h4>
                <button id="ai-voice-summary-close" class="summary-close-btn" aria-label="<?php echo esc_attr($text_settings['close_summary_label']); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/>
                    </svg>
                </button>
            </div>
            <div id="ai-voice-summary-content" class="summary-content">
                <!-- Summary content will be inserted here -->
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div id="ai-voice-speed-modal" class="modal-overlay">
        <div class="modal-content">
            <h4 class="modal-title">Playback Speed</h4>
            <div id="ai-voice-speed-options" class="modal-grid"></div>
        </div>
    </div>
    <div id="ai-voice-voice-modal" class="modal-overlay">
        <div class="modal-content">
            <h4 class="modal-title">Select a Voice</h4>
            <div id="ai-voice-voice-options" class="modal-voice-list"></div>
        </div>
    </div>
</div>