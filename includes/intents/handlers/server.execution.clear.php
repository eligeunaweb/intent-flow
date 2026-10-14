<?php
if (!defined('ABSPATH')) { exit; }

return [
	'intent' => 'server.execution.clear',
	'run' => function(array $ctx) : array {
		if (!class_exists('INTEFLOW_ExecutionLog')) return ['ok'=>false,'error'=>'execution_log_missing'];
		$p = is_array($ctx['payload'] ?? null) ? $ctx['payload'] : [];
		$filters = [
			'source'      => isset($p['source']) ? (string)$p['source'] : '',
			'target_type' => isset($p['target_type']) ? (string)$p['target_type'] : '',
			'target_id'   => isset($p['target_id']) ? (string)$p['target_id'] : '',
		];
		return INTEFLOW_ExecutionLog::clear($filters);
	},
	'capability' => 'manage_options',
	'scope' => 'core',
	'unsafe' => false,
	'tags' => ['execution'],
];
