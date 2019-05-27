<?php

namespace local_teameval;

use core_plugin_manager;
use coding_exception;

class question_info {

    protected $_teameval;
    protected $_id;
    protected $_type;
    protected $_plugininfo;
    protected $_question;
    protected $_questionid;
    protected $_submissiontemplate;
    protected $_editingtemplate;

    public function __construct($teameval, $id, $type, $questionid) {
        $this->_teameval = $teameval;
        $this->_id = $id;
        $this->_type = $type;
        $this->_questionid = $questionid;
        $this->_submissiontemplate = "teamevalquestion_$type/submission_view";
        $this->_editingtemplate = "teamevalquestion_$type/editing_view";
    }

    protected function get_plugininfo() {
        if (!isset($this->_plugininfo)) {
            $plugins = $this->_teameval->get_question_plugins();
            $this->_plugininfo = $plugins[$this->_type];
        }
        return $this->_plugininfo;
    }

    protected function get_question() {
        if (!isset($this->_question)) {
            $plugininfo = $this->get_plugininfo();
            $cls = $plugininfo->get_question_class();
            $this->_question = new $cls($this->_teameval, $this->_questionid);
        }
        return $this->_question;
    }

    public function __get($name) {
        switch($name) {
            case "plugininfo":
                return $this->get_plugininfo();
            case "question":
                return $this->get_question();
            case "id":
            case "questionid":
            case "submissiontemplate":
            case "editingtemplate":
            case "type":
                $name = '_'.$name;
                return $this->$name;
        }
        throw new coding_exception("Undefined property $name on class question_info.");
    }



}
