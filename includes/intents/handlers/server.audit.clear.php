<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'server.audit.clear',

		function(array $payload, array $meta, array $ctx) : array {

			IF_AuditLog::clear();

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
