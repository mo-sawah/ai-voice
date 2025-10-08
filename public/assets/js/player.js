document.addEventListener("DOMContentLoaded", () => {
  const wrapper = document.getElementById("ai-voice-player-wrapper");
  if (!wrapper || typeof aiVoiceData === "undefined") return;

  // Elements
  const playerContainer = wrapper.querySelector(".ai-voice-player-container");
  const summaryBtn = wrapper.querySelector("#ai-voice-summary-btn");
  const summaryIcon = wrapper.querySelector("#ai-voice-summary-icon");
  const summaryCheck = wrapper.querySelector("#ai-voice-summary-check");
  const summaryLoader = wrapper.querySelector("#ai-voice-summary-loader");
  const summarySection = wrapper.querySelector("#ai-voice-summary-section");
  const summaryContent = wrapper.querySelector("#ai-voice-summary-content");
  const summaryClose = wrapper.querySelector("#ai-voice-summary-close");

  const playPauseBtn = wrapper.querySelector("#ai-voice-play-pause-btn");
  const playIcon = wrapper.querySelector("#ai-voice-play-icon");
  const pauseIcon = wrapper.querySelector("#ai-voice-pause-icon");
  const loader = wrapper.querySelector("#ai-voice-loader");
  const progressBar = wrapper.querySelector("#ai-voice-progress-bar");
  const currentTimeEl = wrapper.querySelector("#ai-voice-current-time");
  const totalTimeEl = wrapper.querySelector("#ai-voice-total-time");
  const aiLogoContainer = wrapper.querySelector("#ai-voice-logo-container");
  const articleTitleEl = wrapper.querySelector("#ai-voice-article-title");

  // New feature elements
  const translateBtn = wrapper.querySelector("#ai-voice-translate-btn");
  const translateSection = wrapper.querySelector("#ai-voice-translate-section");
  const translateClose = wrapper.querySelector("#ai-voice-translate-close");
  const translateSelect = wrapper.querySelector("#ai-voice-translate-select");
  const translateContent = wrapper.querySelector("#ai-voice-translate-content");
  const translateLoader = wrapper.querySelector("#ai-voice-translate-loader");

  const readalongBtn = wrapper.querySelector("#ai-voice-readalong-btn");
  const readalongSection = wrapper.querySelector("#ai-voice-readalong-section");
  const readalongClose = wrapper.querySelector("#ai-voice-readalong-close");
  const readalongText = wrapper.querySelector("#ai-voice-readalong-text");

  const askaiBtn = wrapper.querySelector("#ai-voice-askai-btn");
  const askaiSection = wrapper.querySelector("#ai-voice-askai-section");
  const askaiClose = wrapper.querySelector("#ai-voice-askai-close");
  const chatMessages = wrapper.querySelector("#ai-voice-chat-messages");
  const chatInput = wrapper.querySelector("#ai-voice-chat-input");
  const chatSend = wrapper.querySelector("#ai-voice-chat-send");

  // Audio + state
  const audio = new Audio();
  let isGenerating = false;
  let isGeneratingSummary = false;
  let currentTheme = aiVoiceData.theme || "light";
  let generationAttempts = 0;
  let summaryGenerated = false;
  const maxGenerationAttempts = 2;
  let audioHadError = false;
  let isReadAlongActive = false;
  let readAlongInterval = null;

  // 🆕 Translation state
  let isTranslated = false;
  let currentLanguage = "en";

  // 🆕 Get the actual article container on the page (for Read Along only)
  const articleContainer = document.querySelector(
    ".entry-content, .post-content, article.post, main article, .article-content"
  );

  // Helpers
  const formatTime = (seconds) => {
    if (isNaN(seconds) || seconds < 0) return "0:00";
    const minutes = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${minutes}:${secs.toString().padStart(2, "0")}`;
  };

  const updateProgressBarUI = () => {
    const percentage = audio.duration
      ? (audio.currentTime / audio.duration) * 100
      : 0;
    const cs = getComputedStyle(wrapper);
    const accentColor =
      currentTheme === "light"
        ? cs.getPropertyValue("--accent-light").trim()
        : cs.getPropertyValue("--accent-dark").trim();
    const bgColor =
      currentTheme === "light"
        ? cs.getPropertyValue("--bg-secondary-light").trim()
        : cs.getPropertyValue("--bg-secondary-dark").trim();
    const finalAccent =
      accentColor || (currentTheme === "light" ? "#3b82f6" : "#60a5fa");
    const finalBg =
      bgColor || (currentTheme === "light" ? "#f1f5f9" : "#334155");
    progressBar.style.background = `linear-gradient(to right, ${finalAccent} ${percentage}%, ${finalBg} ${percentage}%)`;
  };

  // UI state setters
  const updateTheme = (theme) => {
    currentTheme = theme;
    playerContainer.dataset.theme = theme;
    updateProgressBarUI();
  };

  const updateAILogo = () => {
    const service = aiVoiceData.aiService || "google";
    if (service === "google") {
      aiLogoContainer.innerHTML = `
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
      </svg>
      <span>${aiVoiceData.text.voiced_by_google}</span>`;
    } else if (service === "gemini") {
      aiLogoContainer.innerHTML = `
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2L2 7v10c0 5.55 3.84 9.74 9 11 5.16-1.26 9-5.45 9-11V7l-10-5z"/>
        <path d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zm0 8c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3z"/>
      </svg>
      <span>${aiVoiceData.text.voiced_by_gemini}</span>`;
    } else if (service === "openai") {
      aiLogoContainer.innerHTML = `
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
        <path d="M22.282 9.821c-.29-2.737-2.2-4.648-4.937-4.937a5.85 5.85 0 0 0-4.546 1.567 5.85 5.85 0 0 0-4.546-1.567c-2.737.289-4.647 2.2-4.937 4.937a5.85 5.85 0 0 0 1.567 4.546 5.85 5.85 0 0 0 1.567 4.546c.29 2.737 2.2 4.647 4.937 4.937a5.85 5.85 0 0 0 4.546-1.567 5.85 5.85 0 0 0 4.546 1.567c2.737-.29 4.647-2.2 4.937-4.937a5.85 5.85 0 0 0-1.567-4.546 5.85 5.85 0 0 0 1.567-4.546zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"/>
      </svg>
      <span>${aiVoiceData.text.voiced_by_openai}</span>`;
    }
  };

  // Robust helper to finalize Summary UI
  const setSummaryGeneratedUI = () => {
    summaryGenerated = true;
    isGeneratingSummary = false;
    summaryBtn.disabled = false;
    summaryLoader.style.display = "none";
    summaryIcon.style.display = "none";
    summaryCheck.style.display = "block";
    summaryBtn.classList.add("generated");
  };

  // UI helpers
  const clearError = () => {
    articleTitleEl.textContent = aiVoiceData.text.generating_audio;
    articleTitleEl.style.color = "";
  };

  const showError = (message) => {
    articleTitleEl.textContent = "Error: " + message;
    articleTitleEl.style.color = "#ef4444";
    console.error("AI Voice Error:", message);
    resetPlayerState();
  };

  const showSummaryError = (message) => {
    summaryContent.innerHTML =
      '<p style="color: #ef4444;">Error: ' + message + "</p>";
    console.error("AI Voice Summary Error:", message);
    summaryLoader.style.display = "none";
    isGeneratingSummary = false;
    summaryBtn.disabled = false;
    summaryIcon.style.display = "block";
    summaryCheck.style.display = "none";
    summaryBtn.classList.remove("generated");
  };

  // Toggle feature sections
  const toggleFeatureSection = (section) => {
    const allSections = [
      summarySection,
      translateSection,
      readalongSection,
      askaiSection,
    ];
    allSections.forEach((s) => {
      if (s === section) {
        const isVisible = s.style.display !== "none";
        s.style.display = isVisible ? "none" : "block";
      } else {
        s.style.display = "none";
      }
    });
  };

  // Actions
  const togglePlayPause = () => {
    if (isGenerating) return;
    if (!audio.src) {
      generateAudio();
      return;
    }
    clearError();
    audio.paused ? audio.play() : audio.pause();
  };

  const toggleSummary = () => {
    if (isGeneratingSummary) return;

    if (!summaryGenerated) {
      generateSummary();
    } else {
      toggleFeatureSection(summarySection);
      summaryLoader.style.display = "none";
    }
  };

  const generateSummary = () => {
    if (isGeneratingSummary) return;

    isGeneratingSummary = true;
    summaryBtn.disabled = true;
    summaryIcon.style.display = "none";
    summaryLoader.style.display = "block";
    summarySection.style.display = "block";

    jQuery.ajax({
      url: aiVoiceData.ajax_url,
      type: "POST",
      data: {
        action: "ai_voice_generate_summary",
        nonce: aiVoiceData.nonce,
        post_id: aiVoiceData.post_id,
      },
      timeout: 60000,
      success: function (response) {
        if (response.success) {
          summaryContent.innerHTML = response.data.summary;
          setSummaryGeneratedUI();
        } else {
          const errorMsg =
            response.data?.message || "Summary generation failed.";
          showSummaryError(errorMsg);
        }
      },
      error: function (jqXHR, textStatus) {
        let errorMessage = "Summary request failed.";
        if (textStatus === "timeout")
          errorMessage = "Summary request timed out. Please try again.";
        else if (jqXHR.status === 500)
          errorMessage = "Server error. Please check your API configuration.";
        else if (jqXHR.status === 0)
          errorMessage = "Network error. Check your internet connection.";
        showSummaryError(errorMessage);
      },
    });
  };

  const generateAudio = () => {
    if (isGenerating) return;
    generationAttempts++;
    isGenerating = true;
    playPauseBtn.disabled = true;
    playIcon.style.display = "none";
    loader.style.display = "block";

    articleTitleEl.textContent =
      aiVoiceData.text.generating_audio || "Generating audio...";
    articleTitleEl.style.color = "";

    jQuery.ajax({
      url: aiVoiceData.ajax_url,
      type: "POST",
      data: {
        action: "ai_voice_generate_audio",
        nonce: aiVoiceData.nonce,
        post_id: aiVoiceData.post_id,
      },
      timeout: 180000,
      success: function (response) {
        if (response.success) {
          audio.src = response.data.audioUrl;
          clearError();
          audio.play().catch((error) => {
            console.warn("AI Voice: Auto-play prevented by browser:", error);
            resetPlayerState();
          });
          generationAttempts = 0;
        } else {
          const errorMsg = response.data?.message || "Generation failed.";
          if (
            generationAttempts < maxGenerationAttempts &&
            (errorMsg.includes("timeout") || errorMsg.includes("temporary"))
          ) {
            setTimeout(() => {
              isGenerating = false;
              generateAudio();
            }, 2000);
            return;
          }
          showError(errorMsg);
        }
      },
      error: function (jqXHR, textStatus) {
        let errorMessage = "Request failed.";
        if (textStatus === "timeout")
          errorMessage = "Request timed out. The text might be too long.";
        else if (jqXHR.status === 524)
          errorMessage = "Server timeout. Please try with shorter text.";
        else if (jqXHR.status === 500)
          errorMessage = "Server error. Please try again.";
        else if (jqXHR.status === 0)
          errorMessage = "Network error. Check your internet connection.";
        if (
          generationAttempts < maxGenerationAttempts &&
          (textStatus === "timeout" ||
            jqXHR.status === 524 ||
            jqXHR.status === 0)
        ) {
          setTimeout(() => {
            isGenerating = false;
            generateAudio();
          }, 3000);
          return;
        }
        showError(errorMessage);
      },
    });
  };

  // Reset helpers
  const resetPlayerState = () => {
    isGenerating = false;
    playPauseBtn.disabled = false;
    loader.style.display = "none";
    playIcon.style.display = "block";
    pauseIcon.style.display = "none";
    generationAttempts = 0;
  };

  // ============================================
  // 🆕 TRANSLATE FEATURE - FIXED (Dropdown Only)
  // ============================================
  const initTranslate = () => {
    toggleFeatureSection(translateSection);

    // Show initial state
    if (isTranslated) {
      // Already translated - show current translation
      // Translation content is already displayed from previous translation
    } else {
      translateContent.innerHTML = `<div style="padding: 12px; background: #f0f6fc; border-radius: 8px; border: 1px solid #d0e4f7;">
        <p style="margin: 0; color: #64748b;">Select a language above to translate the article. Translation will appear here.</p>
      </div>`;
      translateContent.classList.add("active");
    }
  };

  const getLanguageName = (code) => {
    const names = {
      en: "English",
      es: "Spanish",
      fr: "French",
      de: "German",
      ar: "Arabic",
      "zh-CN": "Chinese",
      ja: "Japanese",
      ru: "Russian",
      pt: "Portuguese",
      it: "Italian",
      ko: "Korean",
      nl: "Dutch",
      tr: "Turkish",
      pl: "Polish",
      hi: "Hindi",
    };
    return names[code] || code;
  };

  const translateArticle = (targetLang) => {
    // If selecting English (Original), show original in dropdown
    if (targetLang === "en") {
      translateContent.innerHTML = `<div style="padding: 12px; background: #f0f6fc; border-radius: 8px; border: 1px solid #d0e4f7;">
        <p style="margin: 0; color: #2271b1; font-weight: 500;">📄 Original Article (English)</p>
      </div>`;
      translateContent.classList.add("active");
      return;
    }

    // Show loading
    translateLoader.style.display = "flex";
    translateContent.classList.remove("active");
    translateContent.innerHTML = "";

    console.log("Translating article to:", targetLang);

    jQuery.ajax({
      url: aiVoiceData.ajax_url,
      type: "POST",
      data: {
        action: "ai_voice_translate_text",
        nonce: aiVoiceData.nonce,
        post_id: aiVoiceData.post_id,
        target_lang: targetLang,
      },
      timeout: 60000,
      success: function (response) {
        translateLoader.style.display = "none";
        if (response.success) {
          const translatedText = response.data.translated_text;

          // Display in dropdown (don't replace article)
          translateContent.innerHTML = `<div style="padding: 16px; background: var(--bg-secondary-light); border-radius: 8px; max-height: 400px; overflow-y: auto; line-height: 1.8;">
            <div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--border-light);">
              <strong style="color: var(--accent-light);">✓ Translated to ${getLanguageName(
                targetLang
              )}</strong>
            </div>
            ${translatedText
              .split("\n\n")
              .filter((p) => p.trim())
              .map((p) => `<p style="margin-bottom: 12px;">${p.trim()}</p>`)
              .join("")}
          </div>`;

          translateContent.classList.add("active");
          currentLanguage = targetLang;
          isTranslated = true;
        } else {
          translateContent.innerHTML = `<p style="color: #ef4444;">Translation failed: ${
            response.data.message || "Unknown error"
          }</p>`;
          translateContent.classList.add("active");
        }
      },
      error: function (jqXHR, textStatus) {
        translateLoader.style.display = "none";
        let errorMsg = "Translation request failed.";
        if (textStatus === "timeout") {
          errorMsg = "Translation timed out. Try again.";
        }
        translateContent.innerHTML = `<p style="color: #ef4444;">${errorMsg}</p>`;
        translateContent.classList.add("active");
      },
    });
  };

  // ============================================
  // 🆕 READ ALONG FEATURE - FIXED
  // ============================================
  const initReadAlong = () => {
    if (!articleContainer) {
      alert("Could not find article content on this page");
      return;
    }

    toggleFeatureSection(readalongSection);

    // Load article text on first open
    if (!readalongText.dataset.loaded) {
      loadArticleForReadAlong();
      readalongText.dataset.loaded = "true";
    }

    // Start audio if not playing
    if (audio.paused && audio.src) {
      audio.play();
    } else if (!audio.src) {
      generateAudio();
    }

    // Enable read-along highlighting
    isReadAlongActive = true;
  };

  const loadArticleForReadAlong = () => {
    if (!articleContainer) return;

    // Get all paragraphs from article
    const paragraphs = articleContainer.querySelectorAll("p");

    if (paragraphs.length === 0) {
      readalongText.innerHTML =
        "<p>No article content found for read-along</p>";
      return;
    }

    let sentenceIndex = 0;
    let allSentences = [];

    paragraphs.forEach((p) => {
      const text = p.innerText || p.textContent;
      if (!text.trim()) return;

      // Split into sentences
      const sentences = text.split(/(?<=[.!?])\s+/);

      sentences.forEach((sentence) => {
        if (sentence.trim().length > 10) {
          allSentences.push({
            text: sentence.trim(),
            index: sentenceIndex++,
          });
        }
      });
    });

    // Render sentences with highlighting capability
    const wrappedText = allSentences
      .map(
        (s) =>
          `<span class="sentence" data-index="${s.index}">${s.text}</span> `
      )
      .join("");

    readalongText.innerHTML = wrappedText;
  };

  const updateReadAlongHighlight = () => {
    if (!isReadAlongActive || !audio.duration) return;

    const sentences = readalongText.querySelectorAll(".sentence");
    if (sentences.length === 0) return;

    // Calculate progress with slight advance (0.5 seconds ahead)
    const adjustedTime = Math.min(audio.currentTime + 0.5, audio.duration);
    const progress = adjustedTime / audio.duration;
    const currentIndex = Math.floor(progress * sentences.length);

    // Remove all highlights
    sentences.forEach((s) => s.classList.remove("highlight"));

    // Highlight current sentence
    if (sentences[currentIndex]) {
      sentences[currentIndex].classList.add("highlight");

      // Auto-scroll to keep highlighted text visible
      sentences[currentIndex].scrollIntoView({
        behavior: "smooth",
        block: "center",
      });
    }
  };

  // ============================================
  // 🆕 ASK AI CHAT FEATURE - FIXED
  // ============================================
  const initAskAI = () => {
    toggleFeatureSection(askaiSection);

    // Focus on input
    setTimeout(() => chatInput.focus(), 100);
  };

  const sendChatMessage = () => {
    const message = chatInput.value.trim();
    if (!message) return;

    // Add user message to chat
    addChatMessage(message, "user");
    chatInput.value = "";
    chatInput.disabled = true;
    chatSend.disabled = true;

    console.log("Sending chat message:", message);

    // Send to AI via AJAX with full context
    jQuery.ajax({
      url: aiVoiceData.ajax_url,
      type: "POST",
      data: {
        action: "ai_voice_ask_ai",
        nonce: aiVoiceData.nonce,
        post_id: aiVoiceData.post_id,
        message: message,
      },
      timeout: 45000,
      success: function (response) {
        console.log("Chat response:", response);

        if (response.success) {
          addChatMessage(response.data.reply, "ai");
        } else {
          addChatMessage(
            "Sorry, I encountered an error: " +
              (response.data.message || "Unknown error"),
            "ai"
          );
        }
      },
      error: function (jqXHR, textStatus) {
        console.error("Chat error:", textStatus, jqXHR);

        let errorMsg = "Connection error. Please try again.";
        if (textStatus === "timeout") {
          errorMsg = "Request timed out. Please try again.";
        } else if (jqXHR.status === 500) {
          errorMsg = "Server error. Check your API configuration.";
        }

        addChatMessage(errorMsg, "ai");
      },
      complete: function () {
        // ALWAYS re-enable input after response (success or error)
        chatInput.disabled = false;
        chatSend.disabled = false;
        chatInput.focus();
      },
    });
  };

  const addChatMessage = (text, sender) => {
    const messageDiv = document.createElement("div");
    messageDiv.className = `chat-message ${sender}-message`;

    const avatar = document.createElement("div");
    avatar.className = `message-avatar ${sender}-avatar`;
    avatar.textContent = sender === "user" ? "👤" : "🤖";

    const content = document.createElement("div");
    content.className = "message-content";
    content.textContent = text;

    messageDiv.appendChild(avatar);
    messageDiv.appendChild(content);

    chatMessages.appendChild(messageDiv);

    // Scroll to bottom
    chatMessages.scrollTop = chatMessages.scrollHeight;
  };

  // ============================================
  // EVENT LISTENERS
  // ============================================

  // Summary
  summaryBtn.addEventListener("click", () => toggleSummary());
  summaryClose.addEventListener(
    "click",
    () => (summarySection.style.display = "none")
  );

  // Play/Pause
  playPauseBtn.addEventListener("click", () => togglePlayPause());

  // Translate
  translateBtn.addEventListener("click", () => initTranslate());
  translateClose.addEventListener(
    "click",
    () => (translateSection.style.display = "none")
  );
  translateSelect.addEventListener("change", (e) =>
    translateArticle(e.target.value)
  );

  // Read Along
  readalongBtn.addEventListener("click", () => initReadAlong());
  readalongClose.addEventListener("click", () => {
    readalongSection.style.display = "none";
    isReadAlongActive = false;
  });

  // Ask AI
  askaiBtn.addEventListener("click", () => initAskAI());
  askaiClose.addEventListener(
    "click",
    () => (askaiSection.style.display = "none")
  );
  chatSend.addEventListener("click", () => sendChatMessage());
  chatInput.addEventListener("keypress", (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      sendChatMessage();
    }
  });

  // Audio events
  audio.addEventListener("play", () => {
    playIcon.style.display = "none";
    pauseIcon.style.display = "block";
    isGenerating = false;
    playPauseBtn.disabled = false;
    loader.style.display = "none";
  });

  audio.addEventListener("pause", () => {
    pauseIcon.style.display = "none";
    playIcon.style.display = "block";
  });

  audio.addEventListener("ended", () => {
    audio.currentTime = 0;
    audio.pause();
    isReadAlongActive = false;
  });

  audio.addEventListener("loadedmetadata", () => {
    totalTimeEl.textContent = formatTime(audio.duration);
    progressBar.max = audio.duration;
  });

  audio.addEventListener("timeupdate", () => {
    currentTimeEl.textContent = formatTime(audio.currentTime);
    progressBar.value = audio.currentTime;
    updateProgressBarUI();

    // Update read-along highlighting
    if (isReadAlongActive) {
      updateReadAlongHighlight();
    }
  });

  audio.addEventListener("error", (e) => {
    if (audioHadError) return;
    audioHadError = true;
    console.error("AI Voice: Audio error:", e);
    showError("Audio playback failed. Please try regenerating.");
    try {
      audio.pause();
      audio.removeAttribute("src");
      audio.load();
    } catch {}
    setTimeout(() => {
      audioHadError = false;
    }, 2000);
  });

  audio.addEventListener("loadstart", () => clearError());

  progressBar.addEventListener("input", (e) => {
    if (audio.src && audio.duration) {
      audio.currentTime = parseFloat(e.target.value);
      updateProgressBarUI();
    }
  });

  // Init
  updateTheme(currentTheme);
  updateAILogo();
  articleTitleEl.textContent = aiVoiceData.text.listen_to_article;
  updateProgressBarUI();

  console.log("AI Voice Player with new features initialized successfully");
});
