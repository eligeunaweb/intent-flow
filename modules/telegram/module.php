<?php
/**
 * Intent Flow - Telegram Module (internal)
 */
if (!defined('ABSPATH')) exit;

// Register connection type
add_filter('if_connection_types', function($types){
    $types['telegram'] = [
        'label' => 'Telegram Bot',
        'fields' => [
            'bot_token' => ['label'=>'Bot Token','type'=>'text','required'=>true],
            'default_chat_id' => ['label'=>'Default Chat ID','type'=>'text','required'=>false],
        ]
    ];
    return $types;
});

// Register intent
add_action('if_register_intents', function($reg){
    $reg->register('telegram.sendMessage', function($payload){
        $token = $payload['bot_token'] ?? '';
        $chat = $payload['chat_id'] ?? '';
        $text = $payload['text'] ?? '';
        if (!$token || !$chat || !$text) {
            return ['ok'=>false,'error'=>'Missing bot_token/chat_id/text'];
        }
        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $res = wp_remote_post($url, [
            'body'=>['chat_id'=>$chat,'text'=>$text],
            'timeout'=>15,
        ]);
        if (is_wp_error($res)) {
            return ['ok'=>false,'error'=>$res->get_error_message()];
        }
        return ['ok'=>true,'response'=>json_decode(wp_remote_retrieve_body($res), true)];
    });
});
