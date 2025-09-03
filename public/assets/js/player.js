document.addEventListener("DOMContentLoaded", function () {
  const player = document.getElementById("ai-voice-player");
  if (!player || typeof aiVoiceData === "undefined") return;

  const playBtn = document.getElementById("ai-voice-play-btn");
  const playIcon = playBtn.querySelector(".play-icon");
  const pauseIcon = playBtn.querySelector(".pause-icon");
  const loader = playBtn.querySelector(".loader");
  const progressBar = player.querySelector(".progress-bar");
  const currentTimeEl = player.querySelector(".current-time");
  const totalTimeEl = player.querySelector(".total-time");
  const speedBtn = player.querySelector(".speed-btn");

  let audio = null;
  let isGenerating = false;
  let currentSpeed = 1.0;
  const speeds = [0.75, 1.0, 1.25, 1.5, 2.0];

  // Format time helper
  function formatTime(seconds) {
    if (isNaN(seconds) || seconds < 0) return "0:00";
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return mins + ":" + (secs < 10 ? "0" : "") + secs;
  }

  // Update progress bar background
  function updateProgressBar() {
    if (!audio || !audio.duration) return;

    const percentage = (audio.currentTime / audio.duration) * 100;
    const primaryColor = getComputedStyle(player)
      .getPropertyValue("--primary-color")
      .trim();
    const secondaryBg = getComputedStyle(player)
      .getPropertyValue("--secondary-bg")
      .trim();

    progressBar.style.background = `linear-gradient(to right, ${primaryColor} ${percentage}%, ${secondaryBg} ${percentage}%)`;
  }

  // Play/pause toggle
  playBtn.addEventListener("click", function () {
    if (isGenerating) return;

    if (!audio) {
      generateAudio();
    } else if (audio.paused) {
      audio.play();
    } else {
      audio.pause();
    }
  });

  // Generate audio via AJAX
  function generateAudio() {
    isGenerating = true;
    playBtn.disabled = true;
    playIcon.style.display = "none";
    pauseIcon.style.display = "none";
    loader.style.display = "block";

    // Use jQuery if available, otherwise use fetch
    if (typeof jQuery !== "undefined") {
      jQuery.ajax({
        url: aiVoiceData.ajax_url,
        type: "POST",
        data: {
          action: "ai_voice_generate",
          nonce: aiVoiceData.nonce,
          post_id: aiVoiceData.post_id,
        },
        timeout: 60000,
        success: function (response) {
          isGenerating = false;
          playBtn.disabled = false;
          loader.style.display = "none";

          if (response.success && response.data.audio_url) {
            createAudioPlayer(response.data.audio_url);
          } else {
            showError(response.data || "Audio generation failed");
            playIcon.style.display = "block";
          }
        },
        error: function (xhr, status, error) {
          isGenerating = false;
          playBtn.disabled = false;
          loader.style.display = "none";
          console.error("AJAX Error:", status, error);
          showError("Request failed. Please try again.");
          playIcon.style.display = "block";
        },
      });
    } else {
      // Fallback to fetch API if jQuery not available
      fetch(aiVoiceData.ajax_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
          action: "ai_voice_generate",
          nonce: aiVoiceData.nonce,
          post_id: aiVoiceData.post_id,
        }),
      })
        .then((response) => response.json())
        .then((data) => {
          isGenerating = false;
          playBtn.disabled = false;
          loader.style.display = "none";

          if (data.success && data.data.audio_url) {
            createAudioPlayer(data.data.audio_url);
          } else {
            showError(data.data || "Audio generation failed");
            playIcon.style.display = "block";
          }
        })
        .catch((error) => {
          isGenerating = false;
          playBtn.disabled = false;
          loader.style.display = "none";
          console.error("Fetch Error:", error);
          showError("Request failed. Please try again.");
          playIcon.style.display = "block";
        });
    }
  }

  // Create audio player
  function createAudioPlayer(audioUrl) {
    audio = new Audio(audioUrl);
    audio.playbackRate = currentSpeed;

    // Audio event listeners
    audio.addEventListener("loadedmetadata", function () {
      totalTimeEl.textContent = formatTime(audio.duration);
      progressBar.max = audio.duration;
    });

    audio.addEventListener("timeupdate", function () {
      currentTimeEl.textContent = formatTime(audio.currentTime);
      progressBar.value = audio.currentTime;
      updateProgressBar();
    });

    audio.addEventListener("play", function () {
      playIcon.style.display = "none";
      pauseIcon.style.display = "block";
    });

    audio.addEventListener("pause", function () {
      pauseIcon.style.display = "block";
      playIcon.style.display = "none";
    });

    audio.addEventListener("ended", function () {
      audio.currentTime = 0;
      pauseIcon.style.display = "none";
      playIcon.style.display = "block";
      updateProgressBar();
    });

    audio.addEventListener("error", function (e) {
      console.error("Audio error:", e);
      showError("Audio playback failed");
      pauseIcon.style.display = "none";
      playIcon.style.display = "block";
    });

    // Start playing
    audio.play().catch(function (error) {
      console.error("Playback failed:", error);
      showError("Playback failed - " + error.message);
      pauseIcon.style.display = "none";
      playIcon.style.display = "block";
    });
  }

  // Progress bar scrubbing
  progressBar.addEventListener("input", function () {
    if (audio && audio.duration) {
      audio.currentTime = parseFloat(this.value);
      updateProgressBar();
    }
  });

  // Progress bar mouse events for better UX
  progressBar.addEventListener("mousedown", function () {
    if (audio && !audio.paused) {
      audio.pause();
      this.dataset.wasPlaying = "true";
    }
  });

  progressBar.addEventListener("mouseup", function () {
    if (audio && this.dataset.wasPlaying === "true") {
      audio.play();
      delete this.dataset.wasPlaying;
    }
  });

  // Speed control
  speedBtn.addEventListener("click", function (e) {
    e.stopPropagation();
    showSpeedModal();
  });

  function showSpeedModal() {
    // Remove existing modal if any
    const existingModal = document.getElementById("ai-voice-speed-modal");
    if (existingModal) {
      existingModal.remove();
    }

    // Create new modal
    const modal = document.createElement("div");
    modal.id = "ai-voice-speed-modal";
    modal.className = "speed-modal";

    const content = document.createElement("div");
    content.className = "speed-modal-content";
    modal.appendChild(content);

    speeds.forEach((speed) => {
      const btn = document.createElement("button");
      btn.className =
        "speed-option" + (speed === currentSpeed ? " active" : "");
      btn.textContent = speed + "x";
      btn.addEventListener("click", function () {
        currentSpeed = speed;
        speedBtn.textContent = speed + "x";
        if (audio) {
          audio.playbackRate = speed;
        }
        modal.remove();
      });
      content.appendChild(btn);
    });

    document.body.appendChild(modal);
    modal.style.display = "flex";

    // Close on outside click
    modal.addEventListener("click", function (e) {
      if (e.target === modal) {
        modal.remove();
      }
    });

    // Close on escape key
    function handleEscape(e) {
      if (e.key === "Escape") {
        modal.remove();
        document.removeEventListener("keydown", handleEscape);
      }
    }
    document.addEventListener("keydown", handleEscape);

    // Auto-close after 5 seconds
    setTimeout(function () {
      if (document.contains(modal)) {
        modal.remove();
      }
    }, 5000);
  }

  // Error display
  function showError(message) {
    const title = player.querySelector(".title");
    const originalText = title.textContent;
    const originalColor = title.style.color;

    title.textContent = "Error: " + message;
    title.style.color = "#ef4444";

    setTimeout(function () {
      title.textContent = originalText;
      title.style.color = originalColor;
    }, 5000);
  }

  // Keyboard shortcuts
  document.addEventListener("keydown", function (e) {
    // Only if player is visible and focused area
    if (!player.offsetParent) return;

    switch (e.code) {
      case "Space":
        // Prevent if user is typing in an input
        if (e.target.tagName === "INPUT" || e.target.tagName === "TEXTAREA")
          return;
        e.preventDefault();
        playBtn.click();
        break;
      case "ArrowLeft":
        if (audio && e.target.tagName !== "INPUT") {
          e.preventDefault();
          audio.currentTime = Math.max(0, audio.currentTime - 10);
        }
        break;
      case "ArrowRight":
        if (audio && e.target.tagName !== "INPUT") {
          e.preventDefault();
          audio.currentTime = Math.min(audio.duration, audio.currentTime + 10);
        }
        break;
    }
  });

  // Initialize progress bar
  updateProgressBar();

  // Handle page visibility changes (pause when tab is hidden)
  document.addEventListener("visibilitychange", function () {
    if (audio && !audio.paused && document.hidden) {
      audio.pause();
    }
  });

  // Clean up on page unload
  window.addEventListener("beforeunload", function () {
    if (audio) {
      audio.pause();
      audio.src = "";
    }
  });
});
