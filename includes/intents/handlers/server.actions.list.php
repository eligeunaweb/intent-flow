<?php
if (!defined('ABSPATH')) { exit; }

return function(INTEFLOW_IntentRegistry $reg) {
	$reg->register('server.actions.list', function(array $payload, array $meta, array $ctx) : array {
		$actions = class_exists('INTEFLOW_ActionRegistry') ? INTEFLOW_ActionRegistry::get_all() : [];
		return [ 'ok' => true, 'items' => $actions ];
	}, [
		'capability' => 'manage_options',
		'scope' => 'core',
		'notes' => 'List available Actions (UX catalog) mapped to intents.'
	]);
};
