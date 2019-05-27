<?php

$functions = [

    'block_teameval_templates_update_title' => [

        'classname'     => 'block_teameval_templates\external',
        'methodname'    => 'update_title',
        'type'          => 'write',
        'ajax'          => true,

    ],

    'block_teameval_templates_delete_template' => [

        'classname'     => 'block_teameval_templates\external',
        'methodname'    => 'delete_template',
        'type'          => 'delete',
        'ajax'          => true,

    ],

    'block_teameval_templates_add_to_module' => [

        'classname'     => 'block_teameval_templates\external',
        'methodname'    => 'add_to_module',
        'type'          => 'write',
        'ajax'          => true,

    ],

];
