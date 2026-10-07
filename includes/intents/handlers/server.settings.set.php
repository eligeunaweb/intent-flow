<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {
	$reg->register(
		'server.settings.set',
		function(array $payload, array $meta, array $ctx) : array {
			$user_id = (int)($ctx['user_id'] ?? get_current_user_id());

			$expected = substr(hash_hmac('sha256', 'if_settings_toggle:' . $user_id, wp_salt('auth')), 0, 12);
			$token    = isset($payload['token']) ? (string)$payload['token'] : '';

			if ($token === '' || !hash_equals($expected, $token)) {
				return [
					'ok'      => false,
					'error'   => 'invalid_token',
					'message' => 'Token inválido. Llama a server.settings.challenge y usa payload.token.',
				];
			}

			$current = IF_Settings::get();

			// Solo permitimos estas claves (sin UI)
			$next = $current;

			if (array_key_exists('enabled', $payload)) {
				$next['enabled'] = !empty($payload['enabled']);
			}
			if (array_key_exists('safe_mode', $payload)) {
				$next['safe_mode'] = !empty($payload['safe_mode']);
			}
			if (array_key_exists('dry_run', $payload)) {
				$next['dry_run'] = !empty($payload['dry_run']);
			}

			update_option(IF_Settings::OPTION_KEY, $next, false);

			return [
				'updated' => true,
				'settings' => [
					'enabled'   => !empty($next['enabled']),
					'safe_mode' => !empty($next['safe_mode']),
					'dry_run'   => !empty($next['dry_run']),
				],
			];
		},
		[
			'capability'     => 'manage_options',
			'unsafe'         => false,
			'requires_live'  => false, // settings siempre accesible
			'safe_mode_exempt' => true,  // no bloquear por safe_mode
			'dry_run_exempt' => true,  // ✅ excepción controlada (Fase 6.7)
			'tags'           => ['settings'],
		]
	);
};
