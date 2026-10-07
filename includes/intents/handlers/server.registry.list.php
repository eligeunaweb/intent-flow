<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {

    $reg->register(
        'server.registry.list',
        function() use ($reg) {

            $ref = new ReflectionClass($reg);
            $prop = $ref->getProperty('map');
            $prop->setAccessible(true);

            return [
                'intents' => array_keys($prop->getValue($reg))
            ];
        },
        [
            'capability' => 'manage_options',
            'scope' => 'diagnostics'
        ]
    );

};
