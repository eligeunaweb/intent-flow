<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {
	$reg->register(
		'server.events.emit',
		function(array $payload, array $meta) : array {
			if (!class_exists('IF_Events')) return ['ok'=>false,'error'=>'events_missing'];
			$event   = isset($payload['event']) ? (string)$payload['event'] : '';
			$ep      = (isset($payload['payload']) && is_array($payload['payload'])) ? $payload['payload'] : [];
			$m       = (isset($payload['meta']) && is_array($payload['meta'])) ? $payload['meta'] : [];
			$m = array_merge(['source'=>'events.ui','user_id'=>get_current_user_id()], $m);
			return IF_Events::emit($event, $ep, $m);
		},
		['capability'=>'manage_options','scope'=>'core','unsafe'=>false,'tags'=>['events','debug']]
	);
};
