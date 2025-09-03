<div id="ai-voice-player-wrapper">
    <div data-theme="light" class="ai-voice-player-container">
        <div class="player-inner-flex">
            
            <!-- Play/Pause Button -->
            <button id="ai-voice-play-pause-btn" class="play-pause-button">
                <svg id="ai-voice-play-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="play-icon-svg"><path d="M8 5v14l11-7z"></path></svg>
                <svg id="ai-voice-pause-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="pause-icon-svg"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"></path></svg>
                 <div id="ai-voice-loader" class="loader"></div>
            </button>
            
            <!-- Main Info & Progress -->
            <div class="info-progress-container">
                <div class="title-logo-flex">
                    <p id="ai-voice-article-title" class="article-title">Listen to this article</p>
                    <span id="ai-voice-logo-container" class="ai-logo-container">
                        <!-- Dynamically filled -->
                    </span>
                </div>
                <div class="progress-time-flex">
                    <span id="ai-voice-current-time" class="time-display">0:00</span>
                    <div class="progress-bar-wrapper">
                        <input type="range" id="ai-voice-progress-bar" class="progress-bar" min="0" max="100" value="0">
                    </div>
                    <span id="ai-voice-total-time" class="time-display">0:00</span>
                </div>
            </div>

            <!-- Action Controls -->
            <div class="action-controls-container">
                <button id="ai-voice-speed-btn" class="control-button">1.0x</button>
                <button id="ai-voice-voice-btn" class="control-button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/><path d="M12 5 9.04 7.96"/></svg>
                </button>
                <button id="ai-voice-theme-toggle" class="control-button">
                    <svg id="ai-voice-sun-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                    <svg id="ai-voice-moon-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modals -->
    <div id="ai-voice-speed-modal" class="modal-overlay">
        <div class="modal-content">
            <h3 class="modal-title">Playback Speed</h3>
            <div id="ai-voice-speed-options" class="modal-grid"></div>
        </div>
    </div>
    
    <div id="ai-voice-voice-modal" class="modal-overlay">
         <div class="modal-content">
            <h3 class="modal-title">Select a Voice</h3>
            <!-- In a future version, tabs for different AI can be added here -->
            <div id="ai-voice-voice-options" class="modal-voice-list"></div>
        </div>
    </div>
</div>

