<?php
if (!defined('ABSPATH')) { exit; }

final class IF_IntentRegistry {
	/**
	 * @var array<string, array{handler: callable, policy: array}>
	 */
	private $map = [];

	public function register(string $id, callable $handler, array $policy = []) : void {
		$id = trim($id);
		if ($id === '') {
			throw new InvalidArgumentException('Intent id vacío');
		}

		$defaults = [
			'capability'     => 'manage_options',
			'scope'          => 'core',          // ✅ NUEVO
			'unsafe'         => false,
			'requires_live'  => false,
			'dry_run_exempt' => false,
			'notes'          => '',
			'tags'           => [],
		];

		$merged = array_merge($defaults, $policy);

		$merged['capability'] = (is_string($merged['capability']) && $merged['capability'] !== '')
			? $merged['capability']
			: 'manage_options';

		$merged['scope'] = (is_string($merged['scope']) && $merged['scope'] !== '')
			? $merged['scope']
			: 'core';

		$merged['unsafe']         = !empty($merged['unsafe']);
		$merged['requires_live']  = !empty($merged['requires_live']);
		$merged['dry_run_exempt'] = !empty($merged['dry_run_exempt']);

		if (!is_array($merged['tags'])) {
			$merged['tags'] = [];
		}

		$this->map[$id] = [
			'handler' => $handler,
			'policy'  => $merged,
		];
	}

	public function has(string $id) : bool {
		return isset($this->map[$id]);
	}

	public function getPolicy(string $id) : ?array {
		return $this->has($id) ? $this->map[$id]['policy'] : null;
	}

	public function dispatch(string $id, array $payload, array $meta, array $ctx) : array {
		if (!$this->has($id)) {
			return [
				'ok'       => false,
				'intentId' => $id,
				'error'    => 'unknown_intent',
			];
		}

		$entry  = $this->map[$id];
		$policy = $entry['policy'];

		// Capability gate
		$cap = $policy['capability'] ?? 'manage_options';
		if (!is_user_logged_in() || !current_user_can($cap)) {
			return [
				'ok'         => false,
				'intentId'   => $id,
				'blocked'    => true,
				'reason'     => 'capability_denied',
				'capability' => $cap,
				'error'      => 'blocked',
			];
		}

		// ✅ Scope gate (Fase 8.0)
		$scope = (string)($policy['scope'] ?? 'core');
		$user_id = (int)($ctx['user_id'] ?? get_current_user_id());

		if (class_exists('IF_Scopes') && $scope !== '' && $scope !== 'core') {
			if (!IF_Scopes::is_allowed($user_id, $scope)) {
				return [
					'ok'       => false,
					'intentId' => $id,
					'blocked'  => true,
					'reason'   => 'scope_denied',
					'error'    => 'blocked',
					'scope'    => $scope,
				];
			}
		}

		// safe_mode blocks unsafe (unless Live session is active)
		$safe_mode = !empty($ctx['safe_mode']);

		// Live session (global) — enabled via server.session.live.enable
		$live_until = (int) get_option('macro_intent_live_until', 0);
		$live_active = ($live_until > 0) && ($live_until >= time());

		// Allow handlers to additionally force-disable live through ctx if ever needed
		if (array_key_exists('live_active', $ctx)) {
			$live_active = !empty($ctx['live_active']);
		}

		// If the intent requires Live, enforce it regardless of safe_mode
		if (!empty($policy['requires_live']) && !$live_active) {
			return [
				'ok'       => false,
				'intentId' => $id,
				'blocked'  => true,
				'reason'   => 'live_required',
				'error'    => 'blocked',
				'live'     => false,
			];
		}

		// In safe_mode, unsafe intents are blocked unless Live is active
		if ($safe_mode && !empty($policy['unsafe']) && !$live_active && empty($policy['safe_mode_exempt'])) {
			return [
				'ok'       => false,
				'intentId' => $id,
				'blocked'  => true,
				'reason'   => 'safe_mode_block',
				'error'    => 'blocked',
				'live'     => false,
			];
		}

		// dry-run enforcement
		$dry_run = !empty($ctx['dry_run']);
		if ($dry_run && !empty($policy['requires_live']) && empty($policy['dry_run_exempt'])) {
			return [
				'ok'       => false,
				'intentId' => $id,
				'blocked'  => true,
				'reason'   => 'dry_run_on',
				'error'    => 'blocked',
			];
		}

		try {
			$fn  = $entry['handler'];
			$res = $fn($payload, $meta, $ctx);

			// Si el handler devuelve { ok:false ... } lo tratamos como fallo real
			if (is_array($res) && array_key_exists('ok', $res) && empty($res['ok'])) {
				$out = [
					'ok'       => false,
					'intentId' => $id,
					'error'    => $res['error'] ?? 'handler_failed',
					'message'  => $res['message'] ?? '',
				];

				if (!empty($res['blocked'])) {
					$out['blocked'] = true;
					$out['reason']  = $res['reason'] ?? 'blocked';
					$out['error']   = 'blocked';
				}

				if (!empty($res['capability'])) $out['capability'] = $res['capability'];
				if (!empty($res['scope'])) $out['scope'] = $res['scope'];
				if (!empty($res['reason'])) $out['reason'] = $res['reason'];

				return $out;
			}

			return [
				'ok'       => true,
				'intentId' => $id,
				'result'   => $res,
			];
		}
		catch (Throwable $e) {
			return [
				'ok'       => false,
				'intentId' => $id,
				'error'    => 'intent_failed',
				'message'  => $e->getMessage(),
			];
		}
	}
}
