<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/../../Presets.php';

return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'server.presets.get',
		function(array $payload) : array {
			$id = isset($payload['id']) ? sanitize_key((string)$payload['id']) : '';
			if ($id === '') return ['ok'=>false,'error'=>'missing_id'];
			$p = IF_Presets::get($id);
			if (!$p) return ['ok'=>false,'error'=>'not_found'];
			return ['ok'=>true,'preset'=>$p];
		},
		[
			'capability' => 'manage_options',
			'scope' => 'automation',
			'unsafe' => false,
		]
	);

};
