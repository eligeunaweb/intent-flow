<?php
if (!defined('ABSPATH')) { exit; }

return [
	'intent' => 'server.execution.get',
	'run' => function(array $ctx) : array {
		if (!class_exists('IF_ExecutionLog')) return ['ok'=>false,'error'=>'execution_log_missing'];
		$p = is_array($ctx['payload'] ?? null) ? $ctx['payload'] : [];
		$id = isset($p['exec_id']) ? (string)$p['exec_id'] : '';
		return IF_ExecutionLog::get($id);
	},
	'capability' => 'manage_options',
	'scope' => 'core',
	'unsafe' => false,
	'tags' => ['execution'],
];
