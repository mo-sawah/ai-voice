// MODIFIED settings.js - Replace your existing settings.js with this version
jQuery(document).ready(function ($) {
  // Toggle TTS service-specific settings
  function toggleSettings(serviceSelector, postbox) {
    const selectedService = $(serviceSelector).val();
    const context = postbox ? $(postbox) : $(serviceSelector).closest("form");
    context.find(".ai-voice-setting-row, .ai-voice-setting-row-postbox").hide();
    context.find(`[data-service="${selectedService}"]`).show();
  }

  // Toggle summary API settings
  function toggleSummarySettings(apiSelector) {
    const selectedApi = $(apiSelector).val();
    const context = $(apiSelector).closest("form");
    context.find(".ai-voice-summary-row").hide();
    context.find(`[data-api="${selectedApi}"]`).show();
  }

  // NEW: Fetch voices from Edge TTS server
  function fetchEdgeVoices(language) {
    const $voiceSelect = $("#edge_voice");
    const $fetchButton = $("#fetch_edge_voices");

    // Show loading state
    $fetchButton.prop("disabled", true).text("Loading...");
    $voiceSelect.prop("disabled", true);

    $.ajax({
      url: aiVoiceAdmin.ajax_url,
      type: "POST",
      data: {
        action: "ai_voice_fetch_edge_voices",
        nonce: aiVoiceAdmin.nonce,
        language: language || "",
      },
      success: function (response) {
        if (response.success && response.data.voices) {
          const voices = response.data.voices;
          const currentVoice = $voiceSelect.val();

          // Clear and rebuild voice dropdown
          $voiceSelect.empty();
          $voiceSelect.append(
            '<option value="default">Use Global Setting</option>'
          );

          voices.forEach(function (voice) {
            const selected = voice.name === currentVoice ? "selected" : "";
            $voiceSelect.append(
              `<option value="${voice.name}" ${selected}>${voice.displayName}</option>`
            );
          });

          // Show success message
          $fetchButton.text("✓ Voices Loaded");
          setTimeout(function () {
            $fetchButton.text("Refresh Voices from Server");
          }, 2000);
        } else {
          alert(
            "Could not fetch voices from the TTS server. Make sure the server is running on " +
              $("#local_tts_url").val()
          );
          $fetchButton.text("Refresh Voices from Server");
        }
      },
      error: function () {
        alert(
          "Error connecting to TTS server. Make sure it's running and the URL is correct."
        );
        $fetchButton.text("Refresh Voices from Server");
      },
      complete: function () {
        $fetchButton.prop("disabled", false);
        $voiceSelect.prop("disabled", false);
      },
    });
  }

  // For main settings page - TTS services
  const serviceSelect = $("#ai_voice_default_ai_service");
  if (serviceSelect.length) {
    toggleSettings("#ai_voice_default_ai_service");
    serviceSelect.on("change", function () {
      toggleSettings(this);
    });
  }

  // For main settings page - Summary API
  const summaryApiSelect = $("#ai_voice_summary_api");
  if (summaryApiSelect.length) {
    toggleSummarySettings("#ai_voice_summary_api");
    summaryApiSelect.on("change", function () {
      toggleSummarySettings(this);
    });
  }

  // For post edit page (metabox)
  const serviceSelectPost = $("#ai_voice_ai_service");
  if (serviceSelectPost.length) {
    const postbox = serviceSelectPost.closest(".inside");
    toggleSettings("#ai_voice_ai_service", postbox);
    serviceSelectPost.on("change", function () {
      toggleSettings(this, postbox);
    });
  }

  // NEW: Handle Edge TTS language change
  $("#edge_language").on("change", function () {
    const selectedLanguage = $(this).val();
    if (selectedLanguage && selectedLanguage !== "default") {
      fetchEdgeVoices(selectedLanguage);
    }
  });

  // NEW: Handle "Refresh Voices from Server" button
  $("#fetch_edge_voices").on("click", function (e) {
    e.preventDefault();
    const selectedLanguage = $("#edge_language").val();
    fetchEdgeVoices(selectedLanguage !== "default" ? selectedLanguage : "");
  });

  // Auto-load voices on page load if Edge TTS is selected
  if (
    $("#ai_voice_default_ai_service").val() === "local" &&
    $("#edge_voice option").length <= 2
  ) {
    // Only auto-load if there are very few voices (just default option)
    const language = $("#edge_language").val();
    if (language && language !== "default") {
      fetchEdgeVoices(language);
    }
  }
});
