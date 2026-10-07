<?php
if (!defined('ABSPATH')) { exit; }

/**
 * slack.send
 *
 * payload:
 *  - connection_id (slack.webhook) OR webhook_url
 *  - text (string)
 *  - blocks (array) optional
 *  - attachments (array) optional
 */
return function(IF_IntentRegistry $reg) {
	$reg->register('slack.send', function(array $payload, array $meta, array $ctx) : array {
		$webhook = '';
		$connection_id = isset($payload['connection_id']) ? sanitize_key((string)$payload['connection_id']) : '';
		if ($connection_id) {
			$conn = IF_Integrations::get($connection_id);
			if (!$conn || empty($conn['enabled']) || ($conn['type'] ?? '') !== 'slack.webhook') {
				return [ 'ok' => false, 'error' => 'slack_connection_not_found' ];
			}
			$cfg = is_array($conn['config'] ?? null) ? $conn['config'] : [];
			$webhook = (string)($cfg['webhook_url'] ?? '');
		} else {
			$webhook = isset($payload['webhook_url']) ? esc_url_raw((string)$payload['webhook_url']) : '';
		}
		if (!$webhook) return [ 'ok' => false, 'error' => 'webhook_url_required' ];

		$data = [];
		$text = isset($payload['text']) ? (string)$payload['text'] : '';
		if ($text !== '') $data['text'] = $text;
		if (is_array($payload['blocks'] ?? null)) $data['blocks'] = $payload['blocks'];
		if (is_array($payload['attachments'] ?? null)) $data['attachments'] = $payload['attachments'];

		if (empty($data)) return [ 'ok' => false, 'error' => 'empty_message' ];

		$res = wp_remote_post($webhook, [
			'timeout' => 15,
			'headers' => [ 'Content-Type' => 'application/json; charset=utf-8' ],
			'body' => wp_json_encode($data),
		]);
		if (is_wp_error($res)) {
			return [ 'ok' => false, 'error' => 'slack_request_failed', 'message' => $res->get_error_message() ];
		}
		$code = (int) wp_remote_retrieve_response_code($res);
		$body = wp_remote_retrieve_body($res);
		return [
			'ok' => ($code >= 200 && $code < 300),
			'code' => $code,
			'body' => is_string($body) ? $body : '',
		];
	}, [
		'capability' => 'manage_options',
		'scope' => 'integrations',
		'unsafe' => true,
		'notes' => 'Envía un mensaje a Slack vía Incoming Webhook.',
		'tags' => ['slack','webhook','integration'],
	]);
};
