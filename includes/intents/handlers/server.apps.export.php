<?php
if (!defined('ABSPATH')) { exit; }

return function(INTEFLOW_IntentRegistry $reg) : void {

	$reg->register(
		'server.apps.export',
		function(array $payload) : array {
			$raw = get_option('inteflow_apps', []);
			$apps = is_array($raw) ? $raw : json_decode((string)$raw, true);
			if (!is_array($apps)) $apps = [];
			$export = [
				'schema' => 'inteflow_apps_v1',
				'plugin_version' => defined('INTEFLOW_VERSION') ? INTEFLOW_VERSION : null,
				'exported_at' => gmdate('c'),
				'checksum' => 'sha256:' . hash('sha256', wp_json_encode($apps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
				'apps' => $apps,
			];
			$json = wp_json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			return ['ok'=>true,'json'=>$json,'count'=>count($apps),'schema'=>'inteflow_apps_v1'];
		},
		[
			'capability' => 'manage_options',
			'scope'      => 'core',
			'unsafe'     => false,
			'tags'       => ['apps'],
			'notes'      => 'Exporta Apps como JSON.',
		]
	);
};
