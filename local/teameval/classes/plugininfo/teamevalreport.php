<?php

namespace local_teameval\plugininfo;

defined('MOODLE_INTERNAL') || die();

class teamevalreport extends \core\plugininfo\base {

    public function get_report_class() {
        return "\\teamevalreport_{$this->name}\\report";
    }

    public function is_uninstall_allowed() {
        return true;
    }

}
