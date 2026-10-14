<?php
if (!defined('ABSPATH')) { exit; }

return function(INTEFLOW_IntentRegistry $reg) {
	$reg->register('server.actions.get', function(array $payload, array $meta, array $ctx) : array {
		$id = isset($payload['id']) ? (string)$payload['id'] : '';
		if ($id === '') return [ 'ok' => false, 'error' => 'missing_id' ];
		$action = class_exists('INTEFLOW_ActionRegistry') ? INTEFLOW_ActionRegistry::get($id) : null;
		if (!$action) return [ 'ok' => false, 'error' => 'not_found', 'id' => $id ];
		return [ 'ok' => true, 'item' => $action ];
	}, [
		'capability' => 'manage_options',
		'scope' => 'core',
		'notes' => 'Get one Action definition.'
	]);
};
