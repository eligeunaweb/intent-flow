<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/../../Flows.php';

return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'flow.get',
		function(array $payload) : array {
			$id = isset($payload['id']) ? sanitize_key((string)$payload['id']) : '';
			if ($id === '') return ['ok'=>false,'error'=>'missing_id'];
			$f = IF_Flows::get($id);
			if (!$f) return ['ok'=>false,'error'=>'not_found'];
			return ['ok'=>true,'flow'=>$f];
		},
		[
			'capability' => 'manage_options',
			'scope' => 'automation',
			'unsafe' => false,
		]
	);

};
