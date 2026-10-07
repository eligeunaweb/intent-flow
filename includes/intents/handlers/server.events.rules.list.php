<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {
	$reg->register(
		'server.events.rules.list',
		function(array $payload) : array {
			if (!class_exists('IF_Events')) return ['ok'=>false,'error'=>'events_missing'];
			$rules = IF_Events::all_rules();
			return [
				'ok'    => true,
				'rules' => $rules,
			];
		},
		['capability'=>'manage_options','scope'=>'core','unsafe'=>false,'tags'=>['events']]
	);
};
