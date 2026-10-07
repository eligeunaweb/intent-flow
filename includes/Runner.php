<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Centralized execution entrypoint for all runtime sources.
 *
 * Scheduler, AJAX UI, Events, Webhooks, and future "Apps" should all execute
 * intents through the same codepath, producing consistent ctx/meta and audit.
 */
final class IF_Runner {

	/** @var null|IF_IntentRegistry */
	private static $registry = null;

	public static function registry() {
		if (self::$registry) return self::$registry;

		$index = IF_PLUGIN_DIR . 'includes/intents/index_0930.php';
		if (!file_exists($index)) return null;

		require_once $index;
		if (!function_exists('if_build_intent_registry')) return null;

		self::$registry = if_build_intent_registry();
		// Allow core+modules to register intents after base handlers.
		do_action('if_register_intents', self::$registry);

		return self::$registry;
	}

	/**
	 * Build an execution context (ctx) from Settings + overrides.
	 */
	public static function ctx(array $overrides = []) : array {
		$settings  = class_exists('IF_Settings') ? IF_Settings::get() : [];
		$ctx = [
			'dry_run'   => !empty($settings['dry_run']),
			'safe_mode' => !empty($settings['safe_mode']),
			'user_id'   => get_current_user_id(),
		];
		return array_merge($ctx, $overrides);
	}

	/**
	 * Ensures there's an admin user set as current user. Useful for WP-Cron.
	 */
	public static function ensure_admin_user() : void {
		if (is_user_logged_in() && current_user_can('manage_options')) {
			return;
		}
		$admin_ids = get_users([
			'role__in' => ['administrator'],
			'number'   => 1,
			'fields'   => 'ID',
		]);
		if (!empty($admin_ids)) {
			// Internal execution — no user context required.
		}
	}

	/**
	 * Execute an intent with consistent audit.
	 *
	 * @param array $options {
	 *   @type string $source         Human source label for audit (e.g. 'ajax', 'scheduler.cron', 'events.wp').
	 *   @type bool   $ensure_admin   If true, set an admin user if none (for cron/webhook contexts).
	 *   @type bool   $audit          If true, record into IF_AuditLog.
	 *   @type array  $ctx            Context overrides.
	 * }
	 */
	public static function run_intent(string $intent_id, array $payload = [], array $meta = [], array $options = []) : array {
		$t0 = microtime(true);

		$source       = isset($options['source']) ? (string)$options['source'] : 'unknown';
		$ensure_admin = !empty($options['ensure_admin']);
		$audit        = !array_key_exists('audit', $options) || !empty($options['audit']);
		$ctx_over     = (isset($options['ctx']) && is_array($options['ctx'])) ? $options['ctx'] : [];

		if ($ensure_admin) {
			// Internal execution context — no user impersonation needed
		}

		$reg = self::registry();
		if (!$reg) {
			$out = [ 'ok'=>false, 'intentId'=>$intent_id, 'error'=>'registry_missing' ];
		} else {
			$ctx  = self::ctx($ctx_over);
			$meta = array_merge([ 'source' => $source ], $meta);
			$out  = $reg->dispatch($intent_id, $payload, $meta, $ctx);
			$out  = is_array($out) ? $out : ['ok'=>false,'intentId'=>$intent_id,'error'=>'invalid_result'];
		}

		// Re-check dry_run after execution (settings may change during execution)
		$settings_after = class_exists('IF_Settings') ? IF_Settings::get() : [];
		$dry_run_after  = !empty($settings_after['dry_run']);

		$duration_ms = (int) round((microtime(true) - $t0) * 1000);
		$out_with_meta = array_merge($out, [
			'dry_run'      => $dry_run_after,
			'duration_ms'  => $duration_ms,
			'exec_source'  => $source,
		]);

		if ($audit && class_exists('IF_AuditLog')) {
			$event = [
				'intentId'     => $intent_id,
				'ok'           => !empty($out['ok']),
				'blocked'      => !empty($out['blocked']),
				'reason'       => $out['reason'] ?? '',
				'dry_run'      => $dry_run_after,
				'duration_ms'  => $duration_ms,
				'source'       => $source,
			];
			if (class_exists('IF_Redact')) {
				$event = IF_Redact::deep($event);
			}
			IF_AuditLog::record($event);
		}

		// Execution log entry (global)
		if (class_exists('IF_ExecutionLog')) {
			$entry = [
				'exec_id'     => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('if_exec_', true),
				'ts'          => time(),
				'source'      => $source,
				'target'      => ['type'=>'intent','id'=>$intent_id],
				'ok'          => !empty($out['ok']),
				'blocked'     => !empty($out['blocked']),
				'dry_run'     => $dry_run_after,
				'duration_ms' => $duration_ms,
				'meta'        => $meta,
				'error'       => (string)($out['error'] ?? ''),
				'message'     => (string)($out['message'] ?? ''),
			];
			IF_ExecutionLog::add($entry);
			$out_with_meta['exec_id'] = $entry['exec_id'];
		}

		return $out_with_meta;
	}

