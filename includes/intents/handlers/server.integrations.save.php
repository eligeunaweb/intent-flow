<?php
if (!defined('ABSPATH')) { exit; }

return function(INTEFLOW_IntentRegistry $reg) {
	$reg->register('server.integrations.save', function(array $payload, array $meta, array $ctx) : array {
		$item = [
			'id'      => $payload['id'] ?? '',
			'type'    => $payload['type'] ?? '',
			'name'    => $payload['name'] ?? '',
			'enabled' => array_key_exists('enabled', $payload) ? !empty($payload['enabled']) : true,
			'config'  => is_array($payload['config'] ?? null) ? $payload['config'] : [],
		];

		return INTEFLOW_Integrations::save($item);
	}, [ 'capability' => 'manage_options' ]);
};
