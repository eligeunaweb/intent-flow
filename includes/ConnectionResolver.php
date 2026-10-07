<?php
if (!defined('ABSPATH')) { exit; }

final class IF_ConnectionResolver {

    public static function resolve_payload(array $payload) : array {

        if (empty($payload['connection'])) {
            return $payload;
        }

        if (!class_exists('IF_Connections')) {
            return $payload;
        }

        $conn_id = (string)$payload['connection'];
        $conn = IF_Connections::get($conn_id);

        if (!$conn) {
            return $payload;
        }

        if (!empty($conn['config']) && is_array($conn['config'])) {
            $payload = array_merge($conn['config'], $payload);
        }

        $payload['_connection'] = $conn_id;

        return $payload;
    }
}
