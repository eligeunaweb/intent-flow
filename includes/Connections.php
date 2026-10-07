<?php
if (!defined('ABSPATH')) { exit; }

final class IF_Connections {

    const OPTION_KEY = 'if_connections_v1';

    public static function all() : array {
        $data = get_option(self::OPTION_KEY, []);
        return is_array($data) ? $data : [];
    }

    public static function get(string $id) : ?array {
        $all = self::all();
        return $all[$id] ?? null;
    }

    public static function save(array $conn) : array {
        $id = isset($conn['id']) ? trim((string)$conn['id']) : '';
        if ($id === '') return ['ok'=>false,'error'=>'missing_id'];
        $all = self::all();
        $conn['id'] = $id;
        $conn['updated_at'] = time();
        $all[$id] = $conn;
        update_option(self::OPTION_KEY, $all, false);
        return ['ok'=>true,'connection'=>$conn];
    }

    public static function delete(string $id) : array {
        $all = self::all();
        unset($all[$id]);
        update_option(self::OPTION_KEY, $all, false);
        return ['ok'=>true];
    }
}


// Ensure core connections intents are registered even if handler loader misses them.
add_action('if_register_intents', function($reg){
    if (!is_object($reg) || !method_exists($reg, 'register')) return;

    $reg->register('server.connections.list', function(array $payload, array $meta, array $ctx): array {
        return ['ok'=>true,'items'=>IF_Connections::all()];
    }, ['capability'=>'manage_options']);

    $reg->register('server.connections.save', function(array $payload, array $meta, array $ctx): array {
        return IF_Connections::save($payload);
    }, ['capability'=>'manage_options']);

    $reg->register('server.connections.delete', function(array $payload, array $meta, array $ctx): array {
        $id = isset($payload['id']) ? (string)$payload['id'] : '';
        return IF_Connections::delete($id);
    }, ['capability'=>'manage_options']);

    $reg->register('server.connections.test', function(array $payload, array $meta, array $ctx): array {
        $id = isset($payload['id']) ? (string)$payload['id'] : '';
        if ($id === '') return ['ok'=>false,'error'=>'missing_id'];
        $c = IF_Connections::get($id);
        if (!$c) return ['ok'=>false,'error'=>'not_found'];

        $type = isset($c['type']) ? (string)$c['type'] : '';
        $cfg  = isset($c['config']) && is_array($c['config']) ? $c['config'] : $c;

        // Telegram: validate bot token by calling getMe
        if ($type === 'telegram') {
            $token = isset($cfg['bot_token']) ? (string)$cfg['bot_token'] : '';
            if ($token === '') return ['ok'=>false,'error'=>'missing_bot_token'];
            $url = 'https://api.telegram.org/bot' . rawurlencode($token) . '/getMe';
            $res = wp_remote_get($url, ['timeout'=>12]);
            if (is_wp_error($res)) return ['ok'=>false,'error'=>'http_error','message'=>$res->get_error_message()];
            $code = wp_remote_retrieve_response_code($res);
            $body = wp_remote_retrieve_body($res);
            $json = json_decode($body, true);
            if ($code !== 200 || !is_array($json) || empty($json['ok'])) {
                return ['ok'=>false,'error'=>'telegram_invalid','status'=>$code,'message'=>'Telegram respondió con error','snippet'=>substr((string)$body,0,240)];
            }
            $u = $json['result']['username'] ?? '';
            return ['ok'=>true,'message'=> $u ? ('Bot @' . $u) : 'Telegram OK'];
        }

        // Slack webhook: basic URL sanity
        if ($type === 'slack_webhook') {
            $url = isset($cfg['webhook_url']) ? (string)$cfg['webhook_url'] : '';
            if ($url === '') return ['ok'=>false,'error'=>'missing_webhook_url'];
            if (strpos($url, 'https://hooks.slack.com/') !== 0) {
                return ['ok'=>false,'error'=>'invalid_webhook_url'];
            }
            return ['ok'=>true,'message'=>'URL parece válida'];
        }

        if ($type === 'whatsapp') {
            $token    = isset($cfg['access_token']) ? sanitize_text_field((string)$cfg['access_token']) : '';
            $phone_id = isset($cfg['phone_number_id']) ? sanitize_text_field((string)$cfg['phone_number_id']) : '';
            if ($token === '') return ['ok'=>false,'error'=>'missing_access_token'];
            if ($phone_id === '') return ['ok'=>false,'error'=>'missing_phone_number_id'];
            $response = wp_remote_get('https://graph.facebook.com/v19.0/' . $phone_id . '?fields=display_phone_number', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'timeout' => 10,
            ]);
            if (is_wp_error($response)) return ['ok'=>false,'error'=>$response->get_error_message()];
            $code = wp_remote_retrieve_response_code($response);
            if ($code === 401) return ['ok'=>false,'error'=>'invalid_access_token'];
            if ($code !== 200) return ['ok'=>false,'error'=>'api_error','code'=>$code];
            $data = json_decode(wp_remote_retrieve_body($response), true);
            return ['ok'=>true,'message'=>'WhatsApp connected: ' . ($data['display_phone_number'] ?? 'OK')];
        }
        if ($type === 'openai') {
            $api_key = isset($cfg['api_key']) ? sanitize_text_field((string)$cfg['api_key']) : '';
            if ($api_key === '') return ['ok'=>false,'error'=>'missing_api_key'];
            $response = wp_remote_get('https://api.openai.com/v1/models', [
                'headers' => ['Authorization' => 'Bearer ' . $api_key],
                'timeout' => 10,
            ]);
            if (is_wp_error($response)) return ['ok'=>false,'error'=>$response->get_error_message()];
            $code = wp_remote_retrieve_response_code($response);
            if ($code === 401) return ['ok'=>false,'error'=>'invalid_api_key'];
            if ($code !== 200) return ['ok'=>false,'error'=>'api_error','code'=>$code];
            return ['ok'=>true,'message'=>'OpenAI API key is valid'];
        }

        // Default: supported but no active test
        return ['ok'=>true,'message'=>'Sin test específico para este tipo'];
    }, ['capability'=>'manage_options']);

}, 5);



