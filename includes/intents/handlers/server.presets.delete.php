<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/../../Presets.php';

return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'server.presets.delete',
		function(array $payload) : array {
			$id = isset($payload['id']) ? sanitize_key((string)$payload['id']) : '';
			if ($id === '') return ['ok'=>false,'error'=>'missing_id'];
			$deleted = IF_Presets::delete($id);
			return ['ok'=>true,'deleted'=>$deleted];
		},
		[
			'capability' => 'manage_options',
			'scope' => 'automation',
			'unsafe' => false,
		]
	);

};
