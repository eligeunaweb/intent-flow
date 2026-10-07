<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/../../Presets.php';

return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'server.presets.list',
		function(array $payload) : array {
			$all = IF_Presets::all();
			$items = [];
			foreach ($all as $id => $p) {
				if (!is_array($p)) continue;
				$items[] = [
					'id' => (string)($p['id'] ?? $id),
					'name' => (string)($p['name'] ?? ''),
					'intentId' => (string)($p['intentId'] ?? ''),
					'updated_at' => (int)($p['updated_at'] ?? 0),
				];
			}
			usort($items, function($a,$b){ return ($b['updated_at'] ?? 0) <=> ($a['updated_at'] ?? 0); });
			return ['ok'=>true,'presets'=>$items];
		},
		[
			'capability' => 'manage_options',
			'scope' => 'automation',
			'unsafe' => false,
		]
	);

};
