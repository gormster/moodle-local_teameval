<?php

$observers = [

    [
        'eventname' => '\core\event\course_module_deleted',
        'callback'  => 'local_teameval\events::module_deleted'
    ]

];
