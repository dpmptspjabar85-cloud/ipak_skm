<?php
defined('BASEPATH') or exit('No direct script access allowed.');

/**
 * Builds a cache-busting asset URL.
 *
 * The production server serves assets through caching layers and browsers keep
 * them far longer than a deploy cycle. Without a version query string the
 * survey form can load a stale survey.js/app.css, which silently breaks newer
 * frontend features (for example the searchable dropdown on <select> fields).
 * Appending the file mtime forces a fresh copy whenever the file changes.
 */
function ipak_asset($relativePath)
{
    $relativePath = ltrim((string) $relativePath, '/');
    $url = base_url('assets/ipak/' . $relativePath);

    $filePath = FCPATH . 'assets/ipak/' . $relativePath;
    if (is_file($filePath)) {
        $version = filemtime($filePath);
        if ($version) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'v=' . $version;
        }
    }

    return $url;
}