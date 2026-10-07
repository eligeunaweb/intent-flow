<?php
if (!defined('ABSPATH')) { exit; }

final class IF_Ajax {

	// Registry/audit execution moved to IF_Runner

	public static function init() : void {
		add_action('wp_ajax_mmi_intent', [__CLASS__, 'handle']);
	}



	public static function handle() : void {
		// Admin-only
		if (!is_user_logged_in() || !current_user_can('manage_options')) {
			// Lightweight audit via runner (without executing an intent)
			if (class_exists('IF_AuditLog')) {
				IF_AuditLog::record([
					'ts'          => gmdate('c'),
					'user_id'     => get_current_user_id(),
					'intentId'    => '(forbidden)',
					'ok'          => false,
					'blocked'     => true,
					'reason'      => 'admin_only',
					'dry_run'     => true,
					'duration_ms' => 0,
					'source'      => 'ajax',
				]);
			}

			wp_send_json_error([
				'ok'     => false,
				'error'  => 'forbidden',
				'reason' => 'admin_only',
			], 403);
		}

		check_ajax_referer('inteflow_intent', 'nonce');

                $raw = sanitize_text_field(wp_unslash($_POST['intent'] ?? ''));
		$intent = is_string($raw) ? json_decode($raw, true) : null;

		if (!is_array($intent)) {
			wp_send_json_error([
				'ok'    => false,
				'error' => 'bad_request',
				'field' => 'intent',
			], 400);
		}

		$intent_id = isset($intent['id']) ? sanitize_text_field($intent['id']) : '';
		$payload   = (isset($intent['payload']) && is_array($intent['payload'])) ? $intent['payload'] : [];
		$meta      = (isset($intent['meta']) && is_array($intent['meta'])) ? $intent['meta'] : [];

		if ($intent_id === '') {
			wp_send_json_error([
				'ok'    => false,
				'error' => 'missing_intent_id',
			], 400);
		}

		$out = class_exists('IF_Runner')
			? IF_Runner::run_intent($intent_id, $payload, $meta, [ 'source' => 'ajax' ])
			: ['ok'=>false,'intentId'=>$intent_id,'error'=>'runner_missing'];

		$dry_run_after  = !empty($out['dry_run']);

		// RESPUESTA COHERENTE: success true SOLO si out.ok true
		if (!empty($out['ok'])) {
			wp_send_json_success([
				'ok'       => true,
				'intentId' => $intent_id,
				'dry_run'  => $dry_run_after,
				'result'   => $out['result'] ?? null,
			], 200);
		}

		wp_send_json_error([
			'ok'         => false,
			'intentId'   => $intent_id,
			'blocked'    => !empty($out['blocked']),
			'reason'     => $out['reason'] ?? '',
			'capability' => $out['capability'] ?? '',
			'error'      => $out['error'] ?? 'intent_failed',
			'message'    => $out['message'] ?? '',
			'dry_run'    => $dry_run_after,
		], 200);
	}
}
