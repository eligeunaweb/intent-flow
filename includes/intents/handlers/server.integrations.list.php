<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) {
	$reg->register('server.integrations.list', function(array $payload, array $meta, array $ctx) : array {
		$type = isset($payload['type']) ? sanitize_text_field((string)$payload['type']) : '';
		$items = IF_Integrations::list_items($type);

		// Basic redaction: keep URLs, but avoid showing query tokens.
		foreach ($items as $i => $it) {
			if (!is_array($it)) continue;
			$config = is_array($it['config'] ?? null) ? $it['config'] : [];
			foreach (['webhook_url','hook_url','base_url'] as $k) {
				if (!empty($config[$k]) && is_string($config[$k])) {
					$u = $config[$k];
					$parts = wp_parse_url($u);
					if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
						$clean = $parts['scheme'].'://'.$parts['host'];
						if (!empty($parts['path'])) $clean .= $parts['path'];
						$config[$k] = $clean;
					}
				}
			}
			$items[$i]['config'] = $config;
		}

		return [ 'ok' => true, 'items' => array_values($items) ];
	}, [ 'capability' => 'manage_options' ]);
};
