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
            <button class="summary-button action-button" id="ai-voice-summary-btn" aria-label="<?php echo esc_attr($text_settings['summary_button_label']); ?>">
                <div id="ai-voice-summary-loader" class="loader"></div>
                <svg id="ai-voice-summary-icon" class="summary-icon-svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M14,17H7V15H14M17,13H7V11H17M17,9H7V7H17M19,3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3Z"/>
                </svg>
                <svg id="ai-voice-summary-check" class="summary-check-svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="display: none;">
                    <path d="M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2M11,16.5L18,9.5L16.59,8.09L11,13.67L7.91,10.59L6.5,12L11,16.5Z"/>
                </svg>
            </button>

            <!-- Play/Pause Button -->
            <button class="play-pause-button action-button" id="ai-voice-play-pause-btn" aria-label="<?php echo esc_attr($text_settings['play_pause_label']); ?>">
                <div id="ai-voice-loader" class="loader"></div>
                <svg id="ai-voice-play-icon" class="play-icon-svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 5V19L19 12L8 5Z"></path>
                </svg>
                <svg id="ai-voice-pause-icon" class="pause-icon-svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="display: none;">
                    <path d="M6 19H10V5H6V19ZM14 5V19H18V5H14Z"></path>
                </svg>
            </button>

            <!-- Main Info and Progress Bar -->
            <div class="info-progress-container">
                <div class="title-logo-flex">
                    <h3 id="ai-voice-article-title" class="article-title"><?php echo esc_html($text_settings['listen_to_article'] ?? 'Listen to the article'); ?></h3>
                    <div id="ai-voice-logo-container" class="ai-logo-container"></div>
                </div>
                <div class="progress-time-flex">
                    <span id="ai-voice-current-time" class="time-display">0:00</span>
                    <div class="progress-bar-container">
                        <input type="range" id="ai-voice-progress-bar" class="progress-bar" value="0" min="0" max="100">
                    </div>
                    <span id="ai-voice-total-time" class="time-display total-time-display">0:00</span>
                </div>
            </div>
            
            <!-- New Action Controls -->
            <div class="action-controls-container">
                
                <!-- Translate Button -->
                <button class="action-button feature-btn" id="ai-voice-translate-btn" aria-label="Translate Article" title="Translate Article">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12.87 15.07l-2.54-2.51.03-.03c1.74-1.94 2.98-4.17 3.71-6.53H17V4h-7V2H8v2H1v1.99h11.17C11.5 7.92 10.44 9.75 9 11.35 8.07 10.32 7.3 9.19 6.69 8h-2c.73 1.63 1.73 3.17 2.98 4.56l-5.09 5.02L4 19l5-5 3.11 3.11.76-2.04zM18.5 10h-2L12 22h2l1.12-3h4.75L21 22h2l-4.5-12zm-2.62 7l1.62-4.33L19.12 17h-3.24z"/>
                    </svg>
                </button>
                
                <!-- Read Along Button -->
                <button class="action-button feature-btn" id="ai-voice-readalong-btn" aria-label="Read Along" title="Read Along">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M21,5c-1.11-0.35-2.33-0.5-3.5-0.5c-1.95,0-4.05,0.4-5.5,1.5c-1.45-1.1-3.55-1.5-5.5-1.5S2.45,4.9,1,6v14.65 c0,0.25,0.25,0.5,0.5,0.5c0.1,0,0.15-0.05,0.25-0.05C3.1,20.45,5.05,20,6.5,20c1.95,0,4.05,0.4,5.5,1.5c1.35-0.85,3.8-1.5,5.5-1.5 c1.65,0,3.35,0.3,4.75,1.05c0.1,0.05,0.15,0.05,0.25,0.05c0.25,0,0.5-0.25,0.5-0.5V6C22.4,5.55,21.75,5.25,21,5z M21,18.5 c-1.1-0.35-2.3-0.5-3.5-0.5c-1.7,0-4.15,0.65-5.5,1.5V8c1.35-0.85,3.8-1.5,5.5-1.5c1.2,0,2.4,0.15,3.5,0.5V18.5z"/>
                        <path d="M17.5,10.5c0.88,0,1.73,0.09,2.5,0.26V9.24C19.21,9.09,18.36,9,17.5,9c-1.7,0-3.24,0.29-4.5,0.83v1.66 C14.13,10.85,15.7,10.5,17.5,10.5z"/>
                        <path d="M13,12.49v1.66c1.13-0.64,2.7-0.99,4.5-0.99c0.88,0,1.73,0.09,2.5,0.26V11.9c-0.79-0.15-1.64-0.24-2.5-0.24 C15.8,11.66,14.26,11.96,13,12.49z"/>
                        <path d="M17.5,14.33c-1.7,0-3.24,0.29-4.5,0.83v1.66c1.13-0.64,2.7-0.99,4.5-0.99c0.88,0,1.73,0.09,2.5,0.26v-1.52 C19.21,14.41,18.36,14.33,17.5,14.33z"/>
                    </svg>
                </button>
                
                <!-- Ask AI Button -->
                <button class="action-button feature-btn" id="ai-voice-askai-btn" aria-label="<?php echo esc_attr($text_settings['ask_ai_label'] ?? 'Ask AI'); ?>" title="<?php echo esc_attr($text_settings['ask_ai_label'] ?? 'Ask AI'); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12,2C6.48,2,2,6.48,2,12c0,1.54,0.36,2.98,0.97,4.29L1,23l6.71-1.97C9.02,21.64,10.46,22,12,22c5.52,0,10-4.48,10-10 S17.52,2,12,2z M16.5,16.5h-9c-0.28,0-0.5-0.22-0.5-0.5s0.22-0.5,0.5-0.5h9c0.28,0,0.5,0.22,0.5,0.5S16.78,16.5,16.5,16.5z M16.5,13h-9C7.22,13,7,12.78,7,12.5S7.22,12,7.5,12h9c0.28,0,0.5,0.22,0.5,0.5S16.78,13,16.5,13z M16.5,9.5h-9 C7.22,9.5,7,9.28,7,9s0.22-0.5,0.5-0.5h9C16.78,8.5,17,8.72,17,9S16.78,9.5,16.5,9.5z"/>
                    </svg>
                </button>
                
            </div>
        </div>

        <!-- Summary Section (Initially Hidden) -->
        <div id="ai-voice-summary-section" class="feature-section" style="display: none;">
            <div class="feature-header">
                <h4 class="feature-title"><?php echo esc_html($text_settings['key_takeaways']); ?></h4>
                <button id="ai-voice-summary-close" class="feature-close-btn" aria-label="<?php echo esc_attr($text_settings['close_summary_label']); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/>
                    </svg>
                </button>
            </div>
            <div id="ai-voice-summary-content" class="feature-content">
                <!-- Summary content will be inserted here -->
            </div>
        </div>

        <!-- Translate Section -->
        <div id="ai-voice-translate-section" class="feature-section" style="display: none;">
            <div class="feature-header">
                <h4 class="feature-title">🌐 <?php echo esc_html($text_settings['translate_title'] ?? 'Translate Article'); ?></h4>
                <button id="ai-voice-translate-close" class="feature-close-btn" aria-label="Close">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/>
                    </svg>
                </button>
            </div>
            <div class="feature-content">
                <div class="language-selector">
                    <select id="ai-voice-translate-select">
                        <option value="en">English (Original)</option>
                        <option value="es">Spanish - Español</option>
                        <option value="fr">French - Français</option>
                        <option value="de">German - Deutsch</option>
                        <option value="ar">Arabic - العربية</option>
                        <option value="zh-CN">Chinese - 中文</option>
                        <option value="ja">Japanese - 日本語</option>
                        <option value="ru">Russian - Русский</option>
                        <option value="pt">Portuguese - Português</option>
                        <option value="it">Italian - Italiano</option>
                        <option value="ko">Korean - 한국어</option>
                        <option value="nl">Dutch - Nederlands</option>
                        <option value="tr">Turkish - Türkçe</option>
                        <option value="pl">Polish - Polski</option>
                        <option value="hi">Hindi - हिन्दी</option>
                    </select>
                </div>
                <div id="ai-voice-translate-content" class="translated-text">
                    <!-- Translated content will appear here -->
                </div>
                <div id="ai-voice-translate-loader" class="feature-loader" style="display: none;">
                    <div class="spinner"></div>
                    <span>Translating...</span>
                </div>
            </div>
        </div>

        <!-- Read Along Section -->
        <div id="ai-voice-readalong-section" class="feature-section" style="display: none;">
            <div class="feature-header">
                <h4 class="feature-title">📖 <?php echo esc_html($text_settings['read_along_title'] ?? 'Read Along'); ?></h4>
                <button id="ai-voice-readalong-close" class="feature-close-btn" aria-label="Close">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/>
                    </svg>
                </button>
            </div>
            <div class="feature-content">
                <div id="ai-voice-readalong-text" class="read-along-text">
                    <!-- Article text will be inserted here -->
                </div>
            </div>
        </div>

        <!-- Ask AI Section -->
        <div id="ai-voice-askai-section" class="feature-section" style="display: none;">
            <div class="feature-header">
                <h4 class="feature-title">💬 <?php echo esc_html($text_settings['ai_assistant_name'] ?? 'AI Assistant'); ?></h4>
                <button id="ai-voice-askai-close" class="feature-close-btn" aria-label="Close">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/>
                    </svg>
                </button>
            </div>
            <div class="feature-content">
                <div id="ai-voice-chat-messages" class="chat-messages">
                    <div class="chat-message ai-message">
                        <div class="message-avatar ai-avatar">🤖</div>
                        <div class="message-content">
                            Hi! I'm here to help you understand this article. Ask me anything about the content!
                        </div>
                    </div>
                </div>
                <div class="chat-input-container">
                    <input type="text" id="ai-voice-chat-input" class="chat-input" placeholder="Ask about the article..." />
                    <button id="ai-voice-chat-send" class="send-btn">Send</button>
                </div>
            </div>
        </div>
        
    </div>
</div>