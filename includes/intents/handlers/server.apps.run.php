<?php
if (!defined('ABSPATH')) { exit; }

return [
	'intent' => 'server.apps.run',
	'run' => function(array $ctx) : array {
		if (!class_exists('IF_AppsRunner')) return ['ok'=>false,'error'=>'apps_runner_missing'];

		$payload = is_array($ctx['payload'] ?? null) ? $ctx['payload'] : [];
		$app_id  = sanitize_text_field((string)($payload['id'] ?? $payload['app_id'] ?? ''));
		$input   = is_array($payload['input'] ?? null) ? $payload['input'] : [];
		$meta    = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
		if ($app_id === '') return ['ok'=>false,'error'=>'missing_app_id'];

		$opts = [
			'source'       => (string)($ctx['meta']['source'] ?? 'ajax'),
			'ensure_admin' => !empty($ctx['meta']['ensure_admin']),
			'audit'        => true,
		];

		return IF_AppsRunner::run($app_id, $input, $meta, $opts);
	},
	'capability' => 'manage_options',
	'scope' => 'core',
	'unsafe' => false,
	'tags' => ['apps'],
	'notes' => 'Ejecuta una App por ID (intent directo o flow).',
];
