<?php
if (!defined('ABSPATH')) { exit; }

final class IF_PlanState {

	/**
	 * Calcula una huella estable del estado del sitio relevante para aplicar planes.
	 * Ajusta la lista si quieres incluir/excluir cosas.
	 */
	public static function state_hash() : string {
		$state = [
			'siteurl'        => (string) get_option('siteurl', ''),
			'home'           => (string) get_option('home', ''),
			'wp_version'     => (string) get_bloginfo('version'),
			'stylesheet'     => (string) get_option('stylesheet', ''),
			'template'       => (string) get_option('template', ''),
			'active_plugins' => (array) get_option('active_plugins', []),
			'inteflow_settings'   => self::safe_settings(),
		];

		// Ordena para hash determinista
		$state['active_plugins'] = array_values($state['active_plugins']);
		sort($state['active_plugins']);

		return hash('sha256', wp_json_encode($state));
	}

	private static function safe_settings() : array {
		if (!class_exists('IF_Settings')) {
			return [];
		}
		$s = IF_Settings::get();
		return [
			'enabled'   => !empty($s['enabled']),
			'safe_mode' => !empty($s['safe_mode']),
			'dry_run'   => !empty($s['dry_run']),
		];
	}
}
