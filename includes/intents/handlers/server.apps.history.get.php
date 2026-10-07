<?php
if (!defined('ABSPATH')) { exit; }


return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'server.apps.history.get',
		function(array $payload) : array {
			$id = isset($payload['id']) ? (string)$payload['id'] : '';
			if ($id === '') return ['ok'=>false,'error'=>'missing_id'];

			$hist = if_apps_history_get();
			foreach ($hist as $e) {
				if (!is_array($e)) continue;
				if (!isset($e['id']) || (string)$e['id'] !== $id) continue;

				$apps = isset($e['apps']) && is_array($e['apps']) ? $e['apps'] : [];
				// Validate snapshot for safety.
				$check = if_apps_validate($apps);
				if (empty($check['ok'])) return ['ok'=>false,'error'=>'snapshot_invalid'];

				return [
					'ok'=>true,
					'item'=>[
						'id'=>(string)$e['id'],
						'at'=>(string)($e['at'] ?? ''),
						'user'=>(int)($e['user'] ?? 0),
						'source'=>(string)($e['source'] ?? ''),
						'mode'=> isset($e['mode']) ? (string)$e['mode'] : null,
						'notes'=>(string)($e['notes'] ?? ''),
						'count'=>(int)($e['count'] ?? count($check['apps'])),
						'checksum'=>(string)($e['checksum'] ?? ''),
					],
					'apps'=>$check['apps'],
				];
			}
			return ['ok'=>false,'error'=>'not_found'];
		},
		[
			'capability' => 'manage_options',
			'scope'      => 'core',
			'unsafe'     => false,
			'tags'       => ['apps','history'],
			'notes'      => 'Devuelve una versión concreta del historial de Apps.',
		]
	);
};