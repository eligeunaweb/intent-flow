<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Lightweight token helper used for privileged toggles (settings/live).
 * Token is derived from WP salts + user_id and not stored in DB.
 */
final class MacroIntent_Settings {

	public static function expected_token(int $user_id) : string {
		return substr(hash_hmac('sha256', 'if_settings_toggle:' . $user_id, wp_salt('auth')), 0, 12);
	}

	public static function validate_token($token, int $user_id) : array {
		$token = is_string($token) ? trim($token) : '';
		if ($token === '') {
			return ['ok' => false, 'error' => 'missing_token'];
		}
		$expected = self::expected_token($user_id);
		if (!hash_equals($expected, $token)) {
			return ['ok' => false, 'error' => 'invalid_token'];
		}
		return ['ok' => true];
	}
}
