<?php
/**
 * img_helpers.php — Shared image path normalization
 *
 * DB paths can be stored in two formats (historical inconsistency):
 *   A) "../../assets/img/orgs/xxx.jpg"   (saved by create_org.php from config/API/)
 *   B) "assets/uploads/orgs/xxx.jpg"     (saved by update_org_settings.php — clean format)
 *
 * Both functions are guarded with function_exists() so this file is safe
 * to include multiple times or after pages that declare their own copies.
 */

if (!function_exists('cleanImgPath')) {
    /**
     * Strips leading ../../ or ../ prefixes from a stored image path.
     * Returns a clean project-root-relative path like "assets/img/orgs/xxx.jpg".
     */
    function cleanImgPath(string $path): string {
        if (empty($path)) return '';
        if (strpos($path, 'http') === 0) return $path;
        if (strpos($path, '/')   === 0) return $path;
        return preg_replace('/^(\.\.\/)+/', '', $path);
    }
}

if (!function_exists('imgPathForDepth')) {
    /**
     * Returns the web URL for an image stored in the DB,
     * relative to a page that is $depth levels below the project root.
     *
     *  depth=1 → app/index.php
     *  depth=2 → app/osa/ | app/student/ | app/organization/
     */
    function imgPathForDepth(string $storedPath, int $depth = 2, string $fallback = ''): string {
        if (empty($storedPath)) return $fallback;
        $clean = cleanImgPath($storedPath);
        if (empty($clean)) return $fallback;
        if (strpos($clean, 'http') === 0 || strpos($clean, '/') === 0) return $clean;
        return str_repeat('../', $depth) . $clean;
    }
}
