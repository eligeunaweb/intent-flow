<?php
if (!defined('ABSPATH')) { exit; }

if (!function_exists('if_apps_sanitize_array')) {
function if_apps_sanitize_array($v, $depth = 0) {
	if ($depth > 6) return null;
	if (is_string($v)) return wp_kses_post($v);
	if (is_int($v) || is_float($v) || is_bool($v) || $v === null) return $v;
	if (is_array($v)) {
		$out = [];
		$kcount = 0;
		foreach ($v as $k => $vv) {
			$kcount++;
			if ($kcount > 200) break;
			$kk = is_string($k) ? sanitize_key($k) : (string)$k;
			$out[$kk] = if_apps_sanitize_array($vv, $depth + 1);
		}
		return $out;
	}
	return null;
}

function if_apps_validate($apps) {
	if (!is_array($apps)) return ['ok'=>false,'error'=>'apps_not_array'];
	if (count($apps) > 50) return ['ok'=>false,'error'=>'apps_too_many'];
	$clean = [];
	foreach ($apps as $a) {
		if (!is_array($a)) continue;
		$id = isset($a['id']) ? sanitize_key((string)$a['id']) : '';
		$title = isset($a['title']) ? sanitize_text_field((string)$a['title']) : '';
		if ($id === '' || $title === '') continue;

		$item = [
			'id' => $id,
			'title' => $title,
		];

		if (!empty($a['description'])) {
			$item['description'] = sanitize_text_field((string)$a['description']);
		}

		if (!empty($a['intent']) && is_string($a['intent'])) {
			$item['intent'] = sanitize_text_field($a['intent']);
			$item['payload'] = isset($a['payload']) && is_array($a['payload']) ? if_apps_sanitize_array($a['payload']) : [];
		}

		if (!empty($a['flow']) && is_array($a['flow'])) {
			// Flow shape: {name, steps:[{id,payload}]}
			$flow = $a['flow'];
			$fname = isset($flow['name']) ? sanitize_text_field((string)$flow['name']) : $title;
			$steps = isset($flow['steps']) && is_array($flow['steps']) ? $flow['steps'] : [];
			$steps_clean = [];
			foreach ($steps as $s) {
				if (!is_array($s)) continue;
				$sid = isset($s['id']) ? sanitize_text_field((string)$s['id']) : '';
				if ($sid === '') continue;
				$steps_clean[] = [
					'id' => $sid,
					'payload' => isset($s['payload']) && is_array($s['payload']) ? if_apps_sanitize_array($s['payload']) : [],
				];
				if (count($steps_clean) >= 20) break;
			}
			$item['flow'] = ['name'=>$fname,'steps'=>$steps_clean];
		}

		$clean[] = $item;
	}
	return ['ok'=>true,'apps'=>$clean];
}
}


if (!function_exists('if_apps_history_push')) {
	function if_apps_history_get() : array {
		$raw = get_option('if_apps_history', []);
		$arr = is_array($raw) ? $raw : json_decode((string)$raw, true);
		return is_array($arr) ? $arr : [];
	}

	function if_apps_history_push(array $apps_snapshot, array $meta = []) : array {
		// Store newest-first, cap length for safety.
		$hist = if_apps_history_get();
		$checksum = 'sha256:' . hash('sha256', wp_json_encode($apps_snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$entry = [
			'id' => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('if_', true),
			'at' => gmdate('c'),
			'user' => function_exists('get_current_user_id') ? (int) get_current_user_id() : 0,
			'source' => isset($meta['source']) ? sanitize_key((string)$meta['source']) : 'unknown',
			'mode' => isset($meta['mode']) ? sanitize_key((string)$meta['mode']) : null,
			'notes' => isset($meta['notes']) ? sanitize_text_field((string)$meta['notes']) : '',
			'count' => count($apps_snapshot),
			'checksum' => $checksum,
			'apps' => $apps_snapshot,
		];

		array_unshift($hist, $entry);

		$max = 20;
		if (isset($meta['max']) && is_int($meta['max']) && $meta['max'] >= 5 && $meta['max'] <= 100) {
			$max = $meta['max'];
		}
		if (count($hist) > $max) {
			$hist = array_slice($hist, 0, $max);
		}

		update_option('if_apps_history', $hist, false);
		return $entry;
	}
}

return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'server.apps.save',
		function(array $payload) : array {
			$apps = $payload['apps'] ?? null;
			$check = if_apps_validate($apps);
			if (empty($check['ok'])) return $check;

			
$clean = $check['apps'];

// Snapshot previous value for history (best-effort).
$before_raw = get_option('if_apps', []);
$before_arr = is_array($before_raw) ? $before_raw : json_decode((string)$before_raw, true);
$before_arr = is_array($before_arr) ? $before_arr : [];
$before_check = if_apps_validate($before_arr);
$before = !empty($before_check['ok']) ? $before_check['apps'] : [];

update_option('if_apps', $clean, false);

// Verify persisted value is readable.
$verify = get_option('if_apps', []);
$verify = is_array($verify) ? $verify : json_decode((string)$verify, true);
if (!is_array($verify)) {
	update_option('if_apps', $before, false);
	return ['ok'=>false,'error'=>'save_failed_rollback'];
}

// Push history only after success.
if_apps_history_push($before, ['source' => 'save', 'notes' => 'Guardar Apps']);

return ['ok'=>true,'count'=>count($clean)];

		},
		[
			'capability' => 'manage_options',
			'scope'      => 'core',
			'unsafe'     => false,
			'tags'       => ['apps'],
			'notes'      => 'Guarda la configuración de Apps en una opción de WP.',
		]
	);
};
