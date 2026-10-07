<?php
if (!defined('ABSPATH')) { exit; }

return [
	'intent' => 'server.execution.list',
	'run' => function(array $ctx) : array {
		if (!class_exists('IF_ExecutionLog')) return ['ok'=>false,'error'=>'execution_log_missing'];
		$p = is_array($ctx['payload'] ?? null) ? $ctx['payload'] : [];
		$filters = [
			'limit'       => isset($p['limit']) ? (int)$p['limit'] : 50,
			'source'      => isset($p['source']) ? (string)$p['source'] : '',
			'target_type' => isset($p['target_type']) ? (string)$p['target_type'] : '',
			'target_id'   => isset($p['target_id']) ? (string)$p['target_id'] : '',
		];
		return [
			'ok' => true,
			'entries' => IF_ExecutionLog::list($filters),
		];
	},
	'capability' => 'manage_options',
	'scope' => 'core',
	'unsafe' => false,
	'tags' => ['execution'],
];
