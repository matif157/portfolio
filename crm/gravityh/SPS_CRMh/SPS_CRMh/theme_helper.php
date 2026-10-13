<?php
/**
 * GRAVITY CRM — Theme Persistence Helper
 * Reads / writes per-user theme preference in the settings table.
 * Uses key pattern:  user_theme_{user_id}   →  "professional"
 *                    user_mode_{user_id}     →  "auto" | "light" | "dark"
 */

/**
 * Get the current user's theme preference.
 * Falls back to the org-wide default from settings, then 'professional'.
 */
function get_user_theme(): array {
    global $pdo;
    $user_id = $_SESSION['user_id'] ?? 0;

    $theme = 'professional';
    $mode  = 'auto';

    try {
        if ($user_id) {
            // Per-user preference
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN (?, ?)");
            $stmt->execute(["user_theme_{$user_id}", "user_mode_{$user_id}"]);
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $theme = $rows["user_theme_{$user_id}"] ?? null;
            $mode  = $rows["user_mode_{$user_id}"]  ?? null;
        }

        // Fall back to org default if user has no preference
        if (!$theme || !$mode) {
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('app_theme','app_mode')");
            $stmt->execute();
            $org = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $theme = $theme ?: ($org['app_theme'] ?? 'professional');
            $mode  = $mode  ?: ($org['app_mode']  ?? 'auto');
        }
    } catch (Throwable $e) {
        // DB unavailable — use defaults
    }

    // Sanitise to allowed values
    $allowed_themes = ['professional', 'aurora', 'executive', 'studio', 'focus'];
    $allowed_modes  = ['light', 'dark', 'auto'];
    if (!in_array($theme, $allowed_themes)) $theme = 'professional';
    if (!in_array($mode,  $allowed_modes))  $mode  = 'auto';

    return ['theme' => $theme, 'mode' => $mode];
}

/**
 * Save a theme preference for the current user.
 */
function save_user_theme(string $theme, string $mode): bool {
    global $pdo;
    $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) return false;

    $allowed_themes = ['professional', 'aurora', 'executive', 'studio', 'focus'];
    $allowed_modes  = ['light', 'dark', 'auto'];
    if (!in_array($theme, $allowed_themes)) return false;
    if (!in_array($mode,  $allowed_modes))  return false;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute(["user_theme_{$user_id}", $theme]);
        $stmt->execute(["user_mode_{$user_id}",  $mode]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
