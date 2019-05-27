<?php

$functions = [

    'teamevalquestion_likert_update_question' => [

        'classname'     => 'teamevalquestion_likert\external',
        'methodname'    => 'update_question',
        'type'          => 'write',
        'ajax'          => true,

    ],

    'teamevalquestion_likert_delete_question' => [

        'classname'     => 'teamevalquestion_likert\external',
        'methodname'    => 'delete_question',
        'type'          => 'write',
        'ajax'          => true,

    ],

    'teamevalquestion_likert_submit_response' => [

        'classname'     => 'teamevalquestion_likert\external',
        'methodname'    => 'submit_response',
        'type'          => 'write',
        'ajax'          => true,

    ]

];
