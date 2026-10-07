<?php
if (!defined('ABSPATH')) { exit; }

/**
 * zapier.trigger
 *
 * payload:
 *  - connection_id (zapier.hook) OR hook_url
 *  - data (array|object|string)
 */
return function(IF_IntentRegistry $reg) {
	$reg->register('zapier.trigger', function(array $payload, array $meta, array $ctx) : array {
		$hook = '';
		$connection_id = isset($payload['connection_id']) ? sanitize_key((string)$payload['connection_id']) : '';
		if ($connection_id) {
			$conn = IF_Integrations::get($connection_id);
			if (!$conn || empty($conn['enabled']) || ($conn['type'] ?? '') !== 'zapier.hook') {
				return [ 'ok' => false, 'error' => 'zapier_connection_not_found' ];
			}
			$cfg = is_array($conn['config'] ?? null) ? $conn['config'] : [];
			$hook = (string)($cfg['hook_url'] ?? '');
		} else {
			$hook = isset($payload['hook_url']) ? esc_url_raw((string)$payload['hook_url']) : '';
		}
		if (!$hook) return [ 'ok' => false, 'error' => 'hook_url_required' ];

		$data = $payload['data'] ?? $payload['body'] ?? [];
		if (is_string($data)) {
			$body = $data;
			$headers = [ 'Content-Type' => 'text/plain; charset=utf-8' ];
		} else {
			$body = wp_json_encode($data);
			$headers = [ 'Content-Type' => 'application/json; charset=utf-8' ];
		}

		$res = wp_remote_post($hook, [
			'timeout' => 15,
			'headers' => $headers,
			'body' => $body,
		]);
		if (is_wp_error($res)) {
			return [ 'ok' => false, 'error' => 'zapier_request_failed', 'message' => $res->get_error_message() ];
		}
		$code = (int) wp_remote_retrieve_response_code($res);
		$resp = wp_remote_retrieve_body($res);
		return [
			'ok' => ($code >= 200 && $code < 300),
			'code' => $code,
			'body' => is_string($resp) ? $resp : '',
		];
	}, [
		'capability' => 'manage_options',
		'scope' => 'integrations',
		'unsafe' => true,
		'notes' => 'Dispara un Zapier Catch Hook.',
		'tags' => ['zapier','webhook','integration'],
	]);
};
