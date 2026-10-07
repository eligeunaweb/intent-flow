<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'server.scopes.get',
		function(array $payload, array $meta, array $ctx) : array {
			return [
				'scopes_by_role' => class_exists('IF_Scopes') ? IF_Scopes::get_map() : [],
			];
		},
		[
			'capability' => 'manage_options',
			'scope'      => 'diagnostics',
		]
	);

};
