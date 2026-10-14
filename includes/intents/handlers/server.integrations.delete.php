<?php
if (!defined('ABSPATH')) { exit; }

return function(INTEFLOW_IntentRegistry $reg) {
	$reg->register('server.integrations.delete', function(array $payload, array $meta, array $ctx) : array {
		$id = isset($payload['id']) ? (string)$payload['id'] : '';
		return INTEFLOW_Integrations::delete($id);
	}, [ 'capability' => 'manage_options' ]);
};
