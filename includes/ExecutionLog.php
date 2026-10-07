<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Global execution log (v1)
 * Used for all sources: AJAX, Scheduler, Events, Webhooks, etc.
 */
final class IF_ExecutionLog {
	const OPTION_KEY = 'if_execution_log_v1';
	const MAX_ITEMS  = 500;

	public static function add(array $entry) : string {
		$log = self::get_all();
		$exec_id = $entry['exec_id'] ?? ('exec_' . wp_generate_password(12, false, false) . '_' . time());
		$entry['exec_id'] = $exec_id;
		$entry['ts'] = isset($entry['ts']) ? intval($entry['ts']) : time();

		array_unshift($log, $entry);
		if (count($log) > self::MAX_ITEMS) {
			$log = array_slice($log, 0, self::MAX_ITEMS);
		}
		update_option(self::OPTION_KEY, $log, false);
		return $exec_id;
	}

	
	/**
	 * Back-compat alias. Older code called ::append().
	 */
	public static function append(array $entry) : string {
		return self::add($entry);
	}

	public static function get_all() : array {
		$log = get_option(self::OPTION_KEY, []);
		return is_array($log) ? $log : [];
	}

	public static function list(array $filters = []) : array {
		$limit = isset($filters['limit']) ? max(1, min(500, intval($filters['limit']))) : 100;
		$source = isset($filters['source']) ? (string)$filters['source'] : null;
		$target_type = isset($filters['target_type']) ? (string)$filters['target_type'] : null;
		$target_id = isset($filters['target_id']) ? (string)$filters['target_id'] : null;

		$out = [];
		foreach (self::get_all() as $row) {
			if ($source && (($row['source'] ?? '') !== $source)) continue;
			if ($target_type && (($row['target']['type'] ?? '') !== $target_type)) continue;
			if ($target_id && (($row['target']['id'] ?? '') !== $target_id)) continue;

			$out[] = $row;
			if (count($out) >= $limit) break;
		}
		return $out;
	}

	public static function get(string $exec_id) : ?array {
		foreach (self::get_all() as $row) {
			if (($row['exec_id'] ?? '') === $exec_id) return $row;
		}
		return null;
	}

	public static function clear(array $filters = []) : int {
		$source = isset($filters['source']) ? (string)$filters['source'] : null;
		$target_type = isset($filters['target_type']) ? (string)$filters['target_type'] : null;
		$target_id = isset($filters['target_id']) ? (string)$filters['target_id'] : null;

		if (!$source && !$target_type && !$target_id) {
			update_option(self::OPTION_KEY, [], false);
			return 0;
		}

		$kept = [];
		$removed = 0;
		foreach (self::get_all() as $row) {
			$match = true;
			if ($source && (($row['source'] ?? '') !== $source)) $match = false;
			if ($target_type && (($row['target']['type'] ?? '') !== $target_type)) $match = false;
			if ($target_id && (($row['target']['id'] ?? '') !== $target_id)) $match = false;

			if ($match) {
				$removed++;
				continue;
			}
			$kept[] = $row;
		}
		update_option(self::OPTION_KEY, $kept, false);
		return $removed;
	}
}