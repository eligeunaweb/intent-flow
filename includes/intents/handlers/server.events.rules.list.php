<?php
if (!defined('ABSPATH')) { exit; }

return function(INTEFLOW_IntentRegistry $reg) : void {
	$reg->register(
		'server.events.rules.list',
		function(array $payload) : array {
			if (!class_exists('INTEFLOW_Events')) return ['ok'=>false,'error'=>'events_missing'];
			$rules = INTEFLOW_Events::all_rules();
			return [
				'ok'    => true,
				'rules' => $rules,
			];
		},
		['capability'=>'manage_options','scope'=>'core','unsafe'=>false,'tags'=>['events']]
	);
};
