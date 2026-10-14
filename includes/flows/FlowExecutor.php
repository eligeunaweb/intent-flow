<?php
if (!defined('ABSPATH')) { exit; }

class INTEFLOW_FlowExecutor {

    public static function run($flow_id, $event_payload = [], $meta = [], $options = []) {

        if (!class_exists('INTEFLOW_ActionExecutor')) {
            return ['ok'=>false,'error'=>'action_executor_missing'];
        }

        $flow = INTEFLOW_FlowRegistry::get($flow_id);
        if (!$flow) return ['ok'=>false,'error'=>'flow_not_found'];
        if (empty($flow['enabled'])) return ['ok'=>false,'error'=>'flow_disabled'];

        $actions = (isset($flow['actions']) && is_array($flow['actions'])) ? $flow['actions'] : [];
        $results = [];

        foreach ($actions as $idx => $action) {
            if (!is_array($action)) continue;
            $action_id = isset($action['action_id']) ? (string)$action['action_id'] : '';
            if ($action_id === '') continue;

            $input = (isset($action['input']) && is_array($action['input'])) ? $action['input'] : [];
            if (class_exists('INTEFLOW_Events') && method_exists('INTEFLOW_Events','resolve_templates')) {
                $input = INTEFLOW_Events::resolve_templates($input, $event_payload, $meta);
            }

            $res = INTEFLOW_ActionExecutor::execute($action_id, $input, array_merge($meta, [
                'source' => isset($meta['source']) ? $meta['source'] : 'flow',
                'flow_id' => $flow_id,
                'flow_action_index' => $idx,
            ]), $options);

            $results[] = $res;

            if (empty($res['ok'])) {
                return ['ok'=>false,'error'=>'action_failed','flow_id'=>$flow_id,'results'=>$results];
            }
        }

        return ['ok'=>true,'flow_id'=>$flow_id,'results'=>$results];
    }
}
