<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'server.scopes.set',
		function(array $payload, array $meta, array $ctx) : array {

			$user_id = (int)($ctx['user_id'] ?? get_current_user_id());
			$expected = substr(hash_hmac('sha256', 'if_settings_toggle:' . $user_id, wp_salt('auth')), 0, 12);
			$token = isset($payload['token']) ? (string)$payload['token'] : '';

			if ($token === '' || !hash_equals($expected, $token)) {
				return [
					'ok'      => false,
					'blocked' => true,
					'reason'  => 'invalid_token',
					'message' => 'Token inválido. Usa server.settings.challenge.',
				];
			}

			$map = isset($payload['scopes_by_role']) && is_array($payload['scopes_by_role'])
				? $payload['scopes_by_role']
				: null;

			if (!$map) {
				return ['ok'=>false,'error'=>'missing_scopes_by_role'];
			}

			$s = IF_Settings::get();
			$s['scopes_by_role'] = $map;
			update_option(IF_Settings::OPTION_KEY, $s, false);

			return ['updated'=>true,'scopes_by_role'=>$map];
		},
		[
			'capability'     => 'manage_options',
			'scope'          => 'options',
			'unsafe'         => true,
			'requires_live'  => true,
			'dry_run_exempt' => true,
		]
	);

};
