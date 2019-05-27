<?php

$functions = [

    'teamevalquestion_comment_update_question' => [

        'classname'     => 'teamevalquestion_comment\external',
        'methodname'    => 'update_question',
        'type'          => 'write',
        'ajax'          => true,

    ],

    'teamevalquestion_comment_delete_question' => [

        'classname'     => 'teamevalquestion_comment\external',
        'methodname'    => 'delete_question',
        'type'          => 'write',
        'ajax'          => true,

    ],

    'teamevalquestion_comment_submit_response' => [

        'classname'     => 'teamevalquestion_comment\external',
        'methodname'    => 'submit_response',
        'type'          => 'write',
        'ajax'          => true,

    ]

];
