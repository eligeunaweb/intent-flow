<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {
	$reg->register(
		'server.events.rules.save',
		function(array $payload) : array {
			if (!class_exists('IF_Events')) return ['ok'=>false,'error'=>'events_missing'];
			$event = isset($payload['event']) ? (string)$payload['event'] : '';
			$rule  = (isset($payload['rule']) && is_array($payload['rule'])) ? $payload['rule'] : [];
			return IF_Events::upsert_rule($event, $rule);
		},
		['capability'=>'manage_options','scope'=>'core','unsafe'=>false,'tags'=>['events']]
	);
};
