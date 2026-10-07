<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'server.apps.get',
		function(array $payload) : array {
			$raw = get_option('if_apps', null);
			if ($raw === null || $raw === '') {
				return ['ok'=>true,'apps'=>[],'source'=>'default'];
			}
			$apps = is_array($raw) ? $raw : json_decode((string)$raw, true);
			if (!is_array($apps)) {
				return ['ok'=>false,'error'=>'apps_corrupt'];
			}
			return ['ok'=>true,'apps'=>$apps,'source'=>'option'];
		},
		[
			'capability' => 'manage_options',
			'scope'      => 'core',
			'unsafe'     => false,
			'tags'       => ['apps'],
			'notes'      => 'Devuelve la configuración de Apps guardada en el servidor.',
		]
	);
};
