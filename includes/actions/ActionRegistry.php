<?php
if (!defined('ABSPATH')) { exit; }

class INTEFLOW_ActionRegistry {

    private static $actions = null;

    public static function get_all() : array {
        if (self::$actions !== null) return self::$actions;

        $base = [
            'slack.send_message' => [
                'id' => 'slack.send_message',
                'label' => 'Send Slack Message',
                'integration' => 'slack',
                'intent' => 'slack.send',
                'description' => 'Send a message to a Slack incoming webhook connection.',
                'fields' => [
                    'connection_id' => ['type'=>'string','label'=>'Connection','required'=>true],
                    'text'          => ['type'=>'string','label'=>'Message','required'=>true],
                ],
            ],
            'zapier.trigger' => [
                'id' => 'zapier.trigger',
                'label' => 'Trigger Zapier Catch Hook',
                'integration' => 'zapier',
                'intent' => 'zapier.trigger',
                'description' => 'Send JSON data to a Zapier Catch Hook connection.',
                'fields' => [
                    'connection_id' => ['type'=>'string','label'=>'Connection','required'=>true],
                    'data'          => ['type'=>'object','label'=>'Data (JSON)','required'=>false],
                ],
            ],
            'http.request' => [
                'id' => 'http.request',
                'label' => 'HTTP Request',
                'integration' => 'http',
                'intent' => 'http.request',
                'description' => 'Perform an HTTP request (direct URL or via saved HTTP Endpoint connection).',
                'fields' => [
                    'connection_id' => ['type'=>'string','label'=>'Connection (optional)','required'=>false],
                    'url'           => ['type'=>'string','label'=>'URL','required'=>false],
                    'method'        => ['type'=>'string','label'=>'Method','required'=>false],
                    'headers'       => ['type'=>'object','label'=>'Headers (JSON)','required'=>false],
                    'body'          => ['type'=>'object','label'=>'Body (JSON)','required'=>false],
                ],
            ],
            'wp.email.send' => [
                'id' => 'wp.email.send',
                'label' => 'Send Email',
                'integration' => 'wordpress',
                'intent' => 'wp.email.send',
                'description' => 'Send an email via WordPress wp_mail().',
                'fields' => [
                    'to'      => ['type'=>'string','label'=>'To (email)','required'=>true],
                    'subject' => ['type'=>'string','label'=>'Subject','required'=>true],
                    'body'    => ['type'=>'string','label'=>'Body (HTML allowed)','required'=>false],
                ],
            ],
            'wp.user.create' => [
                'id' => 'wp.user.create',
                'label' => 'Create User',
                'integration' => 'wordpress',
                'intent' => 'wp.user.create',
                'description' => 'Create a new WordPress user.',
                'fields' => [
                    'email'    => ['type'=>'string','label'=>'Email','required'=>true],
                    'login'    => ['type'=>'string','label'=>'Username (optional)','required'=>false],
                    'password' => ['type'=>'string','label'=>'Password (auto-generated if empty)','required'=>false],
                    'role'     => ['type'=>'string','label'=>'Role (default: subscriber)','required'=>false],
                ],
            ],
            'wp.user.add_role' => [
                'id' => 'wp.user.add_role',
                'label' => 'Add Role to User',
                'integration' => 'wordpress',
                'intent' => 'wp.user.add_role',
                'description' => 'Add a role to an existing WordPress user.',
                'fields' => [
                    'user_id' => ['type'=>'integer','label'=>'User ID','required'=>true],
                    'role'    => ['type'=>'string','label'=>'Role','required'=>true],
                ],
            ],
            'wp.user.update_meta' => [
                'id' => 'wp.user.update_meta',
                'label' => 'Update User Meta',
                'integration' => 'wordpress',
                'intent' => 'wp.user.update_meta',
                'description' => 'Update a user meta field.',
                'fields' => [
                    'user_id'    => ['type'=>'integer','label'=>'User ID','required'=>true],
                    'meta_key'   => ['type'=>'string','label'=>'Meta key','required'=>true], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                    'meta_value' => ['type'=>'string','label'=>'Meta value','required'=>false], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                ],
            ],
            'wp.post.create' => [
                'id' => 'wp.post.create',
                'label' => 'Create Post',
                'integration' => 'wordpress',
                'intent' => 'wp.post.create',
                'description' => 'Create a new WordPress post or page.',
                'fields' => [
                    'title'     => ['type'=>'string','label'=>'Title','required'=>true],
                    'content'   => ['type'=>'string','label'=>'Content (HTML)','required'=>false],
                    'status'    => ['type'=>'string','label'=>'Status (draft/publish/pending/private)','required'=>false],
                    'post_type' => ['type'=>'string','label'=>'Post type (default: post)','required'=>false],
                    'author_id' => ['type'=>'integer','label'=>'Author ID (optional)','required'=>false],
                ],
            ],
            'wp.option.set' => [
                'id' => 'wp.option.set',
                'label' => 'Set Option',
                'integration' => 'wordpress',
                'intent' => 'wp.option.set',
                'description' => 'Update a WordPress option. Sensitive options are blocked.',
                'fields' => [
                    'option_name'  => ['type'=>'string','label'=>'Option name','required'=>true],
                    'option_value' => ['type'=>'string','label'=>'Option value','required'=>false],
                ],
            ],

            // WooCommerce Pro
            'wc.order.update_status' => [
                'id'          => 'wc.order.update_status',
                'label'       => 'WC Update Order Status',
                'integration' => 'woocommerce',
                'intent'      => 'wc.order.update_status',
                'description' => 'Change the status of a WooCommerce order.',
                'pro'         => true,
                'fields' => [
                    'order_id' => ['type'=>'integer','label'=>'Order ID','required'=>true],
                    'status'   => ['type'=>'string','label'=>'New status (pending, processing, completed, cancelled…)','required'=>true],
                    'note'     => ['type'=>'string','label'=>'Order note (optional)','required'=>false],
                ],
            ],
            'wc.order.add_note' => [
                'id'          => 'wc.order.add_note',
                'label'       => 'WC Add Order Note',
                'integration' => 'woocommerce',
                'intent'      => 'wc.order.add_note',
                'description' => 'Add a note to a WooCommerce order.',
                'pro'         => true,
                'fields' => [
                    'order_id'      => ['type'=>'integer','label'=>'Order ID','required'=>true],
                    'note'          => ['type'=>'string','label'=>'Note text','required'=>true],
                    'customer_note' => ['type'=>'boolean','label'=>'Visible to customer','required'=>false],
                ],
            ],
            'wc.coupon.create' => [
                'id'          => 'wc.coupon.create',
                'label'       => 'WC Create Coupon',
                'integration' => 'woocommerce',
                'intent'      => 'wc.coupon.create',
                'description' => 'Create a WooCommerce coupon.',
                'pro'         => true,
                'fields' => [
                    'code'        => ['type'=>'string','label'=>'Coupon code (auto-generated if empty)','required'=>false],
                    'amount'      => ['type'=>'number','label'=>'Discount amount','required'=>true],
                    'type'        => ['type'=>'string','label'=>'Type: percent, fixed_cart, fixed_product','required'=>false],
                    'expires'     => ['type'=>'string','label'=>'Expiry date (YYYY-MM-DD, optional)','required'=>false],
                    'usage_limit' => ['type'=>'integer','label'=>'Usage limit (0 = unlimited)','required'=>false],
                ],
            ],
            'wc.order.get' => [
                'id'          => 'wc.order.get',
                'label'       => 'WC Get Order',
                'integration' => 'woocommerce',
                'intent'      => 'wc.order.get',
                'description' => 'Get data from a WooCommerce order.',
                'pro'         => true,
                'fields' => [
                    'order_id' => ['type'=>'integer','label'=>'Order ID','required'=>true],
                ],
            ],
            'wc.customer.get' => [
                'id'          => 'wc.customer.get',
                'label'       => 'WC Get Customer',
                'integration' => 'woocommerce',
                'intent'      => 'wc.customer.get',
                'description' => 'Get data from a WooCommerce customer.',
                'pro'         => true,
                'fields' => [
                    'customer_id' => ['type'=>'integer','label'=>'Customer ID (or use email)','required'=>false],
                    'email'       => ['type'=>'string','label'=>'Customer email','required'=>false],
                ],
            ],

            // Google Sheets Pro
            'gsheets.append_row' => [
                'id'          => 'gsheets.append_row',
                'label'       => 'Google Sheets — Append Row',
                'integration' => 'google_sheets',
                'intent'      => 'gsheets.append_row',
                'description' => 'Add a new row at the end of a Google Sheet.',
                'pro'         => true,
                'fields' => [
                    'connection_id'   => ['type'=>'string', 'label'=>'Connection (google_sheets)', 'required'=>false],
                    'spreadsheet_id'  => ['type'=>'string', 'label'=>'Spreadsheet ID (from URL)', 'required'=>true],
                    'sheet_name'      => ['type'=>'string', 'label'=>'Sheet name (default: Sheet1)', 'required'=>false],
                    'values'          => ['type'=>'array',  'label'=>'Values (array of cell values)', 'required'=>true],
                ],
            ],
            'gsheets.update_cell' => [
                'id'          => 'gsheets.update_cell',
                'label'       => 'Google Sheets — Update Cell',
                'integration' => 'google_sheets',
                'intent'      => 'gsheets.update_cell',
                'description' => 'Update a specific cell or range in a Google Sheet.',
                'pro'         => true,
                'fields' => [
                    'connection_id'  => ['type'=>'string', 'label'=>'Connection (google_sheets)', 'required'=>false],
                    'spreadsheet_id' => ['type'=>'string', 'label'=>'Spreadsheet ID', 'required'=>true],
                    'range'          => ['type'=>'string', 'label'=>'Range (e.g. Sheet1!A1)', 'required'=>true],
                    'value'          => ['type'=>'string', 'label'=>'Value to write', 'required'=>true],
                ],
            ],
            'gsheets.get_range' => [
                'id'          => 'gsheets.get_range',
                'label'       => 'Google Sheets — Get Range',
                'integration' => 'google_sheets',
                'intent'      => 'gsheets.get_range',
                'description' => 'Read values from a range in a Google Sheet.',
                'pro'         => true,
                'fields' => [
                    'connection_id'  => ['type'=>'string', 'label'=>'Connection (google_sheets)', 'required'=>false],
                    'spreadsheet_id' => ['type'=>'string', 'label'=>'Spreadsheet ID', 'required'=>true],
                    'range'          => ['type'=>'string', 'label'=>'Range (e.g. Sheet1!A1:D10)', 'required'=>true],
                ],
            ],


            // Stripe Pro
            'stripe.customer.get' => [
                'id'          => 'stripe.customer.get',
                'label'       => 'Stripe — Get Customer',
                'integration' => 'stripe',
                'intent'      => 'stripe.customer.get',
                'description' => 'Get a Stripe customer by ID or email.',
                'pro'         => true,
                'fields'      => [
                    'connection_id' => ['type'=>'string','label'=>'Connection (stripe)','required'=>false],
                    'customer_id'   => ['type'=>'string','label'=>'Customer ID (or use email)','required'=>false],
                    'email'         => ['type'=>'string','label'=>'Customer email','required'=>false],
                ],
            ],
            'stripe.payment.create' => [
                'id'          => 'stripe.payment.create',
                'label'       => 'Stripe — Create PaymentIntent',
                'integration' => 'stripe',
                'intent'      => 'stripe.payment.create',
                'description' => 'Create a Stripe PaymentIntent.',
                'pro'         => true,
                'fields'      => [
                    'connection_id' => ['type'=>'string','label'=>'Connection (stripe)','required'=>false],
                    'amount'        => ['type'=>'number','label'=>'Amount (e.g. 49.99)','required'=>true],
                    'currency'      => ['type'=>'string','label'=>'Currency (default: eur)','required'=>false],
                    'customer_id'   => ['type'=>'string','label'=>'Stripe Customer ID (optional)','required'=>false],
                    'description'   => ['type'=>'string','label'=>'Description (optional)','required'=>false],
                ],
            ],
            'stripe.webhook.url' => [
                'id'          => 'stripe.webhook.url',
                'label'       => 'Stripe — Get Webhook URL',
                'integration' => 'stripe',
                'intent'      => 'stripe.webhook.url',
                'description' => 'Get the webhook URL to configure in your Stripe dashboard.',
                'pro'         => true,
                'fields'      => [],
            ],


            // WhatsApp Business Pro
            'whatsapp.message.send' => [
                'id'          => 'whatsapp.message.send',
                'label'       => 'WhatsApp — Send Message',
                'integration' => 'whatsapp',
                'intent'      => 'whatsapp.message.send',
                'description' => 'Send a WhatsApp text message via Meta Business API.',
                'pro'         => true,
                'fields'      => [
                    'connection_id' => ['type'=>'string','label'=>'Connection (whatsapp)','required'=>false],
                    'to'            => ['type'=>'string','label'=>'Recipient phone (e.g. 34612345678)','required'=>true],
                    'message'       => ['type'=>'string','label'=>'Message text','required'=>true],
                ],
            ],
            'whatsapp.template.send' => [
                'id'          => 'whatsapp.template.send',
                'label'       => 'WhatsApp — Send Template',
                'integration' => 'whatsapp',
                'intent'      => 'whatsapp.template.send',
                'description' => 'Send a WhatsApp approved template message.',
                'pro'         => true,
                'fields'      => [
                    'connection_id' => ['type'=>'string','label'=>'Connection (whatsapp)','required'=>false],
                    'to'            => ['type'=>'string','label'=>'Recipient phone','required'=>true],
                    'template_name' => ['type'=>'string','label'=>'Template name','required'=>true],
                    'language'      => ['type'=>'string','label'=>'Language code (default: es)','required'=>false],
                    'components'    => ['type'=>'array', 'label'=>'Template components (optional)','required'=>false],
                ],
            ],
            'whatsapp.media.send' => [
                'id'          => 'whatsapp.media.send',
                'label'       => 'WhatsApp — Send Media',
                'integration' => 'whatsapp',
                'intent'      => 'whatsapp.media.send',
                'description' => 'Send an image, document, video or audio via WhatsApp.',
                'pro'         => true,
                'fields'      => [
                    'connection_id' => ['type'=>'string','label'=>'Connection (whatsapp)','required'=>false],
                    'to'            => ['type'=>'string','label'=>'Recipient phone','required'=>true],
                    'type'          => ['type'=>'string','label'=>'Type: image, document, video, audio','required'=>false],
                    'url'           => ['type'=>'string','label'=>'Media URL','required'=>true],
                    'caption'       => ['type'=>'string','label'=>'Caption (image/video only)','required'=>false],
                    'filename'      => ['type'=>'string','label'=>'Filename (document only)','required'=>false],
                ],
            ],
        ];
        $all = apply_filters('inteflow_actions_registry_v1', $base);
        if (!is_array($all)) $all = $base;

        foreach ($all as $k => $a) {
            if (!is_array($a)) { unset($all[$k]); continue; }
            if (empty($a['id'])) $all[$k]['id'] = is_string($k) ? $k : '';
        }

        self::$actions = $all;
        return self::$actions;
    }

    public static function get(string $id) : ?array {
        $all = self::get_all();
        return isset($all[$id]) && is_array($all[$id]) ? $all[$id] : null;
    }
}
