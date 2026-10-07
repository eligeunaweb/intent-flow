<?php
if (!defined('ABSPATH')) { exit; }

/**
 * http.request
 *
 * payload:
 *  - url (string) OR connection_id (http.endpoint) + path
 *  - method (GET|POST|PUT|PATCH|DELETE)
 *  - headers (object)
 *  - body (any)
 *  - timeout (seconds)
 */
return function(IF_IntentRegistry $reg) {
	$reg->register('http.request', function(array $payload, array $meta, array $ctx) : array {
		$method  = strtoupper(sanitize_text_field((string)($payload['method'] ?? 'POST')));
		$timeout = isset($payload['timeout']) ? max(1, min(30, (int)$payload['timeout'])) : 15;

		$headers = is_array($payload['headers'] ?? null) ? $payload['headers'] : [];
		$body    = $payload['body'] ?? null;

		$url = '';
		$connection_id = isset($payload['connection_id']) ? sanitize_key((string)$payload['connection_id']) : '';
		if ($connection_id) {
			$conn = IF_Integrations::get($connection_id);
			if (!$conn || empty($conn['enabled']) || ($conn['type'] ?? '') !== 'http.endpoint') {
				return [ 'ok' => false, 'error' => 'http_connection_not_found' ];
			}
			$cfg = is_array($conn['config'] ?? null) ? $conn['config'] : [];
			$base = (string)($cfg['base_url'] ?? '');
			$path = isset($payload['path']) ? (string)$payload['path'] : '';
			$url = rtrim($base, '/') . '/' . ltrim($path, '/');
			$default_headers = is_array($cfg['headers'] ?? null) ? $cfg['headers'] : [];
			$headers = array_merge($default_headers, $headers);
		} else {
			$url = isset($payload['url']) ? esc_url_raw((string)$payload['url']) : '';
		}

		if (!$url) return [ 'ok' => false, 'error' => 'url_required' ];

		$args = [
			'method'  => $method,
			'timeout' => $timeout,
			'headers' => [],
		];

		foreach ($headers as $k => $v) {
			$hk = sanitize_text_field((string)$k);
			if ($hk === '') continue;
			$args['headers'][$hk] = is_scalar($v) ? (string)$v : wp_json_encode($v);
		}

		if (!in_array($method, ['GET','HEAD'], true)) {
			// Auto JSON encode arrays/objects unless body already a string.
			if (is_array($body) || is_object($body)) {
				$has_ct = false;
				foreach ($args['headers'] as $hk => $hv) {
					if (strtolower($hk) === 'content-type') { $has_ct = true; break; }
				}
				if (!$has_ct) {
					$args['headers']['Content-Type'] = 'application/json; charset=utf-8';
				}
				$args['body'] = wp_json_encode($body);
			} else if ($body !== null) {
				$args['body'] = (string)$body;
			}
		}

		$res = wp_remote_request($url, $args);
		if (is_wp_error($res)) {
			return [ 'ok' => false, 'error' => 'http_request_failed', 'message' => $res->get_error_message() ];
		}

		$code = (int) wp_remote_retrieve_response_code($res);
		$rheaders = wp_remote_retrieve_headers($res);
		$resp_body = wp_remote_retrieve_body($res);
		if (is_string($resp_body) && strlen($resp_body) > 10000) {
			$resp_body = substr($resp_body, 0, 10000) . "\n…(truncated)";
		}

		return [
			'ok' => ($code >= 200 && $code < 300),
			'code' => $code,
			'headers' => is_array($rheaders) ? $rheaders : (array)$rheaders,
			'body' => $resp_body,
			'url' => $url,
			'method' => $method,
		];
	}, [
		'capability' => 'manage_options',
		'scope' => 'integrations',
		'unsafe' => true,
		'notes' => 'Realiza una petición HTTP saliente (webhook/API).',
		'tags' => ['http','webhook','integration'],
	]);
};
