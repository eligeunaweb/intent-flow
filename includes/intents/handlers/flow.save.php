<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/../../Flows.php';

return function(INTEFLOW_IntentRegistry $reg) : void {

	$reg->register(
		'flow.save',
		function(array $payload) : array {
			$out = INTEFLOW_Flows::save($payload);
			if (empty($out['ok'])) return $out + ['ok'=>false];
			return ['ok'=>true,'id'=>$out['id'],'flow'=>$out['flow']];
		},
		[
			'capability' => 'manage_options',
			'scope' => 'automation',
			'unsafe' => false,
		]
	);

};
