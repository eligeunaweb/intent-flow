<?php
if (!defined('ABSPATH')) { exit; }

return function(INTEFLOW_IntentRegistry $reg) : void {
	$reg->register(
		'server.events.rules.save',
		function(array $payload) : array {
			if (!class_exists('INTEFLOW_Events')) return ['ok'=>false,'error'=>'events_missing'];
			$event = isset($payload['event']) ? (string)$payload['event'] : '';
			$rule  = (isset($payload['rule']) && is_array($payload['rule'])) ? $payload['rule'] : [];
			return INTEFLOW_Events::upsert_rule($event, $rule);
		},
		['capability'=>'manage_options','scope'=>'core','unsafe'=>false,'tags'=>['events']]
	);
};
