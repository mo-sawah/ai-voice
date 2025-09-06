<?php
// Admin UI + AJAX purge for AI Voice plugin.
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    // Add under "Settings" menu. If you already have a plugin settings page, you can
    // copy the "Purge Cache" box HTML into that page instead and keep the same JS/AJAX.
    add_options_page(
        'AI Voice Settings',
        'AI Voice',
        'manage_options',
        'ai-voice-settings',
        'ai_voice_render_settings_page'
    );
});

add_action('admin_enqueue_scripts', function ($hook) {
    // Load JS only on our settings page
    if ($hook !== 'settings_page_ai-voice-settings') return;

    wp_enqueue_script(
        'ai-voice-purge-js',
        plugins_url('admin/assets/js/ai-voice-purge.js', dirname(__FILE__)),
        ['jquery'],
        '1.0.0',
        true
    );

    wp_localize_script('ai-voice-purge-js', 'aiVoicePurgeData', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('ai_voice_purge_nonce'),
    ]);

    // Basic styles for the results box
    $css = '
    .ai-voice-purge-box { background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; max-width:840px; }
    .ai-voice-purge-actions { margin-top:12px; }
    .ai-voice-purge-result { margin-top:12px; padding:12px; border-radius:6px; background:#f8fafc; border:1px solid #e5e7eb; }
    .ai-voice-purge-result ul { margin:6px 0 0 18px; list-style:disc; }
    .ai-voice-purge-spinner { display:none; vertical-align:middle; margin-left:8px; }
    .ai-voice-purge-btn[disabled] .ai-voice-purge-spinner { display:inline-block; }
    ';
    wp_add_inline_style('wp-components', $css);
});

// Settings page renderer (you can merge this box into your existing settings page)
function ai_voice_render_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }
    ?>
    <div class="wrap">
        <h1>AI Voice Settings</h1>
        <p>Manage AI Voice features for your site.</p>

        <h2 style="margin-top:24px;">Maintenance</h2>
        <div class="ai-voice-purge-box">
            <h3>Purge Cache (Delete Generated Audio & Summaries)</h3>
            <p>
                This will remove ALL generated audio files and cached summaries created by the AI Voice plugin:
            </p>
            <ul style="list-style:disc; padding-left:20px;">
                <li>Delete Media Library attachments pointing to files named <code>ai-voice-*.mp3</code></li>
                <li>Delete any leftover <code>ai-voice-*.mp3</code> files from <code>wp-content/uploads</code></li>
                <li>Delete cached post meta: <code>_ai_voice_audio_url_*</code> and <code>_ai_voice_summary_*</code></li>
            </ul>
            <p><strong>Note:</strong> Take a quick backup first. This cannot be undone.</p>

            <div class="ai-voice-purge-actions">
                <button type="button" class="button button-primary ai-voice-purge-btn" id="ai-voice-purge-btn">
                    Purge Now
                    <span class="spinner is-active ai-voice-purge-spinner" style="float:none;"></span>
                </button>
                <button type="button" class="button" id="ai-voice-purge-refresh" style="margin-left:8px;">Refresh Page</button>
            </div>

            <div class="ai-voice-purge-result" id="ai-voice-purge-result" style="display:none;"></div>
        </div>
    </div>
    <?php
}

// AJAX handler
add_action('wp_ajax_ai_voice_purge_cache', 'ai_voice_handle_purge_cache_ajax');
function ai_voice_handle_purge_cache_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
    }
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'ai_voice_purge_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce.'], 403);
    }

    @set_time_limit(120);
    $result = ai_voice_run_purge();

    wp_send_json_success($result);
}

// Core purge logic (reused by AJAX)
function ai_voice_run_purge() {
    global $wpdb;

    $out = [
        'deleted_attachments'    => 0,
        'deleted_leftover_files' => 0,
        'deleted_postmeta_rows'  => 0,
        'errors'                 => [],
    ];

    // 1) Delete audio attachments created by the plugin (whose file starts with ai-voice-*.mp3)
    $attachments = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
        'post_mime_type' => 'audio/mpeg',
        'fields'         => 'ids',
    ]);

    foreach ($attachments as $att_id) {
        $file = get_attached_file($att_id);
        if (!$file) continue;

        $basename = basename($file);
        if (stripos($basename, 'ai-voice-') === 0 && ai_voice_str_ends_with(strtolower($basename), '.mp3')) {
            $deleted = wp_delete_attachment($att_id, true);
            if ($deleted) {
                $out['deleted_attachments']++;
            } else {
                $out['errors'][] = "Failed to delete attachment ID {$att_id} ({$basename}).";
            }
        }
    }

    // 2) Delete any leftover ai-voice-*.mp3 files in uploads
    $uploads = wp_get_upload_dir();
    $basedir = $uploads['basedir'];

    if (is_dir($basedir) && is_readable($basedir)) {
        try {
            $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basedir, FilesystemIterator::SKIP_DOTS));
            foreach ($rii as $fileInfo) {
                if ($fileInfo->isFile()) {
                    $bn = $fileInfo->getBasename();
                    if (stripos($bn, 'ai-voice-') === 0 && ai_voice_str_ends_with(strtolower($bn), '.mp3')) {
                        $full = $fileInfo->getPathname();
                        if (file_exists($full)) {
                            if (@unlink($full)) {
                                $out['deleted_leftover_files']++;
                            } else {
                                $out['errors'][] = "Failed to delete file {$full}.";
                            }
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

    // 3) Remove all cached post meta for audio/summaries
    // Use ESCAPE '\' so underscores in LIKE are treated literally
    $sql = "
        DELETE FROM {$wpdb->postmeta}
        WHERE meta_key LIKE '_ai\\_voice\\_audio\\_url\\_%' ESCAPE '\\'
           OR meta_key LIKE '_ai\\_voice\\_summary\\_%'   ESCAPE '\\'
    ";
    $rows = $wpdb->query($sql);
    if ($rows !== false) {
        $out['deleted_postmeta_rows'] = intval($rows);
    } else {
        $out['errors'][] = 'Failed to delete postmeta rows.';
    }

    return $out;
}

// Small polyfill for older PHP if needed
if (!function_exists('ai_voice_str_ends_with')) {
    function ai_voice_str_ends_with($haystack, $needle) {
        $len = strlen($needle);
        if ($len === 0) return true;
        return substr($haystack, -$len) === $needle;
    }
}