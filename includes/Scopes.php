<?php
if (!defined('ABSPATH')) { exit; }

final class IF_Scopes {

	public static function defaults() : array {
		// '*' = todo
		return [
			'administrator' => ['*'],
			'editor'        => ['posts', 'diagnostics', 'audit', 'batch', 'plan'],
			'author'        => ['posts', 'diagnostics'],
			'contributor'   => ['posts', 'diagnostics'],
			'subscriber'    => [],
		];
	}

	public static function get_map() : array {
		$s = class_exists('IF_Settings') ? IF_Settings::get() : [];
		$map = isset($s['scopes_by_role']) && is_array($s['scopes_by_role']) ? $s['scopes_by_role'] : [];
		return $map ?: self::defaults();
	}

	public static function user_roles(int $user_id) : array {
		$u = get_userdata($user_id);
		if (!$u || empty($u->roles) || !is_array($u->roles)) return [];
		return $u->roles;
	}

	public static function is_allowed(int $user_id, string $scope) : bool {
		if ($scope === '' || $scope === 'core') return true;

		$roles = self::user_roles($user_id);
		if (!$roles) return false;

		$map = self::get_map();

		foreach ($roles as $role) {
			$allowed = $map[$role] ?? [];
			if (!is_array($allowed)) continue;
			if (in_array('*', $allowed, true)) return true;
			if (in_array($scope, $allowed, true)) return true;
		}
		return false;
	}
}
