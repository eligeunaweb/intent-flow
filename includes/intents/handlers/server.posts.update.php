<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {

  $reg->register(
    'server.posts.update',
    function(array $payload, array $meta, array $ctx) : array {

      $post_id = isset($payload['post_id']) ? (int)$payload['post_id'] : 0;
      if ($post_id <= 0) {
        return ['ok'=>false,'error'=>'missing_post_id'];
      }

      if (!current_user_can('edit_post', $post_id)) {
        return ['ok'=>false,'blocked'=>true,'reason'=>'capability_denied'];
      }

      $postarr = ['ID' => $post_id];

      if (array_key_exists('title', $payload)) {
        $postarr['post_title'] = wp_strip_all_tags((string)$payload['title']);
      }
      if (array_key_exists('content', $payload)) {
        $postarr['post_content'] = (string)$payload['content'];
      }
      if (array_key_exists('status', $payload)) {
        $status = sanitize_key((string)$payload['status']);
        $allowed_status = ['draft','publish','pending','private'];
        if (!in_array($status, $allowed_status, true)) {
          return ['ok'=>false,'error'=>'invalid_status'];
        }
        $postarr['post_status'] = $status;
      }

      $updated = wp_update_post($postarr, true);
      if (is_wp_error($updated)) {
        return ['ok'=>false,'error'=>'wp_update_post_failed','message'=>$updated->get_error_message()];
      }

      return [
        'updated' => true,
        'post_id' => (int)$post_id,
        'edit'    => get_edit_post_link($post_id, 'raw'),
        'view'    => get_permalink($post_id),
      ];
    },
    [
      'capability'    => 'edit_posts',
      'unsafe'        => false,
      'requires_live' => true,
      'tags'          => ['posts'],
    ]
  );

};
