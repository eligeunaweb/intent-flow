<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Unified execution endpoint (manual/API).
 *
 * Payload:
 *  - target: {type:'intent'|'app', id:string}
 *  - input: array
 *  - meta: array
 *  - options: array (source, ensure_admin, audit, ctx overrides)
 */
return [
	'intent' => 'server.run',
	'run' => function(array $ctx) : array {
		if (!class_exists('IF_Runner')) return ['ok'=>false,'error'=>'runner_missing'];
		$payload = is_array($ctx['payload'] ?? null) ? $ctx['payload'] : [];
		$target  = is_array($payload['target'] ?? null) ? $payload['target'] : [];
		$input   = is_array($payload['input'] ?? null) ? $payload['input'] : [];
		$meta    = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
		$options = is_array($payload['options'] ?? null) ? $payload['options'] : [];
		if (empty($options['source'])) {
			$options['source'] = 'manual';
		}
		return IF_Runner::run_target($target, $input, $meta, $options);
	},
	'capability' => 'manage_options',
	'scope' => 'core',
	'unsafe' => true,
	'tags' => ['runner'],
];
