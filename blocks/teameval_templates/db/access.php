<?php
    $capabilities = array(

    'block/teameval_templates:myaddinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'user' => CAP_ALLOW,
            'student' => CAP_PREVENT
        ),

        'clonepermissionsfrom' => 'moodle/my:manageblocks'

    ),

    'block/teameval_templates:addinstance' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,

        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ),

        'clonepermissionsfrom' => 'moodle/site:manageblocks'
    ),

    'block/teameval_templates:deletetemplate' => array(

        'captype' => 'write',
        'riskbitmask' => RISK_DATALOSS,
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => array(
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )

    )


);
