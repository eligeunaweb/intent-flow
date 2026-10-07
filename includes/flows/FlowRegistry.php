<?php
if (!defined('ABSPATH')) { exit; }

class IF_FlowRegistry {
    const OPTION_KEY = 'if_flows_v1';

    public static function all() {
        $flows = get_option(self::OPTION_KEY, []);
        return is_array($flows) ? $flows : [];
    }

    public static function get($id) {
        $all = self::all();
        return isset($all[$id]) && is_array($all[$id]) ? $all[$id] : null;
    }

    public static function save($flow) {
        $id = isset($flow['id']) ? trim((string)$flow['id']) : '';
        if ($id === '') return ['ok'=>false,'error'=>'missing_id'];

        $all = self::all();
        $flow['id'] = $id;
        if (!isset($flow['enabled'])) $flow['enabled'] = true;

        $all[$id] = $flow;
        update_option(self::OPTION_KEY, $all, false);
        return ['ok'=>true,'flow'=>$flow];
    }

    public static function delete($id) {
        $id = trim((string)$id);
        if ($id === '') return ['ok'=>false,'error'=>'missing_id'];

        $all = self::all();
        unset($all[$id]);
        update_option(self::OPTION_KEY, $all, false);
        return ['ok'=>true];
    }
}
