<?php

namespace local_teameval\forms;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once("$CFG->libdir/formslib.php");

use moodleform;
use external_function_parameters;
use external_value;
use external_format_value;
use external_single_structure;
use HTML_QuickForm_group;
use HTML_QuickForm_static;

abstract class ajaxform extends moodleform {

    public function __construct($action=null, $customdata=null, $method='post', $target='', $attributes=null, $editable=true,
                                $ajaxformdata=null) {
        if (empty($attributes['id'])) {
            $attributes['id'] = 'mform-' . uniqid();
        }
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $ajaxformdata);
    }

    /**
     * Call after definition() or definition_after_data() to fix element IDs to avoid duplicate IDs.
     */
    function fix_ids() {
        $mform = $this->_form;

        $uniq = uniqid();
        foreach ($mform->_elements as $key => $el) {
            $el->_generateId();
            $id = $el->getAttribute('id') . $uniq;
            $el->updateAttributes(['id' => $id]);
        }
    }

    function external_parameters() {
        $mform = $this->_form;
        $params = $this->elements_as_params($mform->_elements);
        return new external_function_parameters([
            'form' => new external_single_structure($params)
        ]);
    }

    function returns() {
        $mform = $this->_form;
        $params = $this->elements_as_params($mform->_elements, true);
        return new external_single_structure($params);
    }

    protected function elements_as_params($els, $return=false) {
        $params = [];
        foreach($els as $el) {
            $name = $el->getName();
            if ($return && in_array($name, ['sesskey', '_qf__'.$this->_formname])) {
                continue;
            }
            if ((strlen($name) > 0) && ($this->is_data_element($el))) {
                $params[$name] = $this->value_for_element($el, !$return);
            }
        }
        return $params;
    }

    protected function is_data_element($el)
    {
        // This function is incomplete. You can help by expanding it.
        if ($el instanceof HTML_QuickForm_static) {
            return false;
        }

        return true;
    }

    protected function value_for_element($element, $unpackgroups=true) {
        if ($unpackgroups && ($element instanceof HTML_QuickForm_group)) {
            $params = $this->elements_as_params($element->getElements());
            return new external_single_structure($params);
        } else {
            $cleantype = $this->clean_type_for_element($element);
            return new external_value($cleantype, 'AJAXFORM: ' . $element->getName());
        }
    }

    protected function clean_type_for_element($element) {
        $name = $element->getName();
        if (array_key_exists($name, $this->_form->_types)) {
            return $this->_form->_types[$name];
        }

        $type = $element->getType();

        $cleantype = PARAM_RAW;
        switch ($type) {
            case 'date_time_selector':
                $cleantype = PARAM_INT;
                break;
        }
        return $cleantype;
    }

    function process_data($json) {
        $this->_form->updateSubmission($json, []);
    }

    public function get_errors() {
        return $this->_form->_errors;
    }

}
