<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) {
	$reg->register('server.actions.execute', function(array $payload, array $meta, array $ctx) : array {
		$action_id = isset($payload['action_id']) ? (string)$payload['action_id'] : '';
		$input = isset($payload['input']) && is_array($payload['input']) ? $payload['input'] : [];
		$options = isset($payload['options']) && is_array($payload['options']) ? $payload['options'] : [];

		if ($action_id === '') return [ 'ok' => false, 'error' => 'missing_action_id' ];
		if (!class_exists('IF_ActionExecutor')) return [ 'ok' => false, 'error' => 'actions_not_available' ];

		// Ensure source.
		$meta2 = is_array($meta) ? $meta : [];
		$meta2['source'] = $meta2['source'] ?? 'manual.action';

		return IF_ActionExecutor::execute($action_id, $input, $meta2, $options);
	}, [
		'capability' => 'manage_options',
		'scope' => 'core',
		'notes' => 'Execute an Action by action_id using Runner->Target.'
	]);
};
