<?php // This is the template for the frontend player ?>
<div id="ai-voice-player-wrapper">
    <div class="ai-voice-player-container" data-theme="light">
        <div style="display:flex; align-items:center; width:100%; gap: 1rem;">
            
            <!-- Play/Pause Button -->
            <button id="ai-voice-play-pause-btn" class="ai-voice-accent-bg" style="color:white; border-radius:9999px; width:3rem; height:3rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; box-shadow:0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -2px rgba(0,0,0,.1);">
                <svg id="ai-voice-play-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" style="margin-left:2px;"><path d="M8 5v14l11-7z"></path></svg>
                <svg id="ai-voice-pause-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" style="display:none;"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"></path></svg>
            </button>
            
            <!-- Main Info & Progress -->
            <div style="flex-grow:1; width:100%; overflow:hidden;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <p id="ai-voice-article-title" style="font-weight:600; font-size: 0.875rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; padding-right:0.5rem;">Article Title</p>
                    <span id="ai-voice-logo-container" class="ai-voice-text-secondary" style="display:flex; align-items:center; gap:0.5rem; font-size:0.75rem; flex-shrink:0;"></span>
                </div>
                <div style="display:flex; align-items:center; gap:0.5rem; margin-top:0.25rem;">
                    <span id="ai-voice-current-time" class="ai-voice-text-secondary" style="font-size:0.75rem;">0:00</span>
                    <div style="position:relative; width:100%; height:1rem; display:flex; align-items:center;">
                        <input type="range" id="ai-voice-progress-bar" class="ai-voice-progress-bar" style="position:absolute; width:100%;" min="0" value="0">
                    </div>
                    <span id="ai-voice-total-time" class="ai-voice-text-secondary" style="font-size:0.75rem;">0:00</span>
                </div>
            </div>

            <!-- Action Controls -->
            <div class="ai-voice-border-color" style="display:flex; align-items:center; gap:0.25rem; border-left-width:1px; padding-left:0.75rem;">
                <button id="ai-voice-speed-btn" class="ai-voice-text-secondary" style="font-size:0.75rem; font-weight:600; padding:0.5rem; border-radius:0.5rem;">1.0x</button>
                <button id="ai-voice-theme-toggle" class="ai-voice-text-secondary" style="padding:0.5rem; border-radius:0.5rem;">
                    <svg id="ai-voice-sun-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                    <svg id="ai-voice-moon-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                </button>
            </div>
        </div>
    </div>
</div>
