<?php
if (!defined('ABSPATH')) { exit; }

final class IF_AuditLog {

	const OPTION_KEY = 'if_audit_log';
	const MAX_ENTRIES = 200;

	public static function record(array $event) : void {

		$log = get_option(self::OPTION_KEY, []);

		if (!is_array($log)) {
			$log = [];
		}

		$log[] = [
			'ts'         => gmdate('c'),
			'user_id'    => get_current_user_id(),
			'intentId'   => $event['intentId'] ?? '',
			'ok'         => !empty($event['ok']),
			'blocked'    => !empty($event['blocked']),
			'reason'     => $event['reason'] ?? '',
			'dry_run'    => !empty($event['dry_run']),
			'duration_ms'=> isset($event['duration_ms']) ? (int)$event['duration_ms'] : 0,
			'source'     => $event['source'] ?? 'unknown',
		];

		// ring buffer
		if (count($log) > self::MAX_ENTRIES) {
			$log = array_slice($log, -self::MAX_ENTRIES);
		}

		update_option(self::OPTION_KEY, $log, false);
	}

	public static function latest(int $limit = 20) : array {

		$log = get_option(self::OPTION_KEY, []);

		if (!is_array($log)) {
			return [];
		}

		return array_slice(array_reverse($log), 0, $limit);
	}

	public static function clear() : void {
		delete_option(self::OPTION_KEY);
	}

}
