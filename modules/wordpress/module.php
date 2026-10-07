<?php
if (!defined('ABSPATH')) { exit; }

define('IF_MODULE_WORDPRESS_VERSION', '1.1.0');

add_action('if_register_intents', function($reg) {
    if (!is_object($reg) || !method_exists($reg, 'register')) return;

    // Ping
    $reg->register('module.wordpress.ping', function($payload, $meta, $ctx) {
        return ['ok' => true, 'module' => 'wordpress', 'version' => '1.1.0'];
    }, ['capability' => 'manage_options']);

    // wp.email.send
    $reg->register('wp.email.send', function($payload, $meta, $ctx) {
        $to      = sanitize_email((string)($payload['to'] ?? ''));
        $subject = sanitize_text_field((string)($payload['subject'] ?? ''));
        $body    = wp_kses_post((string)($payload['body'] ?? ''));
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        if ($to === '' || $subject === '') {
            return ['ok' => false, 'error' => 'missing_to_or_subject'];
        }
        if (!empty($ctx['dry_run'])) {
            return ['ok' => true, 'dry_run' => true, 'to' => $to];
        }
        $sent = wp_mail($to, $subject, $body, $headers);
        return ['ok' => (bool)$sent, 'to' => $to];
    }, ['capability' => 'manage_options']);

    // wp.user.create
    $reg->register('wp.user.create', function($payload, $meta, $ctx) {
        $email    = sanitize_email((string)($payload['email'] ?? ''));
        $login    = sanitize_user((string)($payload['login'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        $role     = sanitize_key((string)($payload['role'] ?? 'subscriber'));
        if ($email === '') return ['ok' => false, 'error' => 'missing_email'];
        // Restrict roles — administrator role cannot be assigned via automation for security reasons
        $blocked_roles = ['administrator', 'super_admin'];
        if (in_array($role, $blocked_roles, true)) return ['ok' => false, 'error' => 'role_not_allowed', 'message' => 'The administrator role cannot be assigned via automation.'];
        if ($login === '') $login = sanitize_user(explode('@', $email)[0]);
        if ($password === '') $password = wp_generate_password(16);
        if (!empty($ctx['dry_run'])) return ['ok' => true, 'dry_run' => true, 'email' => $email];
        if (email_exists($email)) return ['ok' => false, 'error' => 'email_exists'];
        $user_id = wp_insert_user([
            'user_login' => $login,
            'user_email' => $email,
            'user_pass'  => $password,
            'role'       => $role,
        ]);
        if (is_wp_error($user_id)) return ['ok' => false, 'error' => $user_id->get_error_message()];
        return ['ok' => true, 'user_id' => $user_id, 'email' => $email];
    }, ['capability' => 'manage_options']);

    // wp.user.add_role
    $reg->register('wp.user.add_role', function($payload, $meta, $ctx) {
        $user_id = (int)($payload['user_id'] ?? 0);
        $role    = sanitize_key((string)($payload['role'] ?? ''));
        if ($user_id === 0 || $role === '') return ['ok' => false, 'error' => 'missing_user_id_or_role'];
        if (!empty($ctx['dry_run'])) return ['ok' => true, 'dry_run' => true];
        $user = get_user_by('id', $user_id);
        if (!$user) return ['ok' => false, 'error' => 'user_not_found'];
        $user->add_role($role);
        return ['ok' => true, 'user_id' => $user_id, 'role' => $role];
    }, ['capability' => 'manage_options']);

    // wp.user.update_meta
    $reg->register('wp.user.update_meta', function($payload, $meta, $ctx) {
        $user_id    = (int)($payload['user_id'] ?? 0);
        $meta_key   = sanitize_key((string)($payload['meta_key'] ?? '')); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        $meta_value = $payload['meta_value'] ?? '';
        if ($user_id === 0 || $meta_key === '') return ['ok' => false, 'error' => 'missing_user_id_or_meta_key'];
        if (!empty($ctx['dry_run'])) return ['ok' => true, 'dry_run' => true];
        $result = update_user_meta($user_id, $meta_key, $meta_value);
        return ['ok' => true, 'user_id' => $user_id, 'meta_key' => $meta_key, 'updated' => $result]; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
    }, ['capability' => 'manage_options']);

    // wp.post.create
    $reg->register('wp.post.create', function($payload, $meta, $ctx) {
        $title     = sanitize_text_field((string)($payload['title'] ?? ''));
        $content   = wp_kses_post((string)($payload['content'] ?? ''));
        $status    = sanitize_key((string)($payload['status'] ?? 'draft'));
        $post_type = sanitize_key((string)($payload['post_type'] ?? 'post'));
        $author_id = (int)($payload['author_id'] ?? get_current_user_id());
        if ($title === '') return ['ok' => false, 'error' => 'missing_title'];
        if (!in_array($status, ['draft','publish','pending','private'], true)) $status = 'draft';
        if (!empty($ctx['dry_run'])) return ['ok' => true, 'dry_run' => true, 'title' => $title];
        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => $status,
            'post_type'    => $post_type,
            'post_author'  => $author_id,
        ], true);
        if (is_wp_error($post_id)) return ['ok' => false, 'error' => $post_id->get_error_message()];
        return ['ok' => true, 'post_id' => $post_id, 'title' => $title, 'status' => $status];
    }, ['capability' => 'manage_options']);

    // wp.option.set
    $reg->register('wp.option.set', function($payload, $meta, $ctx) {
        $option_name  = sanitize_key((string)($payload['option_name'] ?? ''));
        $option_value = $payload['option_value'] ?? '';
        if ($option_name === '') return ['ok' => false, 'error' => 'missing_option_name'];
        // Security: only allow options with the inteflow_ prefix to prevent arbitrary option modification
        if (strpos($option_name, 'inteflow_') !== 0) return ['ok' => false, 'error' => 'option_name_must_use_inteflow_prefix', 'message' => 'For security, only options prefixed with inteflow_ can be set via automation.'];
        // Bloquear opciones sensibles
        $blocked = ['siteurl','home','admin_email','blogname','users_can_register','default_role'];
        if (in_array($option_name, $blocked, true)) return ['ok' => false, 'error' => 'option_blocked'];
        if (!empty($ctx['dry_run'])) return ['ok' => true, 'dry_run' => true, 'option_name' => $option_name];
        update_option($option_name, $option_value);
        return ['ok' => true, 'option_name' => $option_name];
    }, ['capability' => 'manage_options']);

}, 50);
