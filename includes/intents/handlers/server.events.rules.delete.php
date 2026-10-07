<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {
	$reg->register(
		'server.events.rules.delete',
		function(array $payload) : array {
			if (!class_exists('IF_Events')) return ['ok'=>false,'error'=>'events_missing'];
			$event   = isset($payload['event']) ? (string)$payload['event'] : '';
			$rule_id = isset($payload['rule_id']) ? (string)$payload['rule_id'] : '';
			return IF_Events::delete_rule($event, $rule_id);
		},
		['capability'=>'manage_options','scope'=>'core','unsafe'=>false,'tags'=>['events']]
	);
};
