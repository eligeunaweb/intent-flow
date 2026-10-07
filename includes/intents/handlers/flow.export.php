<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/../../Flows.php';

return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'flow.export',
		function(array $payload) : array {
			$all = IF_Flows::all();
			return ['ok'=>true,'flows'=>array_values($all)];
		},
		[
			'capability' => 'manage_options',
			'scope' => 'automation',
			'unsafe' => false,
		]
	);

};
