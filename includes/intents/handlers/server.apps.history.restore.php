<?php
if (!defined('ABSPATH')) { exit; }


return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'server.apps.history.restore',
		function(array $payload) : array {
			$id = isset($payload['id']) ? (string)$payload['id'] : '';
			if ($id === '') return ['ok'=>false,'error'=>'missing_id'];

			$hist = if_apps_history_get();
			$target = null;
			foreach ($hist as $e) {
				if (!is_array($e)) continue;
				if (isset($e['id']) && (string)$e['id'] === $id) { $target = $e; break; }
			}
			if (!$target) return ['ok'=>false,'error'=>'not_found'];

			$apps = isset($target['apps']) && is_array($target['apps']) ? $target['apps'] : [];
			$check = if_apps_validate($apps);
			if (empty($check['ok'])) return ['ok'=>false,'error'=>'snapshot_invalid'];

			// Snapshot current before restore.
			$current_raw = get_option('if_apps', []);
			$current_arr = is_array($current_raw) ? $current_raw : json_decode((string)$current_raw, true);
			$current_arr = is_array($current_arr) ? $current_arr : [];
			$current_check = if_apps_validate($current_arr);
			$before = !empty($current_check['ok']) ? $current_check['apps'] : [];

			update_option('if_apps', $check['apps'], false);

			$verify = get_option('if_apps', []);
			$verify = is_array($verify) ? $verify : json_decode((string)$verify, true);
			if (!is_array($verify)) {
				update_option('if_apps', $before, false);
				return ['ok'=>false,'error'=>'restore_failed_rollback'];
			}

			// Push history entry for the restore point (this is the automatic "undo" snapshot).
			$undo_entry = if_apps_history_push($before, ['source' => 'restore', 'notes' => 'Restaurar versión ' . $id]);

			return [
				'ok'=>true,
				'restored_id'=>$id,
				'count'=>count($check['apps']),
				'undo_id'=> isset($undo_entry['id']) ? (string) $undo_entry['id'] : '',
			];
		},
		[
			'capability' => 'manage_options',
			'scope'      => 'core',
			'unsafe'     => false,
			'tags'       => ['apps','history'],
			'notes'      => 'Restaura Apps desde el historial (con rollback automático).',
		]
	);
};