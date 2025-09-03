jQuery(document).ready(function ($) {
  console.log("AI Voice Admin Settings JS loaded");

  function toggleSettings(serviceSelector, postbox) {
    const selectedService = $(serviceSelector).val();
    console.log("Selected service:", selectedService);

    const context = postbox ? $(postbox) : $(serviceSelector).closest("form");

    // Hide all service-specific settings
    context.find(".ai-voice-setting-row, .ai-voice-setting-row-postbox").hide();

    // Show settings for selected service
    const elementsToShow = context.find(`[data-service="${selectedService}"]`);
    console.log("Elements to show:", elementsToShow.length);
    elementsToShow.show();
  }

  // For main settings page
  const serviceSelect = $("#ai_voice_default_ai_service");
  if (serviceSelect.length) {
    console.log("Main settings service select found");
    toggleSettings("#ai_voice_default_ai_service");
    serviceSelect.on("change", function () {
      toggleSettings(this);
    });
  }

  // For post edit page (metabox)
  const serviceSelectPost = $("#ai_voice_ai_service");
  if (serviceSelectPost.length) {
    console.log("Post metabox service select found");
    const postbox = serviceSelectPost.closest(".inside");
    toggleSettings("#ai_voice_ai_service", postbox);
    serviceSelectPost.on("change", function () {
      toggleSettings(this, postbox);
    });
  }
});
