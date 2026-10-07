<?php
if (!defined('ABSPATH')) { exit; }


return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'server.apps.history.list',
		function(array $payload) : array {
			$hist = if_apps_history_get();

			// Seed history on first run so the "Versiones" UI isn't empty after upgrading.
			if (empty($hist)) {
				$raw_apps = get_option('if_apps', []);
				$arr_apps = is_array($raw_apps) ? $raw_apps : json_decode((string)$raw_apps, true);
				$arr_apps = is_array($arr_apps) ? $arr_apps : [];
				$valid = function_exists('if_apps_validate') ? if_apps_validate($arr_apps) : ['ok'=>false];
				if (!empty($valid['ok']) && !empty($valid['apps'])) {
					if_apps_history_push($valid['apps'], ['source' => 'seed', 'notes' => 'Seeded on first history list after upgrade']);
					$hist = if_apps_history_get();
				}
			}

			$items = [];
			foreach ($hist as $e) {
				if (!is_array($e)) continue;
				$items[] = [
					'id' => isset($e['id']) ? (string)$e['id'] : '',
					'at' => isset($e['at']) ? (string)$e['at'] : '',
					'user' => isset($e['user']) ? (int)$e['user'] : 0,
					'source' => isset($e['source']) ? (string)$e['source'] : '',
					'mode' => isset($e['mode']) ? (string)$e['mode'] : null,
					'notes' => isset($e['notes']) ? (string)$e['notes'] : '',
					'count' => isset($e['count']) ? (int)$e['count'] : 0,
					'checksum' => isset($e['checksum']) ? (string)$e['checksum'] : null,
				];
				if (count($items) >= 50) break;
			}
			return ['ok'=>true,'items'=>$items,'count'=>count($items)];
		},
		[
			'capability' => 'manage_options',
			'scope'      => 'core',
			'unsafe'     => false,
			'tags'       => ['apps','history'],
			'notes'      => 'Lista el historial de versiones de Apps (metadatos).',
		]
	);
};