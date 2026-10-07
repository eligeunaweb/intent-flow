<?php
if (!defined('ABSPATH')) { exit; }

final class IF_Redact {

	// claves sensibles típicas (case-insensitive)
	private static $SENSITIVE_KEYS = [
		'token', 'nonce', 'password', 'pass', 'pwd', 'secret', 'api_key', 'apikey', 'authorization', 'auth',
	];

	public static function deep(array $data) : array {
		return self::walk($data);
	}

	private static function walk($value) {
		if (is_array($value)) {
			$out = [];
			foreach ($value as $k => $v) {
				if (is_string($k) && self::is_sensitive_key($k)) {
					$out[$k] = '[REDACTED]';
				} else {
					$out[$k] = self::walk($v);
				}
			}
			return $out;
		}
		return $value;
	}

	private static function is_sensitive_key(string $key) : bool {
		$k = strtolower($key);
		foreach (self::$SENSITIVE_KEYS as $s) {
			if ($k === $s) return true;
		}
		return false;
	}
}
