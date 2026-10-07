<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/../../Flows.php';

return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'flow.delete',
		function(array $payload) : array {
			$id = isset($payload['id']) ? sanitize_key((string)$payload['id']) : '';
			if ($id === '') return ['ok'=>false,'error'=>'missing_id'];
			$deleted = IF_Flows::delete($id);
			return ['ok'=>true,'deleted'=>$deleted];
		},
		[
			'capability' => 'manage_options',
			'scope' => 'automation',
			'unsafe' => false,
		]
	);

};