	/**
	 * Execute a unified target: intent/app
	 *
	 * @param array $target Canonical target format: ['type'=>'intent'|'app','id'=>'...']
	 */
	public static function run_target(array $target, array $input = [], array $meta = [], array $options = []) : array {
		$target = class_exists('IF_Target') ? IF_Target::normalize($target) : $target;

		// Retry policy (optional):
		// - retries: int (additional attempts)
		// - backoff_ms: int (base backoff between attempts)
		// - max_backoff_ms: int (cap)
		$retries = isset($options['retries']) ? (int)$options['retries'] : 0;
		$retries = max(0, $retries);
		$max_attempts = 1 + $retries;
		$backoff_ms = isset($options['backoff_ms']) ? (int)$options['backoff_ms'] : 250;
		$backoff_ms = max(0, $backoff_ms);
		$max_backoff_ms = isset($options['max_backoff_ms']) ? (int)$options['max_backoff_ms'] : 5000;
		$max_backoff_ms = max(0, $max_backoff_ms);

		$attempt = 0;
		$errors = [];
		$last = null;

		while ($attempt < $max_attempts) {
			$attempt++;

			$meta_attempt = array_merge($meta, [
				'attempt'      => $attempt,
				'max_attempts' => $max_attempts,
			]);
			$last = self::run_target_once($target, $input, $meta_attempt, $options);

			// Success or blocked -> stop retrying
			if (!empty($last['ok']) || !empty($last['blocked'])) {
				break;
			}

			$err = (string)($last['error'] ?? 'unknown');
			$errors[] = $err;

			if ($attempt >= $max_attempts) {
				break;
			}

			// Exponential backoff: base * 2^(attempt-1)
			$delay = (int) round($backoff_ms * pow(2, max(0, $attempt - 1)));
			if ($max_backoff_ms > 0) {
				$delay = min($delay, $max_backoff_ms);
			}
			if ($delay > 0) {
				usleep($delay * 1000);
			}
		}

		if (!is_array($last)) {
			$last = ['ok'=>false,'error'=>'invalid_result'];
		}

		$last['attempts'] = $attempt;
		$last['retried']  = ($attempt > 1);
		if (!empty($errors)) {
			$last['retry_errors'] = $errors;
		}

		return $last;
	}

	/**
	 * Execute target exactly once (no retries).
	 */
	private static function run_target_once(array $target, array $input, array $meta, array $options) : array {
		$type = (string)($target['type'] ?? 'intent');
		$id   = (string)($target['id'] ?? '');
		if ($id === '') {
			return ['ok'=>false,'error'=>'missing_target','target'=>$target];
		}
		if ($type === 'intent') {
			return self::run_intent($id, $input, $meta, $options);
		}
		if ($type === 'app') {
			$source       = isset($options['source']) ? (string)$options['source'] : 'unknown';
			$ensure_admin = !empty($options['ensure_admin']);
			$audit        = !array_key_exists('audit', $options) || !empty($options['audit']);
			$ctx_over     = (isset($options['ctx']) && is_array($options['ctx'])) ? $options['ctx'] : [];

			if ($ensure_admin) {
				// Internal execution context — no user impersonation needed
			}

			$t0 = microtime(true);
			$ctx = self::ctx($ctx_over);
			$meta = array_merge([ 'source' => $source ], $meta);
			$out = class_exists('IF_AppsRunner')
				? IF_AppsRunner::run($id, $input, $meta, [
					'source'       => $source,
					'ensure_admin' => false,
					'audit'        => $audit,
					'ctx'          => $ctx,
				])
				: ['ok'=>false,'error'=>'apps_runner_missing'];

			$settings_after = class_exists('IF_Settings') ? IF_Settings::get() : [];
			$dry_run_after  = !empty($settings_after['dry_run']);
			$duration_ms = (int) round((microtime(true) - $t0) * 1000);
			$out = is_array($out) ? $out : ['ok'=>false,'error'=>'invalid_result'];
			$out_with_meta = array_merge($out, [
				'dry_run'     => $dry_run_after,
				'duration_ms' => $duration_ms,
				'exec_source' => $source,
			]);

			if (class_exists('IF_ExecutionLog')) {
				$entry = [
					'exec_id'     => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('if_exec_', true),
					'ts'          => time(),
					'source'      => $source,
					'target'      => ['type'=>'app','id'=>$id],
					'ok'          => !empty($out['ok']),
					'blocked'     => !empty($out['blocked']),
					'dry_run'     => $dry_run_after,
					'duration_ms' => $duration_ms,
					'meta'        => $meta,
					'error'       => (string)($out['error'] ?? ''),
					'message'     => (string)($out['message'] ?? ''),
				];
				IF_ExecutionLog::add($entry);
				$out_with_meta['exec_id'] = $entry['exec_id'];
			}

			return $out_with_meta;
		}

		return ['ok'=>false,'error'=>'unsupported_target_type','target'=>$target];
	}
}
