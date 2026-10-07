<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {

    $reg->register(
        'macro.post.ensure',

        function(array $payload, array $meta, array $ctx) : array {

            $title   = isset($payload['title']) ? sanitize_text_field($payload['title']) : '';
            $content = isset($payload['content']) ? wp_kses_post($payload['content']) : '';
            $status  = isset($payload['status']) ? sanitize_key($payload['status']) : 'draft';

            if ($title === '') {
                return ['ok'=>false,'error'=>'missing_title'];
            }

            global $wpdb;

            $post_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type='post' LIMIT 1",
                    $title
                )
            );

            if ($post_id) {

                wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $content,
                    'post_status' => $status,
                ]);

                return [
                    'action' => 'updated',
                    'post_id' => (int)$post_id,
                    'edit' => get_edit_post_link($post_id, ''),
                    'view' => get_permalink($post_id),
                ];

            } else {

                $post_id = wp_insert_post([
                    'post_title' => $title,
                    'post_content' => $content,
                    'post_status' => $status,
                    'post_type' => 'post',
                ]);

                return [
                    'action' => 'created',
                    'post_id' => (int)$post_id,
                    'edit' => get_edit_post_link($post_id, ''),
                    'view' => get_permalink($post_id),
                ];
            }
        },

        [
            'capability' => 'edit_posts',
            'scope'      => 'posts',
            'unsafe'     => true,
            'requires_live' => true,
        ]
    );

};
