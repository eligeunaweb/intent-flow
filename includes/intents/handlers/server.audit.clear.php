<?php
if (!defined('ABSPATH')) { exit; }

return function(INTEFLOW_IntentRegistry $reg) : void {

	$reg->register(
		'server.audit.clear',

		function(array $payload, array $meta, array $ctx) : array {

			INTEFLOW_AuditLog::clear();

			return [
				'cleared' => true
			];
		},

		[
			'capability'    => 'manage_options',
			'requires_live' => true,
			'dry_run_exempt'=> true,
			'tags'          => ['audit']
		]
	);
};
