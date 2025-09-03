jQuery(document).ready(function ($) {
  function toggleSettings(serviceSelector, postbox) {
    const selectedService = $(serviceSelector).val();

    const context = postbox ? $(postbox) : $(serviceSelector).closest("form");

    context.find(".ai-voice-setting-row, .ai-voice-setting-row-postbox").hide();
    context.find(`[data-service="${selectedService}"]`).show();
  }

  // For main settings page
  const serviceSelect = $("#ai_voice_default_ai_service");
  if (serviceSelect.length) {
    toggleSettings("#ai_voice_default_ai_service");
    serviceSelect.on("change", function () {
      toggleSettings(this);
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
});
