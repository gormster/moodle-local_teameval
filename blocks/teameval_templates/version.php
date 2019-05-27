<?php

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'block_teameval_templates';  // Recommended since 2.0.2 (MDL-26035). Required since 3.0 (MDL-48494)
$plugin->version = 2016091400;
$plugin->requires = 2015051100;
$plugin->dependencies = array(
    'local_teameval' => 2016051301
);
