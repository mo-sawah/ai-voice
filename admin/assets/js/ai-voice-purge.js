(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var purgeBtn = document.getElementById("ai-voice-purge-btn");
    var refreshBtn = document.getElementById("ai-voice-purge-refresh");
    var resultBox = document.getElementById("ai-voice-purge-result");

    if (!purgeBtn) return;

    var spinner = purgeBtn.querySelector(".ai-voice-purge-spinner");

    function setLoading(loading) {
      purgeBtn.disabled = loading;
      if (spinner) {
        if (loading) spinner.classList.add("is-active");
        else spinner.classList.remove("is-active");
      }
    }

    function renderResult(data, isError) {
      if (!resultBox) return;
      resultBox.style.display = "block";
      if (isError) {
        resultBox.innerHTML =
          '<p><strong style="color:#b91c1c">Error:</strong> ' +
          (data && data.message ? String(data.message) : "Unknown error") +
          "</p>";
        return;
      }
      var errorsHtml = "";
      if (Array.isArray(data.errors) && data.errors.length) {
        errorsHtml =
          "<p><strong>Errors:</strong></p><ul>" +
          data.errors
            .map(function (e) {
              return "<li>" + String(e) + "</li>";
            })
            .join("") +
          "</ul>";
      }
      resultBox.innerHTML =
        "<p><strong>Purge completed.</strong></p>" +
        "<ul>" +
        "<li>Deleted attachments: " +
        Number(data.deleted_attachments || 0) +
        "</li>" +
        "<li>Deleted leftover files: " +
        Number(data.deleted_leftover_files || 0) +
        "</li>" +
        "<li>Deleted postmeta rows: " +
        Number(data.deleted_postmeta_rows || 0) +
        "</li>" +
        "</ul>" +
        errorsHtml;
    }

    purgeBtn.addEventListener("click", function () {
      if (
        !window.confirm(
          "This will permanently delete all generated AI Voice audio and cached summaries. Continue?"
        )
      ) {
        return;
      }
      resultBox.style.display = "none";
      resultBox.innerHTML = "";
      setLoading(true);

      jQuery.ajax({
        url: aiVoicePurgeData.ajax_url,
        type: "POST",
        dataType: "json",
        data: { action: "ai_voice_purge_cache", nonce: aiVoicePurgeData.nonce },
        timeout: 180000,
        success: function (res) {
          if (res && res.success) renderResult(res.data, false);
          else
            renderResult(
              (res && res.data) || { message: "Unknown error" },
              true
            );
        },
        error: function (jqXHR, textStatus) {
          var msg = "Request failed.";
          if (textStatus === "timeout") msg = "Request timed out.";
          else if (jqXHR.status === 403)
            msg = "Permission denied or invalid nonce.";
          else if (jqXHR.status === 500) msg = "Server error.";
          renderResult({ message: msg }, true);
        },
        complete: function () {
          setLoading(false);
        },
      });
    });

    if (refreshBtn) {
      refreshBtn.addEventListener("click", function () {
        window.location.reload();
      });
    }
  });
})();
