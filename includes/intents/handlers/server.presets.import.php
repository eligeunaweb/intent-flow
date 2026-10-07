<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/../../Presets.php';

return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'server.presets.import',
		function(array $payload) : array {
			$items = isset($payload['presets']) && is_array($payload['presets']) ? $payload['presets'] : [];
			$overwrite = isset($payload['overwrite']) ? (bool)$payload['overwrite'] : true;
			return IF_Presets::import($items, $overwrite);
		},
		[
			'capability' => 'manage_options',
			'scope' => 'automation',
			'unsafe' => false,
		]
	);

};
