<?php
if (!defined('ABSPATH')) { exit; }

return [
    'intent' => 'server.bundle.import',
    'run' => function(array $payload, array $meta, array $ctx) : array {

        $bundle = $payload['bundle'] ?? null;
        if (!is_array($bundle)) {
            return ['ok'=>false,'error'=>'invalid_bundle'];
        }

        if (($bundle['schema'] ?? '') !== 'if_bundle_v1') {
            return ['ok'=>false,'error'=>'invalid_schema'];
        }

        // Verify checksum if present
        if (!empty($bundle['checksum'])) {
            $checksum = $bundle['checksum'];
            $copy = $bundle;
            unset($copy['checksum']);
            $json = wp_json_encode($copy, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            $calc = 'sha256:' . hash('sha256', $json);
            if ($calc !== $checksum) {
                return ['ok'=>false,'error'=>'checksum_mismatch'];
            }
        }

        // Merge mode default
        $mode = $payload['mode'] ?? 'merge';

        if (!empty($bundle['apps'])) {
            if ($mode === 'replace') {
                update_option('if_apps', $bundle['apps'], false);
            } else {
                $current = get_option('if_apps', []);
                if (!is_array($current)) { $current = []; }
                foreach ($bundle['apps'] as $app) {
                    if (!empty($app['id'])) {
                        $current[$app['id']] = $app;
                    }
                }
                update_option('if_apps', $current, false);
            }
        }

        if (!empty($bundle['flows']) && class_exists('IF_Flows')) {
            update_option(IF_Flows::OPTION_KEY, $bundle['flows'], false);
        }

        if (!empty($bundle['presets']) && class_exists('IF_Presets')) {
            update_option(IF_Presets::OPTION_KEY, $bundle['presets'], false);
        }

        if (!empty($bundle['settings'])) {
            update_option('inteflow_settings', $bundle['settings'], false);
        }

        return ['ok'=>true];
    },
    'capability' => 'manage_options',
    'scope' => 'core',
    'unsafe' => true,
];
