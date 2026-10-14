<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/../../Presets.php';

return function(INTEFLOW_IntentRegistry $reg) : void {

	$reg->register(
		'server.presets.export',
		function(array $payload) : array {
			$all = INTEFLOW_Presets::all();
			return ['ok'=>true,'presets'=>array_values($all)];
		},
		[
			'capability' => 'manage_options',
			'scope' => 'automation',
			'unsafe' => false,
		]
	);

};
