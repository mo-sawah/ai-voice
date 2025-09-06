<?php
// Embed-able purge box + AJAX handler (no new admin page).
if (!defined('ABSPATH')) exit;

/**
 * Call this inside your existing AI Voice settings page renderer where you want the box to appear:
 *
 *   if (function_exists('ai_voice_render_purge_box')) {
 *       ai_voice_render_purge_box();
 *   }
 */
function ai_voice_render_purge_box() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Enqueue our small JS only when the box is rendered.
    wp_enqueue_script(
        'ai-voice-purge-js',
        plugins_url('admin/assets/js/ai-voice-purge.js', dirname(__FILE__)),
        array('jquery'),
        '1.0.1',
        true
    );

    wp_localize_script('ai-voice-purge-js', 'aiVoicePurgeData', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('ai_voice_purge_nonce'),
    ));

    // Minimal styles scoped to this box
    $css = '
    .ai-voice-purge-box { background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; max-width:840px; }
    .ai-voice-purge-actions { margin-top:12px; }
    .ai-voice-purge-result { margin-top:12px; padding:12px; border-radius:6px; background:#f8fafc; border:1px solid #e5e7eb; display:none; }
    .ai-voice-purge-result ul { margin:6px 0 0 18px; list-style:disc; }
    .ai-voice-purge-btn .ai-voice-purge-spinner { display:inline-block; margin-left:8px; vertical-align:middle; }
    .ai-voice-purge-btn .ai-voice-purge-spinner:not(.is-active) { display:none; }
    ';
    // Use a core handle so the CSS prints in admin head
    wp_add_inline_style('wp-components', $css);
    ?>

    <h2 style="margin-top:24px;">Maintenance</h2>
    <div class="ai-voice-purge-box">
        <h3>Purge Cache (Delete Generated Audio & Summaries)</h3>
        <p>This will remove ALL generated audio files and cached summaries created by the AI Voice plugin:</p>
        <ul style="list-style:disc; padding-left:20px;">
            <li>Delete Media Library attachments pointing to files named <code>ai-voice-*.mp3</code></li>
            <li>Delete any leftover <code>ai-voice-*.mp3</code> files from <code>wp-content/uploads</code></li>
            <li>Delete cached post meta: <code>_ai_voice_audio_url_*</code> and <code>_ai_voice_summary_*</code></li>
        </ul>
        <p><strong>Note:</strong> Take a quick backup first. This cannot be undone.</p>

        <div class="ai-voice-purge-actions">
            <button type="button" class="button button-primary ai-voice-purge-btn" id="ai-voice-purge-btn">
                Purge Now
                <!-- Spinner starts NOT active -->
                <span class="spinner ai-voice-purge-spinner" style="float:none;"></span>
            </button>
            <button type="button" class="button" id="ai-voice-purge-refresh" style="margin-left:8px;">Refresh Page</button>
        </div>

        <div class="ai-voice-purge-result" id="ai-voice-purge-result"></div>
    </div>
    <?php
}

// AJAX: purge everything
add_action('wp_ajax_ai_voice_purge_cache', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'), 403);
    }
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'ai_voice_purge_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'), 403);
    }

    @set_time_limit(180);
    $result = ai_voice_run_purge();
    wp_send_json_success($result);
});

/**
 * Core purge logic with robust postmeta fallback.
 */
function ai_voice_run_purge() {
    global $wpdb;

    $out = array(
        'deleted_attachments'    => 0,
        'deleted_leftover_files' => 0,
        'deleted_postmeta_rows'  => 0,
        'errors'                 => array(),
    );

    // 1) Delete audio attachments for ai-voice-*.mp3
    $attachments = get_posts(array(
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
        'post_mime_type' => 'audio/mpeg',
        'fields'         => 'ids',
    ));

    foreach ((array)$attachments as $att_id) {
        $file = get_attached_file($att_id);
        if (!$file) continue;
        $bn = basename($file);
        if (stripos($bn, 'ai-voice-') === 0 && ai_voice_str_ends_with(strtolower($bn), '.mp3')) {
            $deleted = wp_delete_attachment($att_id, true);
            if ($deleted) $out['deleted_attachments']++;
            else $out['errors'][] = "Failed to delete attachment ID {$att_id} ({$bn}).";
        }
    }

    // 2) Delete any leftover ai-voice-*.mp3 in uploads
    $uploads = wp_get_upload_dir();
    $basedir = $uploads['basedir'];
    if (is_dir($basedir) && is_readable($basedir)) {
        try {
            $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basedir, FilesystemIterator::SKIP_DOTS));
            foreach ($rii as $fi) {
                if ($fi->isFile()) {
                    $bn = $fi->getBasename();
                    if (stripos($bn, 'ai-voice-') === 0 && ai_voice_str_ends_with(strtolower($bn), '.mp3')) {
                        $full = $fi->getPathname();
                        if (file_exists($full)) {
                            if (@unlink($full)) $out['deleted_leftover_files']++;
                            else $out['errors'][] = "Failed to delete file {$full}.";
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $out['errors'][] = 'Scan error: ' . $e->getMessage();
        }
    } else {
        $out['errors'][] = 'Uploads directory not accessible: ' . $basedir;
    }

    // 3) Delete postmeta rows by pattern
    $deleted_rows = 0;
    $sql = "
        DELETE FROM {$wpdb->postmeta}
        WHERE meta_key LIKE '_ai\\_voice\\_audio\\_url\\_%' ESCAPE '\\'
           OR meta_key LIKE '_ai\\_voice\\_summary\\_%'   ESCAPE '\\'
    ";
    $res = $wpdb->query($sql);

    if ($res === false) {
        // Fallback: iterate meta keys and use WP API to delete, counting rows
        $keys = $wpdb->get_col("
            SELECT DISTINCT meta_key
            FROM {$wpdb->postmeta}
            WHERE meta_key LIKE '_ai\\_voice\\_audio\\_url\\_%' ESCAPE '\\'
               OR meta_key LIKE '_ai\\_voice\\_summary\\_%'   ESCAPE '\\'
        ");

        if (is_wp_error($keys)) {
            $out['errors'][] = 'Postmeta lookup failed.';
        } else {
            foreach ((array)$keys as $k) {
                // Count rows for this key before deletion
                $cnt = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", $k));
                delete_post_meta_by_key($k);
                $deleted_rows += $cnt;
            }
        }

        if ($deleted_rows === 0) {
            $err = $wpdb->last_error ? ' (' . $wpdb->last_error . ')' : '';
            $out['errors'][] = 'Failed to delete postmeta rows' . $err . '.';
        }
    } else {
        // Direct SQL worked; use affected rows
        $deleted_rows = (int) $res;
    }

    $out['deleted_postmeta_rows'] = $deleted_rows;
    return $out;
}

if (!function_exists('ai_voice_str_ends_with')) {
    function ai_voice_str_ends_with($haystack, $needle) {
        $len = strlen($needle);
        if ($len === 0) return true;
        return substr($haystack, -$len) === $needle;
    }
}