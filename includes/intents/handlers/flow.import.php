<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/../../Flows.php';

return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'flow.import',
		function(array $payload) : array {
			$items = isset($payload['flows']) && is_array($payload['flows']) ? $payload['flows'] : [];
			$overwrite = isset($payload['overwrite']) ? (bool)$payload['overwrite'] : true;
			return IF_Flows::import($items, $overwrite);
		},
		[
			'capability' => 'manage_options',
			'scope' => 'automation',
			'unsafe' => false,
		]
	);

};
