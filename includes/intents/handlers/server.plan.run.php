<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'server.plan.run',
		function(array $payload, array $meta, array $ctx) use ($reg) : array {

			$steps = isset($payload['steps']) && is_array($payload['steps']) ? $payload['steps'] : [];
			$maxSteps = 25;

			if (count($steps) === 0) {
				return ['ok' => false, 'error' => 'missing_steps'];
			}
			if (count($steps) > $maxSteps) {
				return ['ok' => false, 'error' => 'too_many_steps', 'max' => $maxSteps];
			}

			$state_hash = class_exists('IF_PlanState') ? IF_PlanState::state_hash() : '';

			// Planning siempre en dry-run
			$planCtx = $ctx;
			$planCtx['dry_run'] = true;
			$planCtx['planning'] = true;

			$results = [];
			$summary = ['total' => 0, 'ok' => 0, 'blocked' => 0, 'failed' => 0];
			$normalizedSteps = [];

			$has_blocked = false;
			$has_failed  = false;

			foreach ($steps as $i => $step) {

				$id = isset($step['id']) ? sanitize_text_field((string)$step['id']) : '';
				$pl = (isset($step['payload']) && is_array($step['payload'])) ? $step['payload'] : [];
				$mt = (isset($step['meta']) && is_array($step['meta'])) ? $step['meta'] : [];

				if ($id === '') {
					$results[] = ['index' => $i, 'ok' => false, 'error' => 'missing_intent_id'];
					$summary['failed']++;
					$has_failed = true;
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
					$has_blocked = true;

                    $normalizedSteps[] = [
                    'id'      => $id,
                    'payload' => [],   // no guardamos nada sensible
                    'meta'    => [],
                    'blocked' => true,
                    'reason'  => 'intent_not_allowed',
                    ];

					continue;
				}

				// Guardar steps redactados
				$pl_safe = class_exists('IF_Redact') ? IF_Redact::deep($pl) : $pl;
				$mt_safe = class_exists('IF_Redact') ? IF_Redact::deep($mt) : $mt;

				$normalizedSteps[] = [
					'id'      => $id,
					'payload' => $pl_safe,
					'meta'    => $mt_safe,
				];

				$out = $reg->dispatch($id, $pl, $mt, $planCtx);

				$item = [
					'index'    => $i,
					'intentId' => $id,
					'ok'       => !empty($out['ok']),
					'blocked'  => !empty($out['blocked']),
					'reason'   => $out['reason'] ?? '',
					'error'    => $out['error'] ?? '',
				];

				if (!empty($out['ok'])) {
					$summary['ok']++;
				} else {
					if (!empty($out['blocked'])) {
						$summary['blocked']++;
						$has_blocked = true;
					} else {
						$summary['failed']++;
						$has_failed = true;
					}
					if (!empty($out['message'])) $item['message'] = $out['message'];
				}

				$results[] = $item;
			}

			$summary['total'] = count($results);

			// Aplicable solo si no hay failed y no hay blocked (por defecto)
			$apply_allowed = (!$has_failed && !$has_blocked);

			try {
				$plan_id = 'if_plan_' . bin2hex(random_bytes(8));
			} catch (Throwable $e) {
				$plan_id = 'if_plan_' . wp_generate_password(16, false, false);
			}

			$ttl = 10 * MINUTE_IN_SECONDS;

			$plan = [
				'plan_id'       => $plan_id,
				'createdAt'     => time(),
				'expiresAt'     => time() + $ttl,
				'user_id'       => (int)($ctx['user_id'] ?? get_current_user_id()),
				'safe_mode'     => !empty($ctx['safe_mode']),
				'state_hash'    => $state_hash,

				'steps'         => $normalizedSteps,

				// Fase 7.4
				'has_blocked'   => $has_blocked,
				'has_failed'    => $has_failed,
				'apply_allowed' => $apply_allowed,
				'summary'       => $summary,
			];

			set_transient($plan_id, $plan, $ttl);

			return [
				'plan_id'       => $plan_id,
				'ttl'           => $ttl,
				'state_hash'    => $state_hash,
				'results'       => $results,
				'summary'       => $summary,

				'has_blocked'   => $has_blocked,
				'has_failed'    => $has_failed,
				'apply_allowed' => $apply_allowed,

				'note'          => 'Plan creado. Usa server.plan.apply con plan_id + token + state_hash. Si apply_allowed=false, necesitas payload.force=true.',
			];
		},
		[
			'capability'    => 'manage_options',
			'unsafe'        => false,
			'requires_live' => false,
			'tags'          => ['plan'],
		]
	);
};
