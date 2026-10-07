<?php
if (!defined('ABSPATH')) { exit; }


return function(IF_IntentRegistry $reg) : void {

	// Ensure server.apps.save is registered even if this file is loaded before save handler.
	$save_def = require __DIR__ . '/server.apps.save.php';
	if (is_callable($save_def)) {
		$save_def($reg);
	}


	$reg->register(
		'server.apps.import',
		function(array $payload) : array {
			$raw = $payload['json'] ?? null;
			$mode = isset($payload['mode']) && is_string($payload['mode']) ? strtolower(trim($payload['mode'])) : 'merge';
			if (!in_array($mode, ['merge','replace','preview'], true)) $mode = 'merge';

			if (!is_string($raw) || trim($raw) === '') return ['ok'=>false,'error'=>'missing_json'];

			$data = json_decode($raw, true);
			if (!is_array($data)) return ['ok'=>false,'error'=>'invalid_json'];

			// Accept both legacy array format and wrapped export format.
			$apps_in = $data;
			$checksum_in = null;
			$schema_in = null;

			if (isset($data['apps']) && is_array($data['apps'])) {
				$apps_in = $data['apps'];
				$checksum_in = isset($data['checksum']) && is_string($data['checksum']) ? $data['checksum'] : null;
				$schema_in = isset($data['schema']) && is_string($data['schema']) ? $data['schema'] : null;
			}

			if (!is_array($apps_in)) return ['ok'=>false,'error'=>'apps_not_array'];

			// Optional checksum validation (detects corruption / tampering, independent of whitespace).
			if ($checksum_in && is_string($checksum_in) && strpos($checksum_in, 'sha256:') === 0) {
				$calc = 'sha256:' . hash('sha256', wp_json_encode($apps_in, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
				if (!hash_equals($checksum_in, $calc)) {
					return ['ok'=>false,'error'=>'checksum_mismatch','schema'=>$schema_in];
				}
			}

			// Validate / sanitize incoming apps.
			$check = if_apps_validate($apps_in);
			if (empty($check['ok'])) return $check;
			$incoming = $check['apps'];

			// Load current apps (best-effort), validate them too.
			$current_raw = get_option('if_apps', []);
			$current_arr = is_array($current_raw) ? $current_raw : json_decode((string)$current_raw, true);
			$current_arr = is_array($current_arr) ? $current_arr : [];
			$current_check = if_apps_validate($current_arr);
			$current = !empty($current_check['ok']) ? $current_check['apps'] : [];

			// Build diff (by id).
			$cur_map = [];
			foreach ($current as $a) { if (isset($a['id'])) $cur_map[$a['id']] = $a; }
			$in_map = [];
			foreach ($incoming as $a) { if (isset($a['id'])) $in_map[$a['id']] = $a; }

			$added = [];
			$updated = [];
			foreach ($in_map as $id => $a) {
				if (!isset($cur_map[$id])) $added[] = $id;
				else $updated[] = $id;
			}
			$removed = [];
			if ($mode === 'replace') {
				foreach ($cur_map as $id => $_a) {
					if (!isset($in_map[$id])) $removed[] = $id;
				}
			}

			$summary = [
				'added' => $added,
				'updated' => $updated,
				'removed' => $removed,
			];

			if ($mode === 'preview') {
				return ['ok'=>true,'mode'=>'preview','count_in'=>count($incoming),'count_current'=>count($current),'diff'=>$summary];
			}

			// Merge or Replace.
			$before = $current; // already validated
			try {
				if ($mode === 'merge') {
					// Keep current order, replace updated items, append new ones.
					$out = [];
					$seen = [];
					foreach ($before as $a) {
						$id = $a['id'];
						if (isset($in_map[$id])) { $out[] = $in_map[$id]; $seen[$id] = true; }
						else { $out[] = $a; $seen[$id] = true; }
					}
					foreach ($incoming as $a) {
						$id = $a['id'];
						if (!isset($seen[$id])) { $out[] = $a; $seen[$id] = true; }
					}
				} else {
					$out = $incoming;
				}

				update_option('if_apps', $out, false);

				// Verify persisted value is readable.
				$verify = get_option('if_apps', []);
				$verify = is_array($verify) ? $verify : json_decode((string)$verify, true);
				if (!is_array($verify)) {
					update_option('if_apps', $before, false);
					return ['ok'=>false,'error'=>'import_failed_rollback'];
				}

				
				// Push history only after success.
				if_apps_history_push($before, ['source' => 'import', 'mode' => $mode, 'notes' => 'Importar Apps']);

				return [
					'ok'=>true,
					'mode'=>$mode,
					'count'=>count($out),
					'diff'=>$summary,
					'schema'=>$schema_in,
				];
			} catch (Throwable $e) {
				update_option('if_apps', $before, false);
				return ['ok'=>false,'error'=>'import_exception_rollback','message'=>$e->getMessage()];
			}
		},
		[
			'capability' => 'manage_options',
			'scope'      => 'core',
			'unsafe'     => false,
			'tags'       => ['apps'],
			'notes'      => 'Importa Apps desde JSON.',
		]
	);
};
