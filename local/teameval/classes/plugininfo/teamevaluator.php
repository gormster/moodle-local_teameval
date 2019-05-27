<?php

namespace local_teameval\plugininfo;

defined('MOODLE_INTERNAL') || die();

class teamevaluator extends \core\plugininfo\base {

    public function get_evaluator_class() {
        return "\\teamevaluator_{$this->name}\\evaluator";
    }

}
