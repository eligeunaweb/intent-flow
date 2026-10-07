<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Ejecutor de Apps (entidad de primer nivel).
 *
 * Una App puede ser:
 *  - intent + payload
 *  - flow { steps:[ {id,payload,meta}, ... ] }
 *
 * Storage actual: option "if_apps" (array o JSON) para compatibilidad.
 */
final class IF_AppsRunner {
	/** @return array<int, array> */
	public static function all() : array {
		$raw = get_option('if_apps', []);
		$apps = is_array($raw) ? $raw : json_decode((string)$raw, true);
		return is_array($apps) ? $apps : [];
	}

	public static function get(string $app_id) : ?array {
		$app_id = (string) $app_id;
		foreach (self::all() as $a) {
			if (is_array($a) && (string)($a['id'] ?? '') === $app_id) return $a;
		}
		return null;
	}

	/**
	 * Ejecuta una App por id.
	 *
	 * @param array $input Datos de entrada (usables en templates {{input.x}})
	 * @param array $meta  Metadatos adicionales
	 * @param array $options Runner options (source, ensure_admin, audit...)
	 */
	public static function run(string $app_id, array $input = [], array $meta = [], array $options = []) : array {
		$app = self::get($app_id);
		if (!$app) return ['ok'=>false,'error'=>'app_not_found','app_id'=>$app_id];
		if (!class_exists('IF_Runner')) return ['ok'=>false,'error'=>'runner_missing','app_id'=>$app_id];

		$source = (string)($options['source'] ?? 'apps');
		$options = array_merge(['source' => $source], $options);

		$started_at = time();
		$t0 = microtime(true);

		$run_meta = array_merge($meta, [
			'app_id' => $app_id,
		]);

		// 1) App directa (intent + payload)
		$intent = (string)($app['intent'] ?? '');
		if ($intent !== '') {
			$payload = is_array($app['payload'] ?? null) ? $app['payload'] : [];
			$payload = self::resolve_templates($payload, $input, $run_meta);
			$out = IF_Runner::run_intent($intent, $payload, $run_meta, $options);
			return self::wrap_result($app, $intent, $started_at, $t0, [$out], $out);
		}

		// 2) Flow (steps)
		$flow = is_array($app['flow'] ?? null) ? $app['flow'] : null;
		$steps = is_array($flow['steps'] ?? null) ? $flow['steps'] : [];
		if (!$flow || count($steps) === 0) {
			return ['ok'=>false,'error'=>'app_missing_intent_or_flow','app_id'=>$app_id];
		}

		$results = [];
		$last = ['ok'=>true];
		foreach ($steps as $i => $step) {
			if (!is_array($step)) continue;
			$step_intent = (string)($step['id'] ?? '');
			if ($step_intent === '') continue;
			$step_payload = is_array($step['payload'] ?? null) ? $step['payload'] : [];
			$step_meta = is_array($step['meta'] ?? null) ? $step['meta'] : [];

			$step_payload = self::resolve_templates($step_payload, $input, $run_meta);
			$step_meta = array_merge($run_meta, $step_meta, [
				'step_index' => (int)$i,
				'step_id'    => $step_intent,
			]);

			$r = IF_Runner::run_intent($step_intent, $step_payload, $step_meta, $options);
			$results[] = $r;
			$last = $r;

			// Stop on first failure (v1)
			if (empty($r['ok'])) break;
		}

		return self::wrap_result($app, '', $started_at, $t0, $results, $last);
	}

	private static function wrap_result(array $app, string $intent, int $started_at, float $t0, array $results, array $last) : array {
		$finished_at = time();
		$duration_ms = (int) round((microtime(true) - $t0) * 1000);
		return [
			'ok'          => !empty($last['ok']),
			'app_id'      => (string)($app['id'] ?? ''),
			'app_title'   => (string)($app['title'] ?? ''),
			'intent'      => $intent,
			'started_at'  => $started_at,
			'finished_at' => $finished_at,
			'duration_ms' => $duration_ms,
			'results'     => $results,
			'last'        => $last,
			'error'       => (string)($last['error'] ?? ''),
			'message'     => (string)($last['message'] ?? ''),
		];
	}

	// ---------------- Templates ----------------

	private static function resolve_templates($data, array $input, array $meta) {
		if (is_string($data)) {
			return self::resolve_string_template($data, $input, $meta);
		}
		if (is_array($data)) {
			$out = [];
			foreach ($data as $k => $v) {
				$out[$k] = self::resolve_templates($v, $input, $meta);
			}
			return $out;
		}
		return $data;
	}

	private static function resolve_string_template(string $s, array $input, array $meta) : string {
		return preg_replace_callback('/\{\{\s*(input|meta)\.([a-zA-Z0-9_\-]+)\s*\}\}/', function($m) use ($input, $meta) {
			$root = $m[1];
			$key  = $m[2];
			$val = null;
			if ($root === 'input') { $val = $input[$key] ?? null; }
			if ($root === 'meta')  { $val = $meta[$key] ?? null; }
			if (is_scalar($val) || $val === null) return (string)($val ?? '');
			return wp_json_encode($val);
		}, $s);
	}
}
