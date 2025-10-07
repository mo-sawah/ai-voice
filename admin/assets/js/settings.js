// ✅ COMPLETE REWRITE - Smart Voice Selection System
jQuery(document).ready(function ($) {
  // Cache for voices to avoid repeated AJAX calls
  let voiceCache = {};
  let isLoadingVoices = false;

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

  // ✅ NEW: Fetch and populate voices based on language
  function fetchAndPopulateVoices(
    languageCode,
    voiceSelectId,
    forceRefresh = false
  ) {
    const $languageSelect = $(voiceSelectId.replace("_voice", "_language"));
    const $voiceSelect = $(voiceSelectId);
    const $fetchButton = $("#fetch_edge_voices");

    // Don't fetch for "default" language
    if (!languageCode || languageCode === "default") {
      console.log("AI Voice: Skipping voice fetch for default language");
      return;
    }

    // Check cache first (unless force refresh)
    if (!forceRefresh && voiceCache[languageCode]) {
      console.log("AI Voice: Using cached voices for " + languageCode);
      populateVoiceDropdown($voiceSelect, voiceCache[languageCode]);
      return;
    }

    // Prevent multiple simultaneous requests
    if (isLoadingVoices) {
      console.log("AI Voice: Already loading voices, skipping");
      return;
    }

    isLoadingVoices = true;

    // Show loading state
    const originalVoiceHTML = $voiceSelect.html();
    const originalButtonText = $fetchButton.text();

    $voiceSelect
      .prop("disabled", true)
      .html('<option value="">Loading...</option>');
    $fetchButton.prop("disabled", true).text("⏳ Loading voices...");

    console.log("AI Voice: Fetching voices for language: " + languageCode);

    $.ajax({
      url: aiVoiceAdmin.ajax_url,
      type: "POST",
      data: {
        action: "ai_voice_fetch_edge_voices",
        nonce: aiVoiceAdmin.nonce,
        language: languageCode,
        force_refresh: forceRefresh ? "true" : "false",
      },
      timeout: 15000,
      success: function (response) {
        if (response.success && response.data.grouped) {
          console.log(
            "AI Voice: ✅ Loaded " +
              response.data.total +
              " voices for " +
              languageCode
          );

          // Cache the result
          voiceCache[languageCode] = response.data.grouped;

          // Populate dropdown
          populateVoiceDropdown($voiceSelect, response.data.grouped);

          // Success feedback
          $fetchButton.text("✅ Voices loaded!");
          setTimeout(function () {
            $fetchButton.text(originalButtonText);
          }, 2000);
        } else {
          handleVoiceLoadError(
            $voiceSelect,
            $fetchButton,
            originalVoiceHTML,
            originalButtonText,
            response.data?.message || "Failed to load voices"
          );
        }
      },
      error: function (xhr, status, error) {
        let errorMsg = "Connection failed";
        if (status === "timeout") {
          errorMsg =
            "Server timeout - is your Edge TTS server running on localhost:6000?";
        } else if (xhr.status === 0) {
          errorMsg =
            "Cannot reach server - check if Edge TTS is running and firewall settings";
        }

        handleVoiceLoadError(
          $voiceSelect,
          $fetchButton,
          originalVoiceHTML,
          originalButtonText,
          errorMsg
        );
      },
      complete: function () {
        isLoadingVoices = false;
        $voiceSelect.prop("disabled", false);
        $fetchButton.prop("disabled", false);
      },
    });
  }

  // ✅ NEW: Populate voice dropdown with grouped voices
  function populateVoiceDropdown($select, groupedVoices) {
    const currentValue = $select.val();

    // Clear and add default option
    $select
      .empty()
      .append('<option value="default">Use Global Setting</option>');

    // Add voices grouped by locale
    groupedVoices.forEach(function (group) {
      const $optgroup = $(
        '<optgroup label="' + group.locale_name + '"></optgroup>'
      );

      group.voices.forEach(function (voice) {
        // Format display name: "Jenny (Female)" instead of long name
        const displayName = formatVoiceName(voice.displayName, voice.gender);

        const $option = $(
          '<option value="' + voice.name + '">' + displayName + "</option>"
        );
        $optgroup.append($option);
      });

      $select.append($optgroup);
    });

    // Restore previous selection if it exists
    if (
      currentValue &&
      $select.find('option[value="' + currentValue + '"]').length
    ) {
      $select.val(currentValue);
    }
  }

  // ✅ NEW: Format voice name for better readability
  function formatVoiceName(fullName, gender) {
    // Extract just the voice name without language prefix
    // "Microsoft Server Speech Text to Speech Voice (en-US, JennyNeural)"
    // becomes "Jenny (Female)"

    const match = fullName.match(/\(([^,]+),\s*([^)]+)\)/);
    if (match) {
      const voiceName = match[2].replace("Neural", "").trim();
      return voiceName + " (" + gender + ")";
    }

    // Fallback to full name
    return fullName;
  }

  // ✅ NEW: Handle voice loading errors
  function handleVoiceLoadError(
    $select,
    $button,
    originalHTML,
    originalButtonText,
    errorMsg
  ) {
    console.error("AI Voice: ❌ " + errorMsg);

    // Restore original options
    $select.html(originalHTML);

    // Show error message
    $button.text("❌ Error");
    alert(
      "Could not load voices from Edge TTS server:\n\n" +
        errorMsg +
        "\n\nMake sure your Python server is running:\npython final_edge_multilang_server.py"
    );

    setTimeout(function () {
      $button.text(originalButtonText);
    }, 3000);
  }

  // ===================================
  // MAIN SETTINGS PAGE
  // ===================================

  // TTS Service Selection
  const serviceSelect = $("#ai_voice_default_ai_service");
  if (serviceSelect.length) {
    toggleSettings("#ai_voice_default_ai_service");
    serviceSelect.on("change", function () {
      toggleSettings(this);
    });
  }

  // Summary API Selection
  const summaryApiSelect = $("#ai_voice_summary_api");
  if (summaryApiSelect.length) {
    toggleSummarySettings("#ai_voice_summary_api");
    summaryApiSelect.on("change", function () {
      toggleSummarySettings(this);
    });
  }

  // ✅ Edge TTS Language Change Handler (Settings Page)
  $("#edge_language").on("change", function () {
    const selectedLanguage = $(this).val();
    console.log("AI Voice: Language changed to " + selectedLanguage);

    if (selectedLanguage && selectedLanguage !== "default") {
      fetchAndPopulateVoices(selectedLanguage, "#edge_voice", false);
    } else {
      // Reset to default voices if "default" selected
      $("#edge_voice")
        .empty()
        .append(
          '<option value="en-US-JennyNeural">Jenny (Female, US)</option>' +
            '<option value="en-US-GuyNeural">Guy (Male, US)</option>' +
            '<option value="en-US-AriaNeural">Aria (Female, US)</option>'
        );
    }
  });

  // ✅ Refresh Voices Button Handler (Settings Page)
  $("#fetch_edge_voices").on("click", function (e) {
    e.preventDefault();
    const selectedLanguage = $("#edge_language").val();

    if (!selectedLanguage || selectedLanguage === "default") {
      alert(
        "Please select a language first before refreshing voices.\n\nThe server needs to know which language to fetch voices for."
      );
      return;
    }

    console.log("AI Voice: Force refreshing voices for " + selectedLanguage);
    fetchAndPopulateVoices(
      selectedLanguage !== "default" ? selectedLanguage : "",
      "#edge_voice",
      true
    );
  });

  // ✅ Auto-load voices on page load if Edge TTS selected (Settings Page)
  if (serviceSelect.val() === "local" && $("#edge_voice option").length <= 3) {
    const language = $("#edge_language").val();
    if (language && language !== "default") {
      console.log(
        "AI Voice: Auto-loading voices for " + language + " on page load"
      );
      // Small delay to ensure page is fully loaded
      setTimeout(function () {
        fetchAndPopulateVoices(language, "#edge_voice", false);
      }, 500);
    }
  }

  // ===================================
  // POST METABOX (Edit Post Screen)
  // ===================================

  // TTS Service Selection in Metabox
  const serviceSelectPost = $("#ai_voice_ai_service");
  if (serviceSelectPost.length) {
    const postbox = serviceSelectPost.closest(".inside");
    toggleSettings("#ai_voice_ai_service", postbox);
    serviceSelectPost.on("change", function () {
      toggleSettings(this, postbox);
    });
  }

  // ✅ Edge TTS Language Change Handler (Metabox)
  $("#ai_voice_edge_language").on("change", function () {
    const selectedLanguage = $(this).val();
    console.log("AI Voice (Metabox): Language changed to " + selectedLanguage);

    if (selectedLanguage && selectedLanguage !== "default") {
      fetchAndPopulateVoices(selectedLanguage, "#ai_voice_edge_voice", false);
    }
  });

  // ✅ Auto-load voices in metabox if Edge TTS selected
  if (
    serviceSelectPost.val() === "local" &&
    $("#ai_voice_edge_voice option").length <= 3
  ) {
    const language = $("#ai_voice_edge_language").val();
    if (language && language !== "default") {
      console.log(
        "AI Voice (Metabox): Auto-loading voices for " +
          language +
          " on page load"
      );
      setTimeout(function () {
        fetchAndPopulateVoices(language, "#ai_voice_edge_voice", false);
      }, 500);
    }
  }

  // ===================================
  // SERVER HEALTH CHECK (Optional)
  // ===================================
  function checkServerHealth() {
    const $healthIndicator = $("#edge_tts_health_status");
    if (!$healthIndicator.length) return;

    $.ajax({
      url: aiVoiceAdmin.ajax_url,
      type: "POST",
      data: {
        action: "ai_voice_check_edge_server",
        nonce: aiVoiceAdmin.nonce,
      },
      timeout: 3000,
      success: function (response) {
        if (response.success) {
          $healthIndicator
            .removeClass("error")
            .addClass("success")
            .html("✅ Server is running");
        } else {
          $healthIndicator
            .removeClass("success")
            .addClass("error")
            .html("❌ Server offline");
        }
      },
      error: function () {
        $healthIndicator
          .removeClass("success")
          .addClass("error")
          .html("❌ Cannot reach server");
      },
    });
  }

  // Check server health on page load if on Edge TTS settings
  if ($("#ai_voice_default_ai_service").val() === "local") {
    setTimeout(checkServerHealth, 1000);
  }

  console.log("AI Voice: Smart voice selection system initialized ✅");
});
