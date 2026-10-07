<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) {
	$reg->register('server.actions.list', function(array $payload, array $meta, array $ctx) : array {
		$actions = class_exists('IF_ActionRegistry') ? IF_ActionRegistry::get_all() : [];
		return [ 'ok' => true, 'items' => $actions ];
	}, [
		'capability' => 'manage_options',
		'scope' => 'core',
		'notes' => 'List available Actions (UX catalog) mapped to intents.'
	]);
};
