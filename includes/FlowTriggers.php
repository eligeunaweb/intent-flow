<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Flow triggers (v1):
 * - Run enabled flows whose `trigger` matches emitted event name.
 * - Executed from IF_Events::emit() after rule dispatching.
 */
final class IF_FlowTriggers {

    public static function on_event(string $eventName, array $payload, array $meta) : void {

        // Avoid recursion if a flow action emits the same event
        if (!empty($meta['__mmi_flow_trigger'])) return;

        if (!class_exists('IF_FlowRegistry') || !class_exists('IF_FlowExecutor')) return;

        $flows = IF_FlowRegistry::all();
        if (!is_array($flows) || empty($flows)) return;

        foreach ($flows as $flow_id => $flow) {
            if (!is_array($flow)) continue;
            if (empty($flow['enabled'])) continue;

            $trigger = isset($flow['trigger']) ? (string)$flow['trigger'] : '';
            if ($trigger === '' || $trigger !== $eventName) continue;

            $flow_meta = array_merge($meta, [
                'source' => isset($meta['source']) ? (string)$meta['source'] : 'events.flow',
                'event'  => $eventName,
                'flow_id' => (string)$flow_id,
                '__mmi_flow_trigger' => true,
            ]);

            // Execution logging is handled by Runner when actions execute.
            IF_FlowExecutor::run((string)$flow_id, $payload, $flow_meta, [
                'source' => 'events.flow',
                'ensure_admin' => true,
                'audit' => true,
            ]);
        }
    }
}
