<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {

    $reg->register(
        'server.scheduler.run_now',
        function(array $payload, array $meta, array $ctx) : array {

            $intent = (string)($payload['intent'] ?? $payload['intentId'] ?? $payload['target'] ?? '');

            // Support nested { item: { intentId, payload, meta } }
            if (!$intent && !empty($payload['item']) && is_array($payload['item'])) {
                $intent = (string)($payload['item']['intent'] ?? $payload['item']['intentId'] ?? '');
                if (!isset($payload['payload']) && isset($payload['item']['payload'])) { $payload['payload'] = $payload['item']['payload']; }
                if (!isset($payload['meta']) && isset($payload['item']['meta'])) { $payload['meta'] = $payload['item']['meta']; }
            }

            if ($intent === '') {
                return ['ok'=>false,'intentId'=>'server.scheduler.run_now','error'=>'missing_intent'];
            }

            $index = IF_PLUGIN_DIR . 'includes/intents/index_0930.php';
            if (!file_exists($index)) {
                return ['ok'=>false,'intentId'=>'server.scheduler.run_now','error'=>'registry_missing'];
            }

            require_once $index;
            if (!function_exists('if_build_intent_registry')) {
                return ['ok'=>false,'intentId'=>'server.scheduler.run_now','error'=>'registry_missing'];
            }

            $reg2 = if_build_intent_registry();

            $settings  = class_exists('IF_Settings') ? IF_Settings::get() : [];
            $safe_mode = !empty($settings['safe_mode']);
            $dry_run   = !empty($settings['dry_run']);

            $dispatch_ctx = [
                'dry_run'   => $dry_run,
                'safe_mode' => $safe_mode,
                'user_id'   => get_current_user_id(),
            ];

            $target_payload = (array)($payload['payload'] ?? $payload['params'] ?? []);
            $target_meta    = (array)($payload['meta'] ?? ['source'=>'scheduler.run_now']);

            return $reg2->dispatch($intent, $target_payload, $target_meta, $dispatch_ctx);
        },
        [
            'capability' => 'manage_options',
            'scope'      => 'core',
            'unsafe'     => false, // run_now is a control intent; the target intent enforces unsafe/live rules
            'notes'      => 'Execute an intent immediately (admin control).',
            'tags'       => ['scheduler'],
        ]
    );
};
