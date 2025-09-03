document.addEventListener("DOMContentLoaded", () => {
  const wrapper = document.getElementById("ai-voice-player-wrapper");
  if (!wrapper || typeof aiVoiceData === "undefined") return;

  // --- DOM ELEMENTS (no changes) ---
  const playerContainer = wrapper.querySelector(".ai-voice-player-container");
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

  // --- AUDIO & STATE (no changes) ---
  const audio = new Audio();
  let isGenerating = false;
  let currentTheme = aiVoiceData.theme || "light";
  let currentSpeed = 1.0;
  const voices = {
    google: [
      { id: "en-US-Studio-O", name: "Studio (Female)" },
      { id: "en-US-Neural2-J", name: "Neural (Male)" },
      { id: "en-US-Wavenet-F", name: "WaveNet (Female)" },
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

  // --- FUNCTIONS (with updates) ---
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
    const accentColor =
      currentTheme === "light" ? "var(--accent-light)" : "var(--accent-dark)";
    const bgColor =
      currentTheme === "light"
        ? "var(--bg-secondary-light)"
        : "var(--bg-secondary-dark)";
    progressBar.style.background = `linear-gradient(to right, ${accentColor} ${percentage}%, ${bgColor} ${percentage}%)`;
  };

  const togglePlayPause = () => {
    if (isGenerating) return;
    if (!audio.src) {
      generateAudio();
      return;
    }
    audio.paused ? audio.play() : audio.pause();
  };

  const getVisibleText = () => {
    // Find the most likely main content area of the article
    let contentNode = wrapper.closest("article, .post, .entry-content, main");
    if (!contentNode) {
      // Fallback to the player's parent if a specific container isn't found
      contentNode = wrapper.parentElement;
    }

    // Clone the node to avoid modifying the live page
    const clone = contentNode.cloneNode(true);

    // Remove the player itself and any scripts from the cloned content
    const playerClone = clone.querySelector("#ai-voice-player-wrapper");
    if (playerClone) playerClone.remove();
    clone
      .querySelectorAll('script, style, noscript, .ads, [aria-hidden="true"]')
      .forEach((el) => el.remove());

    // Return the cleaned, visible text
    return clone.textContent.replace(/\s+/g, " ").trim();
  };

  const generateAudio = () => {
    isGenerating = true;
    playPauseBtn.disabled = true;
    playIcon.style.display = "none";
    loader.style.display = "block";

    const textToSpeak = getVisibleText();

    if (!textToSpeak) {
      isGenerating = false;
      playPauseBtn.disabled = false;
      loader.style.display = "none";
      articleTitleEl.textContent = "No text found on page.";
      playIcon.style.display = "block";
      return;
    }

    jQuery.post(
      aiVoiceData.ajax_url,
      {
        action: "ai_voice_generate_audio",
        nonce: aiVoiceData.nonce,
        post_id: aiVoiceData.post_id,
        text_to_speak: textToSpeak,
      },
      function (response) {
        isGenerating = false;
        playPauseBtn.disabled = false;
        loader.style.display = "none";
        if (response.success) {
          audio.src = response.data.audioUrl;
          audio.play();
        } else {
          articleTitleEl.textContent = "Error Generating Audio";
          console.error("AI Voice Error:", response.data.message);
          playIcon.style.display = "block";
        }
      }
    );
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

  // --- Other functions (updateAILogo, setupSpeedModal, setupVoiceModal) are unchanged ---
  const updateAILogo = () => {
    const service = aiVoiceData.aiService || "google";
    if (service === "google") {
      aiLogoContainer.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M21.35,11.1H12.18V13.83H18.69C18.36,17.64 15.19,19.27 12.19,19.27C8.36,19.27 5,16.25 5,12C5,7.9 8.2,4.73 12.19,4.73C15.29,4.73 17.1,6.7 17.1,6.7L19,4.72C19,4.t2 16.56,2 12.1,2C6.42,2 2.03,6.8 2.03,12C2.03,17.05 6.16,22 12.25,22C17.6,22 21.5,18.33 21.5,12.91C21.5,11.76 21.35,11.1 21.35,11.1V11.1Z"></path></svg><span>Voiced by Google AI</span>`;
    } else {
      aiLogoContainer.innerHTML = `<svg width="18" height="18" viewBox="0 0 41 41" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M35.65,10.96a16.5,16.5,0,0,0-23.33,0L5,18.29l7.33-7.33a16.5,16.5,0,0,0,23.33,0l4.95,4.95-4.95-4.95ZM12.33,29.32,5,22,18.29,35.31a16.48,16.48,0,0,0,11-4.8L22,22Z" ></path></svg><span>Voiced by OpenAI</span>`;
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
      voiceContainer.appendChild(btn);
    });
  };

  // --- EVENT LISTENERS (no changes) ---
  playPauseBtn.addEventListener("click", togglePlayPause);
  audio.addEventListener("play", () => {
    playIcon.style.display = "none";
    pauseIcon.style.display = "block";
  });
  audio.addEventListener("pause", () => {
    pauseIcon.style.display = "none";
    playIcon.style.display = "block";
  });
  audio.addEventListener("ended", () => {
    audio.currentTime = 0;
    pauseIcon.style.display = "none";
    playIcon.style.display = "block";
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
  progressBar.addEventListener("input", (e) => {
    if (audio.src) {
      audio.currentTime = e.target.value;
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
  speedModal.addEventListener("click", (e) => {
    if (e.target === speedModal) speedModal.style.display = "none";
  });
  voiceModal.addEventListener("click", (e) => {
    if (e.target === voiceModal) voiceModal.style.display = "none";
  });

  // --- INITIALIZATION (simplified) ---
  updateTheme(currentTheme);
  updateAILogo();
  articleTitleEl.textContent = aiVoiceData.title;
  updateProgressBarUI();
});
