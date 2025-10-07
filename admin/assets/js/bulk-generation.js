/**
 * AI Voice Bulk Generation JavaScript
 * Save as: wp-content/plugins/ai-voice/admin/assets/js/bulk-generation.js
 */

jQuery(document).ready(function ($) {
  let isProcessing = false;
  let isPaused = false;
  let processedCount = 0;
  let totalCount = 0;
  let rateLimit = 60; // seconds
  let startTime = null;

  // Load stats on page load
  loadStats();

  // Refresh stats button
  $("#refresh-stats").on("click", function () {
    $(this).prop("disabled", true).text("🔄 Loading...");
    loadStats().always(function () {
      $("#refresh-stats").prop("disabled", false).text("🔄 Refresh Stats");
    });
  });

  // Start bulk generation
  $("#start-bulk-btn").on("click", function () {
    if (
      !confirm(
        "This will generate audio for all pending posts. This may take a while. Continue?"
      )
    ) {
      return;
    }

    const regenerate = $("#regenerate-existing").is(":checked");

    $(this).prop("disabled", true);
    addLog("Starting bulk generation...", "info");

    $.ajax({
      url: aiVoiceBulk.ajax_url,
      type: "POST",
      data: {
        action: "ai_voice_start_bulk",
        nonce: aiVoiceBulk.nonce,
        regenerate: regenerate ? "1" : "0",
      },
      success: function (response) {
        if (response.success) {
          totalCount = response.data.total;
          processedCount = 0;
          startTime = Date.now();

          addLog(`Queue created: ${totalCount} posts to process`, "success");

          // Update UI
          $("#total-count").text(totalCount);
          $("#processed-count").text(0);
          $("#queue-status")
            .text("Running")
            .removeClass("status-idle")
            .addClass("status-running");

          // Show progress bar and controls
          $(".progress-container").show();
          $("#start-bulk-btn").hide();
          $("#pause-bulk-btn, #stop-bulk-btn").show();

          // Start processing
          isProcessing = true;
          isPaused = false;
          processNext();
        } else {
          addLog("Error: " + response.data.message, "error");
          $("#start-bulk-btn").prop("disabled", false);
        }
      },
      error: function () {
        addLog("Failed to start bulk generation", "error");
        $("#start-bulk-btn").prop("disabled", false);
      },
    });
  });

  // Pause button
  $("#pause-bulk-btn").on("click", function () {
    isPaused = true;
    $(this).hide();
    $("#resume-bulk-btn").show();
    $("#queue-status")
      .text("Paused")
      .removeClass("status-running")
      .addClass("status-paused");

    $.ajax({
      url: aiVoiceBulk.ajax_url,
      type: "POST",
      data: {
        action: "ai_voice_pause_bulk",
        nonce: aiVoiceBulk.nonce,
      },
    });

    addLog("Queue paused", "info");
  });

  // Resume button
  $("#resume-bulk-btn").on("click", function () {
    isPaused = false;
    $(this).hide();
    $("#pause-bulk-btn").show();
    $("#queue-status")
      .text("Running")
      .removeClass("status-paused")
      .addClass("status-running");

    addLog("Queue resumed", "info");
    processNext();
  });

  // Stop button
  $("#stop-bulk-btn").on("click", function () {
    if (!confirm("Are you sure you want to stop generation?")) {
      return;
    }

    isProcessing = false;
    isPaused = false;

    $.ajax({
      url: aiVoiceBulk.ajax_url,
      type: "POST",
      data: {
        action: "ai_voice_clear_queue",
        nonce: aiVoiceBulk.nonce,
      },
      success: function () {
        resetUI();
        addLog("Generation stopped by user", "warning");
      },
    });
  });

  // Clear log button
  $("#clear-log").on("click", function () {
    $("#activity-log").html(
      '<p class="log-empty">No activity yet. Start generation to see logs.</p>'
    );
  });

  // Save settings
  $("#bulk-settings-form").on("submit", function (e) {
    e.preventDefault();

    const newRateLimit = $("#bulk-rate-limit").val();
    const maxPerHour = $("#bulk-max-per-hour").val();

    // Update rate limit
    rateLimit = parseInt(newRateLimit);

    // Save to WordPress options via AJAX
    $.ajax({
      url: aiVoiceBulk.ajax_url,
      type: "POST",
      data: {
        action: "ai_voice_save_bulk_settings",
        nonce: aiVoiceBulk.nonce,
        rate_limit: newRateLimit,
        max_per_hour: maxPerHour,
      },
      success: function () {
        addLog("Settings saved successfully", "success");

        // Show temporary success message
        const $btn = $("#bulk-settings-form button[type=submit]");
        const originalText = $btn.text();
        $btn.text("✓ Saved!").prop("disabled", true);
        setTimeout(function () {
          $btn.text(originalText).prop("disabled", false);
        }, 2000);
      },
    });
  });

  // Process next post in queue
  function processNext() {
    if (!isProcessing || isPaused) {
      return;
    }

    $.ajax({
      url: aiVoiceBulk.ajax_url,
      type: "POST",
      data: {
        action: "ai_voice_process_next",
        nonce: aiVoiceBulk.nonce,
      },
      success: function (response) {
        if (response.success) {
          const data = response.data;

          if (data.status === "complete") {
            // All done!
            completeGeneration();
            return;
          }

          if (data.status === "paused") {
            return;
          }

          // Update progress
          processedCount = data.index;
          const progress = Math.round((processedCount / totalCount) * 100);

          $("#processed-count").text(processedCount);
          $("#current-post").text(data.post_title);
          $("#progress-fill").css("width", progress + "%");
          $("#progress-text").text(progress + "%");

          // Calculate ETA
          const elapsed = (Date.now() - startTime) / 1000; // seconds
          const avgTimePerPost = elapsed / processedCount;
          const remaining = totalCount - processedCount;
          const eta = Math.round(avgTimePerPost * remaining);
          $("#progress-eta").text("Estimated time: " + formatTime(eta));

          // Log result
          if (data.result.success) {
            if (data.result.skipped) {
              addLog(`Skipped: ${data.post_title} (audio exists)`, "info");
            } else {
              addLog(`Generated: ${data.post_title}`, "success");
            }
          } else {
            addLog(
              `Failed: ${data.post_title} - ${data.result.error}`,
              "error"
            );
          }

          // Wait for rate limit, then process next
          setTimeout(processNext, rateLimit * 1000);
        } else {
          addLog("Error processing post: " + response.data.message, "error");
        }
      },
      error: function () {
        addLog("AJAX error while processing", "error");
        // Retry after longer delay
        if (isProcessing && !isPaused) {
          setTimeout(processNext, (rateLimit + 30) * 1000);
        }
      },
    });
  }

  // Complete generation
  function completeGeneration() {
    isProcessing = false;
    resetUI();

    $("#queue-status")
      .text("Complete")
      .removeClass("status-running")
      .addClass("status-complete");

    addLog(
      `✅ Bulk generation complete! Processed ${totalCount} posts.`,
      "success"
    );

    // Show success notification
    if (window.Notification && Notification.permission === "granted") {
      new Notification("AI Voice", {
        body: `Bulk generation complete! ${totalCount} posts processed.`,
        icon: "/wp-admin/images/wordpress-logo.svg",
      });
    }

    // Reload stats
    loadStats();
  }

  // Reset UI to initial state
  function resetUI() {
    $("#start-bulk-btn").show().prop("disabled", false);
    $("#pause-bulk-btn, #resume-bulk-btn, #stop-bulk-btn").hide();
    $(".progress-container").hide();
    $("#current-post").text("None");
    $("#queue-status")
      .text("Idle")
      .removeClass("status-running status-paused status-complete")
      .addClass("status-idle");
  }

  // Load statistics
  function loadStats() {
    return $.ajax({
      url: aiVoiceBulk.ajax_url,
      type: "POST",
      data: {
        action: "ai_voice_get_stats",
        nonce: aiVoiceBulk.nonce,
      },
      success: function (response) {
        if (response.success) {
          const data = response.data;
          $("#total-posts").text(data.total_posts);
          $("#with-audio").text(data.with_audio);
          $("#pending-posts").text(data.pending);
          $("#completion-rate").text(data.completion_rate);
        }
      },
    });
  }

  // Add log entry
  function addLog(message, type = "info") {
    const $log = $("#activity-log");

    // Remove empty message if exists
    $log.find(".log-empty").remove();

    const timestamp = new Date().toLocaleTimeString();
    const icon =
      type === "success"
        ? "✓"
        : type === "error"
        ? "✗"
        : type === "warning"
        ? "⚠"
        : "ℹ";

    const $entry = $("<div>")
      .addClass("log-entry log-" + type)
      .html(
        `<span class="log-time">[${timestamp}]</span> <span class="log-icon">${icon}</span> <span class="log-message">${message}</span>`
      );

    $log.prepend($entry);

    // Keep only last 50 entries
    $log.find(".log-entry:gt(49)").remove();

    // Auto-scroll
    $log.scrollTop(0);
  }

  // Format seconds to human readable time
  function formatTime(seconds) {
    if (seconds < 60) {
      return Math.round(seconds) + " seconds";
    } else if (seconds < 3600) {
      const minutes = Math.floor(seconds / 60);
      const secs = Math.round(seconds % 60);
      return minutes + " min " + secs + " sec";
    } else {
      const hours = Math.floor(seconds / 3600);
      const minutes = Math.floor((seconds % 3600) / 60);
      return hours + " hour " + minutes + " min";
    }
  }

  // Request notification permission on page load
  if (window.Notification && Notification.permission === "default") {
    Notification.requestPermission();
  }
});
