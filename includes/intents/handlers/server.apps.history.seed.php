<?php
if (!defined('ABSPATH')) { exit; }


return function(INTEFLOW_IntentRegistry $reg) : void {

	$reg->register(
		'server.apps.history.seed',
		function(array $payload) : array {
			$raw_apps = get_option('inteflow_apps', []);
			$arr_apps = is_array($raw_apps) ? $raw_apps : json_decode((string)$raw_apps, true);
			$arr_apps = is_array($arr_apps) ? $arr_apps : [];
			$check = function_exists('inteflow_apps_validate') ? inteflow_apps_validate($arr_apps) : ['ok'=>false,'error'=>'validate_missing'];
			if (empty($check['ok'])) return $check;

			$apps = $check['apps'] ?? [];
			// Allow seeding even if empty, so the UI has a baseline.
			$entry = inteflow_apps_history_push(is_array($apps) ? $apps : [], [
				'source' => 'seed',
				'notes'  => 'Seeded from current apps',
			]);

			return ['ok'=>true,'id'=>$entry['id'] ?? '','count'=> (int)($entry['count'] ?? 0)];
		},
		[
			'capability' => 'manage_options',
			'scope'      => 'core',
			'unsafe'     => false,
			'tags'       => ['apps','history'],
			'notes'      => 'Crea una versión inicial del historial de Apps a partir del estado actual.',
		]
	);
};