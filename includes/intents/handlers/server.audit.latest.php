<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'server.audit.latest',

		function(array $payload, array $meta, array $ctx) : array {

			$limit = isset($payload['limit'])
				? max(1, min(200, (int)$payload['limit']))
				: 20;

			return [
				'events' => IF_AuditLog::latest($limit)
			];
		},

		[
			'capability' => 'manage_options',
			'tags'       => ['audit']
		]
	);
};
