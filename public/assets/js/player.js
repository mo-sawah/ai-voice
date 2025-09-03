document.addEventListener("DOMContentLoaded", () => {
  const playerWrapper = document.getElementById("ai-voice-player-wrapper");
  if (!playerWrapper || typeof aiVoiceData === "undefined") {
    return;
  }

  // --- DOM ELEMENTS ---
  const playerContainer = playerWrapper.querySelector(
    ".ai-voice-player-container"
  );
  const playPauseBtn = playerWrapper.querySelector("#ai-voice-play-pause-btn");
  const playIcon = playerWrapper.querySelector("#ai-voice-play-icon");
  const pauseIcon = playerWrapper.querySelector("#ai-voice-pause-icon");
  const progressBar = playerWrapper.querySelector("#ai-voice-progress-bar");
  const currentTimeEl = playerWrapper.querySelector("#ai-voice-current-time");
  const totalTimeEl = playerWrapper.querySelector("#ai-voice-total-time");
  const themeToggle = playerWrapper.querySelector("#ai-voice-theme-toggle");
  const sunIcon = playerWrapper.querySelector("#ai-voice-sun-icon");
  const moonIcon = playerWrapper.querySelector("#ai-voice-moon-icon");
  const speedBtn = playerWrapper.querySelector("#ai-voice-speed-btn");
  const aiLogoContainer = playerWrapper.querySelector(
    "#ai-voice-logo-container"
  );
  const articleTitleEl = playerWrapper.querySelector("#ai-voice-article-title");

  // --- AUDIO & STATE ---
  const audio = new Audio();
  let isPlaying = false;
  let isGenerating = false;
  let currentTheme = aiVoiceData.theme || "light";
  let currentSpeed = 1.0;

  // --- FUNCTIONS ---
  const formatTime = (seconds) => {
    if (isNaN(seconds)) return "0:00";
    const minutes = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${minutes}:${secs.toString().padStart(2, "0")}`;
  };

  const updateProgressBarUI = () => {
    const percentage = (audio.currentTime / audio.duration) * 100;
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

    // If audio source is not set, generate it first.
    if (!audio.src) {
      generateAudio();
      return;
    }

    if (audio.paused) {
      audio.play();
    } else {
      audio.pause();
    }
  };

  const generateAudio = () => {
    isGenerating = true;
    playPauseBtn.disabled = true;
    const originalTitle = articleTitleEl.textContent;
    articleTitleEl.textContent =
      aiVoiceData.generatingText || "Generating Audio...";
    playIcon.style.display = "none";
    pauseIcon.style.display = "none";

    // Use jQuery for WordPress AJAX compatibility
    jQuery.post(
      aiVoiceData.ajax_url,
      {
        action: "ai_voice_generate_audio",
        nonce: aiVoiceData.nonce,
        post_id: aiVoiceData.post_id,
      },
      function (response) {
        isGenerating = false;
        playPauseBtn.disabled = false;
        if (response.success) {
          audio.src = response.data.audioUrl;
          audio.play();
          articleTitleEl.textContent = originalTitle;
        } else {
          articleTitleEl.textContent = aiVoiceData.errorText || "Error";
          console.error("AI Voice Error:", response.data.message);
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

  const updateAILogo = () => {
    if (aiVoiceData.aiService === "google") {
      aiLogoContainer.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M21.35,11.1H12.18V13.83H18.69C18.36,17.64 15.19,19.27 12.19,19.27C8.36,19.27 5,16.25 5,12C5,7.9 8.2,4.73 12.19,4.73C15.29,4.73 17.1,6.7 17.1,6.7L19,4.72C19,4.t2 16.56,2 12.1,2C6.42,2 2.03,6.8 2.03,12C2.03,17.05 6.16,22 12.25,22C17.6,22 21.5,18.33 21.5,12.91C21.5,11.76 21.35,11.1 21.35,11.1V11.1Z"></path></svg><span>Voiced by Google AI</span>`;
    } else {
      aiLogoContainer.innerHTML = `<svg width="18" height="18" viewBox="0 0 41 41" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M35.65,10.96a16.5,16.5,0,0,0-23.33,0L5,18.29l7.33-7.33a16.5,16.5,0,0,0,23.33,0l4.95,4.95-4.95-4.95ZM12.33,29.32,5,22,18.29,35.31a16.48,16.48,0,0,0,11-4.8L22,22Z" ></path></svg><span>Voiced by OpenAI</span>`;
    }
  };

  // --- EVENT LISTENERS ---
  playPauseBtn.addEventListener("click", togglePlayPause);

  audio.addEventListener("play", () => {
    playIcon.style.display = "none";
    pauseIcon.style.display = "block";
  });

  audio.addEventListener("pause", () => {
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

  themeToggle.addEventListener("click", () => {
    const newTheme = currentTheme === "light" ? "dark" : "light";
    updateTheme(newTheme);
  });

  speedBtn.addEventListener("click", () => {
    const speeds = [1.0, 1.25, 1.5, 2.0, 0.75, 0.5];
    const nextIndex = (speeds.indexOf(currentSpeed) + 1) % speeds.length;
    currentSpeed = speeds[nextIndex];
    audio.playbackRate = currentSpeed;
    speedBtn.textContent = `${currentSpeed}x`;
  });

  // --- INITIALIZATION ---
  updateTheme(currentTheme);
  updateAILogo();
  articleTitleEl.textContent = aiVoiceData.title;
});
