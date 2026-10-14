<?php
if (!defined('ABSPATH')) { exit; }

return function(INTEFLOW_IntentRegistry $reg) : void {
	$reg->register(
		'server.settings.get',
		function(array $payload, array $meta, array $ctx) : array {
			$settings = INTEFLOW_Settings::get();

			return [
				'settings' => [
					'enabled'   => !empty($settings['enabled']),
					'safe_mode' => !empty($settings['safe_mode']),
					'dry_run'   => !empty($settings['dry_run']),
				],
			];
		},
		[
			'capability'    => 'manage_options',
			'unsafe'        => false,
			'requires_live' => false,
			'tags'          => ['settings'],
		]
	);
};
