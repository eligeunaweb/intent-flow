<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Integrations storage (connections) for external platforms.
 *
 * Option: if_integrations_v1 (autoload = false)
 *
 * Structure:
 *  [
 *    'version' => 1,
 *    'items' => [
 *      [
 *        'id' => 'slack_main',
 *        'type' => 'slack.webhook' | 'zapier.hook' | 'http.endpoint',
 *        'name' => '...',
 *        'enabled' => true,
 *        'config' => [ ... ],
 *        'created_at' => 123,
 *        'updated_at' => 123,
 *      ],
 *    ],
 *  ]
 */
final class IF_Integrations {
	const OPTION_KEY = 'if_integrations_v1';

	public static function ensure_defaults() : void {
		$cur = get_option(self::OPTION_KEY, null);
		if (is_array($cur) && isset($cur['version'])) return;
		add_option(self::OPTION_KEY, [
			'version' => 1,
			'items' => [],
		], '', false);
	}

	public static function all() : array {
		$v = get_option(self::OPTION_KEY, [ 'version' => 1, 'items' => [] ]);
		if (!is_array($v)) return [ 'version' => 1, 'items' => [] ];
		if (!isset($v['version'])) $v['version'] = 1;
		if (!isset($v['items']) || !is_array($v['items'])) $v['items'] = [];
		return $v;
	}

	public static function list_items(string $type = '') : array {
		$data = self::all();
		$items = $data['items'];
		if ($type !== '') {
			$items = array_values(array_filter($items, function($it) use ($type){
				return is_array($it) && ($it['type'] ?? '') === $type;
			}));
		}
		return $items;
	}

	public static function get(string $id) : ?array {
		$id = sanitize_key($id);
		if (!$id) return null;
		$data = self::all();
		foreach ($data['items'] as $it) {
			if (is_array($it) && ($it['id'] ?? '') === $id) return $it;
		}
		return null;
	}

	public static function save(array $item) : array {
		self::ensure_defaults();

		$type = isset($item['type']) ? (string)$item['type'] : '';
		$name = isset($item['name']) ? sanitize_text_field((string)$item['name']) : '';
		$enabled = isset($item['enabled']) ? (bool)$item['enabled'] : true;
		$id = isset($item['id']) ? sanitize_key((string)$item['id']) : '';

		if (!$id) {
			$id = 'conn_' . wp_generate_password(10, false, false);
		}

		$allowed_types = [ 'slack.webhook', 'zapier.hook', 'http.endpoint' ];
		if (!in_array($type, $allowed_types, true)) {
			return [ 'ok' => false, 'error' => 'integration_type_not_allowed' ];
		}
		if ($name === '') {
			return [ 'ok' => false, 'error' => 'name_required' ];
		}

		$config = is_array($item['config'] ?? null) ? $item['config'] : [];
		$config = self::sanitize_config($type, $config);
		if ($config === null) {
			return [ 'ok' => false, 'error' => 'invalid_config' ];
		}

		$data = self::all();
		$now = time();
		$found = false;
		foreach ($data['items'] as $idx => $it) {
			if (is_array($it) && ($it['id'] ?? '') === $id) {
				$found = true;
				$data['items'][$idx] = [
					'id' => $id,
					'type' => $type,
					'name' => $name,
					'enabled' => $enabled,
					'config' => $config,
					'created_at' => (int)($it['created_at'] ?? $now),
					'updated_at' => $now,
				];
				break;
			}
		}
		if (!$found) {
			$data['items'][] = [
				'id' => $id,
				'type' => $type,
				'name' => $name,
				'enabled' => $enabled,
				'config' => $config,
				'created_at' => $now,
				'updated_at' => $now,
			];
		}

		update_option(self::OPTION_KEY, $data, false);
		return [ 'ok' => true, 'item' => self::get($id) ];
	}

	public static function delete(string $id) : array {
		$id = sanitize_key($id);
		if (!$id) return [ 'ok' => false, 'error' => 'id_required' ];
		$data = self::all();
		$before = count($data['items']);
		$data['items'] = array_values(array_filter($data['items'], function($it) use ($id){
			return !is_array($it) || ($it['id'] ?? '') !== $id;
		}));
		if (count($data['items']) === $before) return [ 'ok' => false, 'error' => 'not_found' ];
		update_option(self::OPTION_KEY, $data, false);
		return [ 'ok' => true ];
	}

	private static function sanitize_config(string $type, array $config) : ?array {
		switch ($type) {
			case 'slack.webhook':
				$url = isset($config['webhook_url']) ? esc_url_raw((string)$config['webhook_url']) : '';
				if (!$url) return null;
				return [ 'webhook_url' => $url ];
			case 'zapier.hook':
				$url = isset($config['hook_url']) ? esc_url_raw((string)$config['hook_url']) : '';
				if (!$url) return null;
				return [ 'hook_url' => $url ];
			case 'http.endpoint':
				$base = isset($config['base_url']) ? esc_url_raw((string)$config['base_url']) : '';
				if (!$base) return null;
				$headers = is_array($config['headers'] ?? null) ? $config['headers'] : [];
				$out_headers = [];
				foreach ($headers as $k => $v) {
					$hk = sanitize_text_field((string)$k);
					if ($hk === '') continue;
					$out_headers[$hk] = sanitize_text_field((string)$v);
				}
				return [
					'base_url' => $base,
					'headers' => $out_headers,
				];
			default:
				return null;
		}
	}
}
