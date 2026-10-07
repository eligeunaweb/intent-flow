<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'server.plan.apply',
		function(array $payload, array $meta, array $ctx) use ($reg) : array {

			$plan_id = isset($payload['plan_id']) ? (string)$payload['plan_id'] : '';
			if ($plan_id === '') {
				return ['ok' => false, 'error' => 'missing_plan_id'];
			}

			$state_hash = isset($payload['state_hash']) ? (string)$payload['state_hash'] : '';
			if ($state_hash === '') {
				return [
					'ok'      => false,
					'blocked' => true,
					'reason'  => 'missing_state_hash',
					'message' => 'Falta state_hash. Ejecuta server.plan.run y pasa plan.result.state_hash al apply.',
				];
			}

			$force = !empty($payload['force']);

			// Token challenge (6.7)
			$user_id = (int)($ctx['user_id'] ?? get_current_user_id());
			$expected = substr(hash_hmac('sha256', 'if_settings_toggle:' . $user_id, wp_salt('auth')), 0, 12);
			$token = isset($payload['token']) ? (string)$payload['token'] : '';

			if ($token === '' || !hash_equals($expected, $token)) {
				return [
					'ok'      => false,
					'blocked' => true,
					'reason'  => 'invalid_token',
					'message' => 'Token inválido. Llama a server.settings.challenge y usa payload.token.',
				];
			}

			// No aplicar con dry_run ON
			if (!empty($ctx['dry_run'])) {
				return [
					'ok'      => false,
					'blocked' => true,
					'reason'  => 'dry_run_on',
					'message' => 'No se puede aplicar un plan con dry_run ON. Apágalo con server.settings.set.',
				];
			}

			$plan = get_transient($plan_id);
                if (!is_array($plan) || !array_key_exists('steps', $plan) || !is_array($plan['steps'])) {
                return ['ok' => false, 'error' => 'plan_not_found_or_expired'];
            }

			// Owner check
			if (!empty($plan['user_id']) && (int)$plan['user_id'] !== $user_id) {
				return [
					'ok'      => false,
					'blocked' => true,
					'reason'  => 'plan_owner_mismatch',
				];
			}

			// Gate: no aplicar planes con blocked/failed salvo force=true
			$apply_allowed = !empty($plan['apply_allowed']);
			$has_blocked   = !empty($plan['has_blocked']);
			$has_failed    = !empty($plan['has_failed']);

			if ((!$apply_allowed || $has_blocked || $has_failed) && !$force) {
				return [
					'ok'      => false,
					'blocked' => true,
					'reason'  => 'plan_not_applicable',
					'message' => 'Este plan contiene pasos bloqueados o fallidos. Rehaz el plan o usa payload.force=true (se audita).',
					'plan'    => [
						'apply_allowed' => $apply_allowed,
						'has_blocked'   => $has_blocked,
						'has_failed'    => $has_failed,
						'summary'       => $plan['summary'] ?? null,
					],
				];
			}

			// State hash check (7.3)
			$current_hash = class_exists('IF_PlanState') ? IF_PlanState::state_hash() : '';
			$plan_hash = isset($plan['state_hash']) ? (string)$plan['state_hash'] : '';

			if ($plan_hash !== '' && $current_hash !== '' && !hash_equals($plan_hash, $current_hash)) {
				return [
					'ok'       => false,
					'blocked'  => true,
					'reason'   => 'state_changed',
					'message'  => 'El estado del sitio cambió desde que se generó el plan. Vuelve a ejecutar server.plan.run.',
					'expected' => $plan_hash,
					'current'  => $current_hash,
				];
			}

			if ($current_hash !== '' && !hash_equals($state_hash, $current_hash)) {
				return [
					'ok'      => false,
					'blocked' => true,
					'reason'  => 'state_hash_mismatch',
					'message' => 'state_hash no coincide con el estado actual. Vuelve a ejecutar server.plan.run.',
				];
			}

			$steps = $plan['steps'];
			$results = [];
			$summary = ['total' => 0, 'ok' => 0, 'blocked' => 0, 'failed' => 0];

			foreach ($steps as $i => $step) {

				$id = isset($step['id']) ? sanitize_text_field((string)$step['id']) : '';
				$pl = (isset($step['payload']) && is_array($step['payload'])) ? $step['payload'] : [];
				$mt = (isset($step['meta']) && is_array($step['meta'])) ? $step['meta'] : [];

				if ($id === '') {
					$results[] = ['index' => $i, 'ok' => false, 'error' => 'missing_intent_id'];
					$summary['failed']++;
					continue;
				}

				// Allowlist
				if (class_exists('IF_Allowlist') && !IF_Allowlist::is_allowed($id)) {
					$results[] = [
						'index'    => $i,
						'intentId' => $id,
						'ok'       => false,
						'blocked'  => true,
						'reason'   => 'intent_not_allowed',
					];
					$summary['blocked']++;
					continue;
				}

				$out = $reg->dispatch($id, $pl, $mt, $ctx);

				$item = [
					'index'    => $i,
					'intentId' => $id,
					'ok'       => !empty($out['ok']),
					'blocked'  => !empty($out['blocked']),
					'reason'   => $out['reason'] ?? '',
					'error'    => $out['error'] ?? '',
				];

				if (!empty($out['ok'])) {
					$item['result'] = $out['result'] ?? null;
					$summary['ok']++;
				} else {
					if (!empty($out['blocked'])) $summary['blocked']++;
					else $summary['failed']++;

					if (!empty($out['message'])) $item['message'] = $out['message'];
				}

				$results[] = $item;
			}

			$summary['total'] = count($results);

			delete_transient($plan_id);

			return [
				'applied' => true,
				'forced'  => $force,
				'plan_id' => $plan_id,
				'results' => $results,
				'summary' => $summary,
			];
		},
		[
			'capability'     => 'manage_options',
			'unsafe'         => false,
			'requires_live'  => true,
			'dry_run_exempt' => false,
			'tags'           => ['plan'],
		]
	);
};
