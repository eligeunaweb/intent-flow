<?php
if (!defined('ABSPATH')) { exit; }

return function(INTEFLOW_IntentRegistry $reg) : void {

	$reg->register(
		'server.apps.history.clear',
		function(array $payload) : array {
			update_option('inteflow_apps_history', [], false);
			return ['ok'=>true,'cleared'=>true];
		},
		[
			'capability' => 'manage_options',
			'scope'      => 'core',
			'unsafe'     => false,
			'tags'       => ['apps','history'],
			'notes'      => 'Borra el historial de Apps.',
		]
	);
};
