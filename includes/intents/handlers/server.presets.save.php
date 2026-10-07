<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/../../Presets.php';

return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'server.presets.save',
		function(array $payload) : array {
			// payload: {id?, name, intentId, payload}
			$out = IF_Presets::save($payload);
			if (empty($out['ok'])) return $out + ['ok'=>false];
			return ['ok'=>true,'id'=>$out['id'],'preset'=>$out['preset']];
		},
		[
			'capability' => 'manage_options',
			'scope' => 'automation',
			'unsafe' => false,
		]
	);

};
