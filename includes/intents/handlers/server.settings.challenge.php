<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {
	$reg->register(
		'server.settings.challenge',
		function(array $payload, array $meta, array $ctx) : array {
			$user_id = (int)($ctx['user_id'] ?? get_current_user_id());

			// Token derivado de salts + user_id (no se guarda en DB)
			$token = substr(hash_hmac('sha256', 'if_settings_toggle:' . $user_id, wp_salt('auth')), 0, 12);

			return [
				'token'   => $token,
				'user_id' => $user_id,
				'note'    => 'Usa este token como payload.token en server.settings.set',
			];
		},
		[
			'capability'    => 'manage_options',
			'unsafe'        => false,
			'requires_live' => false,
			'tags'          => ['settings'],
		]
	);
};
