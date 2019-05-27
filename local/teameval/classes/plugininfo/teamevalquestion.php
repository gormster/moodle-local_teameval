<?php

namespace local_teameval\plugininfo;

defined('MOODLE_INTERNAL') || die();

/**
 * Some important information for your question subplugin:
 *
 * You must name your question and response classes question and response.
 * There is no way to override this plugin behaviour.
 *
 * If you support multiple renderers (and you should!) you should declare your
 * supported subtypes in your question class as a static function
 * question::supported_renderer_subtypes.
 *
 */

class teamevalquestion extends \core\plugininfo\base {

    public function get_question_class() {
        return "\\teamevalquestion_{$this->name}\\question";
    }

    public function get_response_class() {
        return "\\teamevalquestion_{$this->name}\\response";
    }

    public function is_uninstall_allowed() {
        return true;
    }

    public function supported_renderer_subtypes() {
        $cls = $this->get_question_class();
        if (method_exists($cls, 'supported_renderer_subtypes')) {
            return $cls::supported_renderer_subtypes();
        }
        return [];
    }

}
