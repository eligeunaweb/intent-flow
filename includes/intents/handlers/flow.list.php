<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/../../Flows.php';

return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'flow.list',
		function(array $payload) : array {
			$all = IF_Flows::all();
			$items = [];
			foreach ($all as $id => $f) {
				if (!is_array($f)) continue;
				$items[] = [
					'id' => (string)($f['id'] ?? $id),
					'name' => (string)($f['name'] ?? ''),
					'steps' => is_array($f['steps'] ?? null) ? count($f['steps']) : 0,
					'updated_at' => (int)($f['updated_at'] ?? 0),
				];
			}
			usort($items, function($a,$b){ return ($b['updated_at'] ?? 0) <=> ($a['updated_at'] ?? 0); });
			return ['ok'=>true,'flows'=>$items];
		},
		[
			'capability' => 'manage_options',
			'scope' => 'automation',
			'unsafe' => false,
		]
	);

};
