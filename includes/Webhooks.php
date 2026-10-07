<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Webhooks (v1)
 * - Public REST endpoint: /wp-json/mmi/v1/webhook/{id}
 * - HMAC verification via X-MMI-Signature header (sha256=<hex> or <hex>)
 * - Emits event: webhook.received
 */
final class IF_Webhooks {
	private static $did_init = false;
	const OPTION_KEY = 'if_webhooks_v1';

	public static function init() : void {
		if (self::$did_init) return;
		self::$did_init = true;
		add_action('rest_api_init', [__CLASS__, 'register_routes']);
	}

	public static function get_all() : array {
		$all = get_option(self::OPTION_KEY, []);
		return is_array($all) ? $all : [];
	}

	public static function get(string $id) : ?array {
		$all = self::get_all();
		return isset($all[$id]) && is_array($all[$id]) ? $all[$id] : null;
	}

	public static function save(array $data) : array {
		$all = self::get_all();

		$id = isset($data['id']) && is_string($data['id']) && $data['id'] !== ''
			? sanitize_key($data['id'])
			: ('wh_' . sanitize_key(wp_generate_password(10, false, false)));

		$item = $all[$id] ?? [
			'id' => $id,
			'created_at' => time(),
		];

		$item['name'] = sanitize_text_field($data['name'] ?? $item['name'] ?? $id);
		$item['enabled'] = isset($data['enabled']) ? (bool)$data['enabled'] : (bool)($item['enabled'] ?? true);

		if (empty($item['secret'])) {
			$item['secret'] = self::generate_secret();
		}

		$all[$id] = $item;
		update_option(self::OPTION_KEY, $all, false);

		return self::with_url($item);
	}

	public static function delete(string $id) : bool {
		$id = sanitize_key($id);
		$all = self::get_all();
		if (!isset($all[$id])) return false;
		unset($all[$id]);
		update_option(self::OPTION_KEY, $all, false);
		return true;
	}

	public static function rotate_secret(string $id) : ?array {
		$id = sanitize_key($id);
		$all = self::get_all();
		if (!isset($all[$id]) || !is_array($all[$id])) return null;
		$all[$id]['secret'] = self::generate_secret();
		update_option(self::OPTION_KEY, $all, false);
		return self::with_url($all[$id]);
	}

	private static function generate_secret() : string {
		try {
			return bin2hex(random_bytes(16));
		} catch (\Throwable $e) {
			return wp_generate_password(32, false, false);
		}
	}

	private static function with_url(array $item) : array {
		$item['url'] = function_exists('get_rest_url')
			? get_rest_url(null, 'mmi/v1/webhook/' . $item['id'])
			: rest_url('if/v1/webhook/' . $item['id']);
		return $item;
	}

	public static function register_routes() : void {
		register_rest_route('mmi/v1', '/webhook/(?P<id>[a-zA-Z0-9_\-]+)', [
			'methods'  => 'POST',
			'callback' => [__CLASS__, 'handle_webhook'],
			'permission_callback' => '__return_true',
			'args' => [
				'id' => ['required' => true],
			],
		]);
	}

	public static function handle_webhook(\WP_REST_Request $req) {
		$id = sanitize_key((string)$req->get_param('id'));
		$hook = self::get($id);
		if (!$hook) {
			return new \WP_REST_Response(['ok'=>false,'error'=>'webhook_not_found'], 404);
		}
		if (empty($hook['enabled'])) {
			return new \WP_REST_Response(['ok'=>false,'error'=>'webhook_disabled'], 403);
		}

		$body = (string)$req->get_body();
		$sig = self::get_header($req, 'x-mmi-signature');

		if (!$sig) {
			return new \WP_REST_Response(['ok'=>false,'error'=>'missing_signature'], 401);
		}

		$sig = trim((string)$sig);
		if (stripos($sig, 'sha256=') === 0) {
			$sig = substr($sig, 7);
		}
		$expected = hash_hmac('sha256', $body, (string)($hook['secret'] ?? ''));
		if (!hash_equals($expected, strtolower($sig))) {
			return new \WP_REST_Response(['ok'=>false,'error'=>'invalid_signature'], 401);
		}

		$parsed = null;
		$is_json = false;
		if ($body !== '') {
			$tmp = json_decode($body, true);
			if (json_last_error() === JSON_ERROR_NONE) {
				$parsed = $tmp;
				$is_json = true;
			}
		}

		$payload = [
			'webhook_id' => $id,
			'body' => $is_json ? $parsed : $body,
			'raw'  => $body,
			'query' => $req->get_query_params(),
		];

		$meta = [
			'source' => 'webhook',
			'ip' => sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')) ?? '',
			'ua' => sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')) ?? '',
			'received_at' => time(),
		];

		// Emit event for rule engine.
		if (class_exists('IF_Events')) {
			IF_Events::emit('webhook.received', $payload, $meta);
		}

		// Optional: record the reception itself in execution log, even if no rules.
		if (class_exists('IF_ExecutionLog')) {
			IF_ExecutionLog::add([
				'source' => 'webhook.received',
				'target' => ['type'=>'event','id'=>'webhook.received'],
				'ok' => true,
				'meta' => ['webhook_id'=>$id],
				'message' => 'webhook_received',
			]);
		}

		return new \WP_REST_Response(['ok'=>true], 200);
	}

	private static function get_header(\WP_REST_Request $req, string $name) : ?string {
		$h = $req->get_header($name);
		if (is_string($h) && $h !== '') return $h;
		$h2 = $req->get_header(strtolower($name));
		if (is_string($h2) && $h2 !== '') return $h2;
		return null;
	}
}
