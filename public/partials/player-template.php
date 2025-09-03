<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
?>
<div id="ai-voice-player-wrapper">
    <div class="ai-voice-player-container" data-theme="light">
        <div class="player-inner-flex">

            <!-- Play/Pause Button -->
            <button class="play-pause-button" id="ai-voice-play-pause-btn" aria-label="Play/Pause Audio">
                <div id="ai-voice-loader" class="loader"></div>
                <svg id="ai-voice-play-icon" class="play-icon-svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M8 5V19L19 12L8 5Z"></path></svg>
                <svg id="ai-voice-pause-icon" class="pause-icon-svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M6 19H10V5H6V19ZM14 5V19H18V5H14Z"></path></svg>
            </button>

            <!-- Main Info and Progress Bar -->
            <div class="info-progress-container">
                <div class="title-logo-flex">
                    <h3 id="ai-voice-article-title" class="article-title">Loading Article Title...</h3>
                    <div id="ai-voice-logo-container" class="ai-logo-container"></div>
                </div>
                <div class="progress-time-flex">
                    <span id="ai-voice-current-time" class="time-display">0:00</span>
                    <div class="progress-bar-container">
                        <input type="range" id="ai-voice-progress-bar" class="progress-bar" value="0" min="0" max="100">
                    </div>
                </div>
            </div>

            <!-- Total Time (Moved to correct position) -->
            <span id="ai-voice-total-time" class="time-display total-time-display">0:00</span>
            
            <!-- Action Controls -->
            <div class="action-controls-container">
                <button id="ai-voice-speed-btn" class="control-button">1.0x</button>
                <button id="ai-voice-voice-btn" class="control-button" aria-label="Change Voice">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 21.35L10.55 20.03C5.4 15.36 2 12.27 2 8.5C2 5.41 4.42 3 7.5 3C9.24 3 10.91 3.81 12 5.08C13.09 3.81 14.76 3 16.5 3C19.58 3 22 5.41 22 8.5C22 12.27 18.6 15.36 13.45 20.03L12 21.35Z"></path></svg>
                </button>
                <button id="ai-voice-theme-toggle" class="control-button" aria-label="Toggle Theme">
                    <svg id="ai-voice-sun-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 7C14.76 7 17 9.24 17 12C17 14.76 14.76 17 12 17C9.24 17 7 14.76 7 12C7 9.24 9.24 7 12 7ZM12 9C10.34 9 9 10.34 9 12C9 13.66 10.34 15 12 15C13.66 15 15 13.66 15 12C15 10.34 13.66 9 12 9ZM2 13H4V11H2V13ZM20 13H22V11H20V13ZM11 2V4H13V2H11ZM11 22V20H13V22H11ZM5.64 6.36L4.22 4.93L2.81 6.34L4.22 7.78L5.64 6.36ZM18.36 19.07L19.78 20.5L21.19 19.09L19.78 17.66L18.36 19.07ZM19.78 6.34L21.19 4.93L19.78 3.51L18.36 4.93L19.78 6.34ZM4.22 17.66L2.81 19.07L4.22 20.5L5.64 19.09L4.22 17.66Z"></path></svg>
                    <svg id="ai-voice-moon-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="display: none;"><path d="M12 3C16.97 3 21 7.03 21 12C21 16.97 16.97 21 12 21C7.03 21 3 16.97 3 12C3 9.38 4.19 7.09 5.89 5.59C6.21 6.82 7.23 7.83 8.45 8.15C9.05 8.32 9.68 8.44 10.34 8.44C13.92 8.44 16.5 6.09 16.5 3.05C16.5 2.53 16.44 2.05 16.32 1.59C17.91 4.19 18.68 5.68 18.91 6.22C19.76 8.1 19.43 10.23 18.29 11.89C16.88 13.9 14.55 15.17 12 15.17C11.45 15.17 10.92 15.11 10.41 15C8.08 14.5 6.5 12.92 6.09 10.63C6.03 10.33 6 10.02 6 9.71C6 9.47 6.02 9.22 6.05 8.98C5.02 10.15 4.39 11.53 4.14 13.02C3.4 17.21 6.79 20.6 11 20.95C15.21 21.3 18.6 17.91 18.95 13.7C19.03 12.89 18.94 12.06 18.71 11.29C17.91 8.55 15.45 6.44 12.68 6.05C12.46 6.02 12.23 6 12 6C9.24 6 7 8.24 7 11C7 11.28 7.03 11.56 7.08 11.82C7.58 14.13 9.59 15.92 12 15.92C14.41 15.92 16.42 14.13 16.92 11.82C16.97 11.56 17 11.28 17 11C17 8.24 14.76 6 12 6C11.53 6 11.08 6.05 10.65 6.14C11.63 4.86 12.96 4 14.5 4C14.79 4 15.08 4.04 15.36 4.11C14.54 3.42 13.31 3 12 3Z"></path></svg>
                </button>
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

