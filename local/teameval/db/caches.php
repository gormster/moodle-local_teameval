<?php

$definitions = array(

    'settings' => array(
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true
    ),

    'evalcontext' => array(
        'mode' => cache_store::MODE_REQUEST,
        'simplekeys' => true,
    )
);
