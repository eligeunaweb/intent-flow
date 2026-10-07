<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Target normalizer (v1)
 * Unifies legacy scheduler/jobs and event rules into a canonical target:
 *   ['type' => 'intent'|'app', 'id' => 'server.audit.latest'|'app_xxx']
 */
final class IF_Target {

	public static function normalize($mixed) : array {
		$t = is_array($mixed) ? $mixed : [];
		// Already canonical?
		if (isset($t['type'], $t['id']) && is_string($t['type']) && is_string($t['id'])) {
			return [
				'type' => self::sanitize_type($t['type']),
				'id'   => sanitize_text_field($t['id']),
			];
		}

		// Legacy scheduler job shape: { intent, ... } or { type:intent, intent } or { type:app, app_id }
		$type = $t['type'] ?? null;

		if ($type === 'app' || isset($t['app_id'])) {
			$app_id = $t['app_id'] ?? ($t['id'] ?? '');
			return [
				'type' => 'app',
				'id'   => sanitize_text_field((string)$app_id),
			];
		}

		// Default to intent
		$intent = $t['intent'] ?? ($t['id'] ?? '');
		return [
			'type' => 'intent',
			'id'   => sanitize_text_field((string)$intent),
		];
	}

	public static function sanitize_type($type) : string {
		$type = strtolower((string)$type);
		return ($type === 'app') ? 'app' : 'intent';
	}

	public static function is_valid(array $target) : bool {
		return isset($target['type'], $target['id'])
			&& in_array($target['type'], ['intent','app'], true)
			&& is_string($target['id']) && $target['id'] !== '';
	}
}
