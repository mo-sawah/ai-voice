document.addEventListener("DOMContentLoaded", () => {
  const wrapper = document.getElementById("ai-voice-player-wrapper");
  if (!wrapper || typeof aiVoiceData === "undefined") return;

  // --- DOM ELEMENTS ---
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
  const themeToggle = wrapper.querySelector("#ai-voice-theme-toggle");
  const sunIcon = wrapper.querySelector("#ai-voice-sun-icon");
  const moonIcon = wrapper.querySelector("#ai-voice-moon-icon");
  const speedBtn = wrapper.querySelector("#ai-voice-speed-btn");
  const voiceBtn = wrapper.querySelector("#ai-voice-voice-btn");
  const aiLogoContainer = wrapper.querySelector("#ai-voice-logo-container");
  const articleTitleEl = wrapper.querySelector("#ai-voice-article-title");
  const speedModal = wrapper.querySelector("#ai-voice-speed-modal");
  const voiceModal = wrapper.querySelector("#ai-voice-voice-modal");

  // --- AUDIO & STATE ---
  const audio = new Audio();
  let isGenerating = false;
  let isGeneratingSummary = false;
  let currentTheme = aiVoiceData.theme || "light";
  let currentSpeed = 1.0;
  let generationAttempts = 0;
  let summaryGenerated = false;
  const maxGenerationAttempts = 2;

  const voices = {
    google: [
      { id: "en-US-Studio-O", name: "Studio (Female)" },
      { id: "en-US-Studio-M", name: "Studio (Male)" },
      { id: "en-US-Neural2-J", name: "Neural (Male)" },
      { id: "en-US-Neural2-F", name: "Neural (Female)" },
      { id: "en-US-Wavenet-F", name: "WaveNet (Female)" },
      { id: "en-US-Wavenet-D", name: "WaveNet (Male)" },
    ],
    gemini: [
      { id: "Kore", name: "Kore (Firm)" },
      { id: "Puck", name: "Puck (Upbeat)" },
      { id: "Charon", name: "Charon (Informative)" },
      { id: "Leda", name: "Leda (Youthful)" },
      { id: "Enceladus", name: "Enceladus (Breathy)" },
    ],
    openai: [
      { id: "alloy", name: "Alloy" },
      { id: "echo", name: "Echo" },
      { id: "fable", name: "Fable" },
      { id: "onyx", name: "Onyx" },
      { id: "nova", name: "Nova" },
      { id: "shimmer", name: "Shimmer" },
    ],
  };

  // --- FUNCTIONS ---
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

    // Get computed CSS variables from the actual element
    const computedStyles = getComputedStyle(wrapper);
    const accentColor =
      currentTheme === "light"
        ? computedStyles.getPropertyValue("--accent-light").trim()
        : computedStyles.getPropertyValue("--accent-dark").trim();
    const bgColor =
      currentTheme === "light"
        ? computedStyles.getPropertyValue("--bg-secondary-light").trim()
        : computedStyles.getPropertyValue("--bg-secondary-dark").trim();

    // Fallback colors if CSS variables aren't loaded
    const fallbackAccent = currentTheme === "light" ? "#3b82f6" : "#60a5fa";
    const fallbackBg = currentTheme === "light" ? "#f1f5f9" : "#334155";

    const finalAccent = accentColor || fallbackAccent;
    const finalBg = bgColor || fallbackBg;

    progressBar.style.background = `linear-gradient(to right, ${finalAccent} ${percentage}%, ${finalBg} ${percentage}%)`;
  };

  const resetPlayerState = () => {
    isGenerating = false;
    playPauseBtn.disabled = false;
    loader.style.display = "none";
    playIcon.style.display = "block";
    pauseIcon.style.display = "none";
    generationAttempts = 0;
  };

  const resetSummaryState = () => {
    isGeneratingSummary = false;
    summaryBtn.disabled = false;
    summaryLoader.style.display = "none";
    if (summaryGenerated) {
      summaryIcon.style.display = "none";
      summaryCheck.style.display = "block";
      summaryBtn.classList.add("generated");
    } else {
      summaryIcon.style.display = "block";
      summaryCheck.style.display = "none";
      summaryBtn.classList.remove("generated");
    }
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
    resetSummaryState();
  };

  const clearError = () => {
    articleTitleEl.textContent = "Listen to the article";
    articleTitleEl.style.color = "";
  };

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
      // Toggle summary section visibility
      if (summarySection.style.display === "none") {
        summarySection.style.display = "block";
      } else {
        summarySection.style.display = "none";
      }
    }
  };

  const getVisibleText = () => {
    let contentNode = wrapper.closest(
      "article, .post, .entry-content, main, .content"
    );
    if (!contentNode) {
      contentNode = wrapper.parentElement;
    }

    const clone = contentNode.cloneNode(true);

    // Remove the player itself
    const playerClone = clone.querySelector("#ai-voice-player-wrapper");
    if (playerClone) playerClone.remove();

    // Remove unwanted elements
    clone
      .querySelectorAll(
        [
          "script",
          "style",
          "noscript",
          "nav",
          "header",
          "footer",
          ".ads",
          ".advertisement",
          ".sidebar",
          ".menu",
          ".navigation",
          '[aria-hidden="true"]',
          ".screen-reader-text",
          ".wp-caption-text",
          ".social-share",
          ".related-posts",
          ".comments",
          ".comment-form",
        ].join(",")
      )
      .forEach((el) => el.remove());

    let text = clone.textContent || clone.innerText || "";

    // Clean up the text
    text = text.replace(/\s+/g, " ").trim();

    // Remove common unwanted phrases
    text = text.replace(
      /^(Skip to content|Menu|Search|Home|About|Contact)[\s\n]*/gi,
      ""
    );
    text = text.replace(
      /(Copyright|©|\d{4}|All rights reserved|Privacy Policy|Terms of Service).*$/gi,
      ""
    );

    return text;
  };

  const generateSummary = () => {
    if (isGeneratingSummary) return;

    isGeneratingSummary = true;
    summaryBtn.disabled = true;
    summaryIcon.style.display = "none";
    summaryLoader.style.display = "block";

    const textToSummarize = getVisibleText();

    if (!textToSummarize || textToSummarize.trim().length < 50) {
      showSummaryError("Not enough text content found to summarize.");
      return;
    }

    console.log(
      "AI Voice: Generating summary for text length:",
      textToSummarize.length,
      "characters"
    );

    jQuery.ajax({
      url: aiVoiceData.ajax_url,
      type: "POST",
      data: {
        action: "ai_voice_generate_summary",
        nonce: aiVoiceData.nonce,
        post_id: aiVoiceData.post_id,
        text_to_summarize: textToSummarize,
      },
      timeout: 60000, // 1 minute timeout
      success: function (response) {
        console.log("AI Voice: Summary response:", response);

        if (response.success) {
          summaryContent.innerHTML = response.data.summary;
          summarySection.style.display = "block";
          summaryGenerated = true;
          resetSummaryState();
        } else {
          const errorMsg =
            response.data?.message || "Summary generation failed.";
          showSummaryError(errorMsg);
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        console.error("AI Voice: Summary AJAX Error:", {
          status: jqXHR.status,
          textStatus,
          errorThrown,
          responseText: jqXHR.responseText?.substring(0, 500),
        });

        let errorMessage = "Summary request failed.";

        if (textStatus === "timeout") {
          errorMessage = "Summary request timed out. Please try again.";
        } else if (jqXHR.status === 500) {
          errorMessage = "Server error. Please check your API configuration.";
        } else if (jqXHR.status === 0) {
          errorMessage = "Network error. Check your internet connection.";
        }

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

    // Show generating status
    articleTitleEl.textContent = "Generating audio...";
    articleTitleEl.style.color = "";

    const textToSpeak = getVisibleText();

    if (!textToSpeak || textToSpeak.trim().length < 10) {
      showError("No readable text found on this page.");
      return;
    }

    console.log(
      "AI Voice: Processing text length:",
      textToSpeak.length,
      "characters"
    );

    jQuery.ajax({
      url: aiVoiceData.ajax_url,
      type: "POST",
      data: {
        action: "ai_voice_generate_audio",
        nonce: aiVoiceData.nonce,
        post_id: aiVoiceData.post_id,
        text_to_speak: textToSpeak,
      },
      timeout: 180000, // 3 minutes timeout
      success: function (response) {
        console.log("AI Voice: Generation response:", response);

        if (response.success) {
          audio.src = response.data.audioUrl;
          clearError();

          // Auto-play after successful generation
          audio.play().catch((error) => {
            console.warn("AI Voice: Auto-play prevented by browser:", error);
            resetPlayerState();
          });

          generationAttempts = 0;
        } else {
          const errorMsg = response.data?.message || "Generation failed.";

          // Retry logic for certain errors
          if (
            generationAttempts < maxGenerationAttempts &&
            (errorMsg.includes("timeout") || errorMsg.includes("temporary"))
          ) {
            console.log(
              "AI Voice: Retrying generation, attempt",
              generationAttempts + 1
            );
            setTimeout(() => {
              isGenerating = false;
              generateAudio();
            }, 2000);
            return;
          }

          showError(errorMsg);
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        console.error("AI Voice: AJAX Error:", {
          status: jqXHR.status,
          textStatus,
          errorThrown,
          responseText: jqXHR.responseText?.substring(0, 500),
        });

        let errorMessage = "Request failed.";

        if (textStatus === "timeout") {
          errorMessage = "Request timed out. The text might be too long.";
        } else if (jqXHR.status === 524) {
          errorMessage = "Server timeout. Please try with shorter text.";
        } else if (jqXHR.status === 500) {
          errorMessage = "Server error. Please try again.";
        } else if (jqXHR.status === 0) {
          errorMessage = "Network error. Check your internet connection.";
        }

        // Retry for network/timeout errors
        if (
          generationAttempts < maxGenerationAttempts &&
          (textStatus === "timeout" ||
            jqXHR.status === 524 ||
            jqXHR.status === 0)
        ) {
          console.log(
            "AI Voice: Retrying due to",
            textStatus,
            "- attempt",
            generationAttempts + 1
          );
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

  const updateTheme = (theme) => {
    currentTheme = theme;
    playerContainer.dataset.theme = theme;
    if (theme === "dark") {
      sunIcon.style.display = "none";
      moonIcon.style.display = "block";
    } else {
      moonIcon.style.display = "none";
      sunIcon.style.display = "block";
    }
    updateProgressBarUI();
  };

  const updateAILogo = () => {
    const service = aiVoiceData.aiService || "google";

    // Fixed SVG paths
    if (service === "google") {
      aiLogoContainer.innerHTML = `
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
          <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
          <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
          <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        <span>Voiced by Google Cloud</span>`;
    } else if (service === "gemini") {
      aiLogoContainer.innerHTML = `
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 2L2 7v10c0 5.55 3.84 9.74 9 11 5.16-1.26 9-5.45 9-11V7l-10-5z"/>
          <path d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zm0 8c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3z"/>
        </svg>
        <span>Voiced by Gemini</span>`;
    } else if (service === "openai") {
      aiLogoContainer.innerHTML = `
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
          <path d="M22.282 9.821c-.29-2.737-2.2-4.648-4.937-4.937a5.85 5.85 0 0 0-4.546 1.567 5.85 5.85 0 0 0-4.546-1.567c-2.737.289-4.647 2.2-4.937 4.937a5.85 5.85 0 0 0 1.567 4.546 5.85 5.85 0 0 0-1.567 4.546c.29 2.737 2.2 4.647 4.937 4.937a5.85 5.85 0 0 0 4.546-1.567 5.85 5.85 0 0 0 4.546 1.567c2.737-.29 4.647-2.2 4.937-4.937a5.85 5.85 0 0 0-1.567-4.546 5.85 5.85 0 0 0 1.567-4.546zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"/>
        </svg>
        <span>Voiced by OpenAI</span>`;
    }
  };

  const setupSpeedModal = () => {
    const speedContainer = speedModal.querySelector("#ai-voice-speed-options");
    speedContainer.innerHTML = "";
    [0.75, 1.0, 1.25, 1.5, 2.0].forEach((s) => {
      const btn = document.createElement("button");
      btn.className = s === currentSpeed ? "active" : "";
      btn.textContent = `${s}x`;
      btn.onclick = () => {
        currentSpeed = s;
        audio.playbackRate = currentSpeed;
        speedBtn.textContent = `${s}x`;
        speedModal.style.display = "none";
        speedContainer
          .querySelectorAll("button")
          .forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
      };
      speedContainer.appendChild(btn);
    });
  };

  const setupVoiceModal = () => {
    const voiceContainer = voiceModal.querySelector("#ai-voice-voice-options");
    voiceContainer.innerHTML = "";
    const currentVoices = voices[aiVoiceData.aiService] || voices.google;
    currentVoices.forEach((voice, index) => {
      const btn = document.createElement("button");
      btn.className = index === 0 ? "active" : "";
      btn.textContent = voice.name;
      btn.onclick = () => {
        voiceModal.style.display = "none";
      };
      voiceContainer.appendChild(btn);
    });
  };

  // --- EVENT LISTENERS ---
  summaryBtn.addEventListener("click", toggleSummary);
  playPauseBtn.addEventListener("click", togglePlayPause);

  summaryClose.addEventListener("click", () => {
    summarySection.style.display = "none";
  });

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
  });

  audio.addEventListener("loadedmetadata", () => {
    totalTimeEl.textContent = formatTime(audio.duration);
    progressBar.max = audio.duration;
  });

  audio.addEventListener("timeupdate", () => {
    currentTimeEl.textContent = formatTime(audio.currentTime);
    progressBar.value = audio.currentTime;
    updateProgressBarUI();
  });

  audio.addEventListener("error", (e) => {
    console.error("AI Voice: Audio error:", e);
    showError("Audio playback failed. Please try regenerating.");
    audio.src = "";
  });

  audio.addEventListener("loadstart", () => {
    clearError();
  });

  progressBar.addEventListener("input", (e) => {
    if (audio.src && audio.duration) {
      audio.currentTime = parseFloat(e.target.value);
      updateProgressBarUI();
    }
  });

  themeToggle.addEventListener("click", () =>
    updateTheme(currentTheme === "light" ? "dark" : "light")
  );

  speedBtn.addEventListener("click", () => {
    setupSpeedModal();
    speedModal.style.display = "flex";
  });

  voiceBtn.addEventListener("click", () => {
    setupVoiceModal();
    voiceModal.style.display = "flex";
  });

  // Close modals when clicking outside
  speedModal.addEventListener("click", (e) => {
    if (e.target === speedModal) speedModal.style.display = "none";
  });

  voiceModal.addEventListener("click", (e) => {
    if (e.target === voiceModal) voiceModal.style.display = "none";
  });

  // Close modals on Escape key
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      speedModal.style.display = "none";
      voiceModal.style.display = "none";
    }
  });

  // --- INITIALIZATION ---
  updateTheme(currentTheme);
  updateAILogo();
  articleTitleEl.textContent = "Listen to the article";
  updateProgressBarUI();

  // Set initial playback rate
  audio.playbackRate = currentSpeed;

  console.log("AI Voice Player with Summary initialized successfully");
});
