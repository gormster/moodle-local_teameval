<?php

defined('MOODLE_INTERNAL') || die();

class restore_local_teameval_plugin extends restore_local_plugin {

    // This is necessary because add_subplugin_structure is declared protected.
    // So there's a lot of working around that fact.
    public function define_plugin_structure($connectionpoint) {
        if (!$connectionpoint instanceof restore_path_element) {
            throw new restore_step_exception('restore_path_element_required', $connectionpoint);
        }

        $paths = array();
        $this->connectionpoint = $connectionpoint;
        $methodname = 'define_' . basename($this->connectionpoint->get_path()) . '_plugin_structure';

        if (method_exists($this, $methodname)) {
            if ($bluginpaths = $this->$methodname()) {
                foreach ($bluginpaths as $path) {
                    if (is_null($path->get_processing_object())) {
                        $path->set_processing_object($this);
                    }
                    $paths[] = $path;
                }
            }
        }
        return $paths;
    }





    protected $addtoteameval = 0;

    protected $ordinalbase = 0;


    protected function define_module_plugin_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $teameval = new restore_path_element('teameval', $this->get_pathfor('/teameval'));
        $question = new restore_path_element('question', $this->get_pathfor('/teameval/questions/question'));

        $paths = [$teameval, $question];

        if ($userinfo) {
            $release_user = new restore_path_element('release_user', $this->get_pathfor('/teameval/releases/release_user'));
            $release_group = new restore_path_element('release_group', $this->get_pathfor('/teameval/releases/release_group'));
            $release_all = new restore_path_element('release_all', $this->get_pathfor('/teameval/releases/release_all'));
            $rescind = new restore_path_element('rescind', $this->get_pathfor('/teameval/questions/question/rescinds/rescind'));

            $paths = array_merge($paths, [$release_user, $release_group, $release_all, $rescind]);
            $this->step->log('userinfo paths set', backup::LOG_DEBUG);
        }

        $question_subplugins = $this->add_subplugin_structure('teamevalquestion', $question, 'local', 'teameval');

        $paths = array_merge($question_subplugins, $paths);

        return $paths;
    }

    protected function define_course_plugin_structure() {
        $template = new restore_path_element('template', $this->get_pathfor('/teameval'));
        $question = new restore_path_element('question', $this->get_pathfor('/teameval/questions/question'));

        $question_subplugins = $this->add_subplugin_structure('teamevalquestion', $question, 'local', 'teameval');

        $paths = [$template, $question];

        $paths = array_merge($question_subplugins, $paths);

        return $paths;
    }

    protected function define_template_plugin_structure() {
        try {
            $this->addtoteameval = $this->get_setting_value('addtoteameval');
            $this->ordinalbase = $this->get_setting_value('ordinalbase');
        } catch (base_plan_exception $e) {
            // do nothing
        }

        $paths = [];

        if ($this->addtoteameval === 0) {
            $paths[] = new restore_path_element('template', $this->get_pathfor('/teameval'));
        }
        $question = new restore_path_element('question', $this->get_pathfor('/teameval/questions/question'));
        $paths[] = $question;

        $question_subplugins = $this->add_subplugin_structure('teamevalquestion', $question, 'local', 'teameval');

        $paths = array_merge($question_subplugins, $paths);

        return $paths;
    }

    public function process_teameval($settings) {
        global $DB;

        // it boggles my mind that this is necessary
        // but sometimes this function is called with an object
        // and sometimes with an array
        $settings = (object)$settings;

        $cmid = $this->task->get_moduleid();
        $settings->cmid = $cmid;
        $settings->deadline = $this->apply_date_offset($settings->deadline);
        $oldid = $settings->id;
        $newid = $DB->insert_record('teameval', $settings);

        $this->set_mapping('teameval', $oldid, $newid);
    }

    public function process_template($settings) {
        global $DB;

        $settings = (object)$settings;
        $settings->contextid = $this->task->get_contextid();
        $oldid = $settings->id;
        $newid = $DB->insert_record('teameval', $settings);

        $this->set_mapping('template', $oldid, $newid);
    }

    public function process_question($question) {
        global $DB;

        $this->step->log('processing question!', backup::LOG_DEBUG);

        $question = (object)$question;
        $oldid = $question->id;

        // we make the question ID a negative number, to eliminate the possibility of duplicate keys
        $question->questionid = -$question->questionid;

        // when we're importing questions in to an existing teameval, we need to rebase ordinals
        // if we're not, then ordinalbase should be 0
        $question->ordinal += $this->ordinalbase;

        if ($this->addtoteameval > 0) {
            $question->teamevalid = $this->addtoteameval;
        } else {
            $question->teamevalid = $this->get_new_parentid('teameval') ?: $this->get_new_parentid('template');
        }
        $newid = $DB->insert_record('teameval_questions', $question);

        $this->set_mapping('question', $oldid, $newid);
    }

    protected function post_process_question() {
        global $DB;
        //fix question ids
        if ($this->addtoteameval > 0) {
            $teamevalid = $this->addtoteameval;
        } else {
            $teamevalid = $this->get_new_parentid('teameval') ?: $this->get_new_parentid('template');
        }

        $questions = $DB->get_records('teameval_questions', ['teamevalid' => $teamevalid]);
        foreach($questions as $question) {
            // we saved this as a negative number, so we have to un-negate it
            $oldid = -$question->questionid;
            $question->questionid = $this->get_mappingid($question->qtype.'_questionid', $oldid);
            if ($question->questionid) {
                $DB->update_record('teameval_questions', $question);
            } else {
                if ($question->ordinal >= $this->ordinalbase) { // this question should have restored, but failed
                    $DB->delete_records('teameval_questions', ['id' => $question->id]);
                }
            }
        }

        // make sure that we get any leftover questions if some questions failed
        $DB->delete_records_select('teameval_questions', 'questionid < 0');
    }

    public function process_release_all($release) {
        $release = (object)$release;
        $this->process_release($release, 0);
    }

    public function process_release_group($release) {
        $release = (object)$release;
        $release->target = $this->get_mappingid('group', $release->target);
        $this->process_release($release, 1);
    }

    public function process_release_user($release) {
        $release = (object)$release;
        $release->target = $this->get_mappingid('user', $release->target);
        $this->process_release($release, 2);
    }

    protected function process_release($release, $level) {
        global $DB;

        $cmid = $this->task->get_moduleid();

        $release->level = $level;
        $release->cmid = $cmid;

        $this->step->log("release $release->cmid $release->level $release->target", backup::LOG_DEBUG);

        $DB->insert_record('teameval_release', $release);
    }

    public function process_rescind($rescind) {
        global $DB;

        $rescind = (object)$rescind;

        $questionid = $this->get_new_parentid('question');
        $this->step->log('rescind new questionid '.$questionid, backup::LOG_DEBUG);

        $rescind->questionid = $questionid;
        $rescind->markerid = $this->get_mappingid('user', $rescind->markerid);
        $rescind->targetid = $this->get_mappingid('user', $rescind->targetid);

        $DB->insert_record('teameval_rescind', $rescind);

    }





    protected function after_execute_module() {
        $this->post_process_question();
    }

    protected function after_execute_course() {
        $this->post_process_question();
    }

    protected function after_execute_template() {
        $this->post_process_question();
    }




    // As above, because this is declared protected on backup_structure_step.

    protected function add_subplugin_structure($subplugintype, $element, $plugintype, $pluginname) {
        global $CFG;
        // This global declaration is required, because where we do require_once($backupfile);
        // That file may in turn try to do require_once($CFG->dirroot ...).
        // That worked in the past, we should keep it working.

        // Check the requested plugintype is a valid one.
        if (!array_key_exists($plugintype, core_component::get_plugin_types())) {
            throw new restore_step_exception('incorrect_plugin_type', $plugintype);
        }
        // Check the requested pluginname, for the specified plugintype, is a valid one.
        if (!array_key_exists($pluginname, core_component::get_plugin_list($plugintype))) {
            throw new restore_step_exception('incorrect_plugin_name', array($plugintype, $pluginname));
        }
        // Check the requested subplugintype is a valid one.
        $subpluginsfile = core_component::get_component_directory($plugintype . '_' . $pluginname) . '/db/subplugins.php';
        if (!file_exists($subpluginsfile)) {
            throw new restore_step_exception('plugin_missing_subplugins_php_file', array($plugintype, $pluginname));
        }
        include($subpluginsfile);
        if (!array_key_exists($subplugintype, $subplugins)) {
             throw new restore_step_exception('incorrect_subplugin_type', $subplugintype);
        }
        // Every subplugin optionally can have a common/parent subplugin
        // class for shared stuff.
        $parentclass = 'restore_' . $plugintype . '_' . $pluginname . '_' . $subplugintype . '_subplugin';
        $parentfile = core_component::get_component_directory($plugintype . '_' . $pluginname) .
            '/backup/moodle2/' . $parentclass . '.class.php';
        if (file_exists($parentfile)) {
            require_once($parentfile);
        }
        // Get all the restore path elements, looking across all the subplugin dirs.
        $subpluginsdirs = core_component::get_plugin_list($subplugintype);

        $paths = [];
        foreach ($subpluginsdirs as $name => $subpluginsdir) {
            $classname = 'restore_' . $subplugintype . '_' . $name . '_subplugin';
            $restorefile = $subpluginsdir . '/backup/moodle2/' . $classname . '.class.php';
            if (file_exists($restorefile)) {
                require_once($restorefile);
                $restoresubplugin = new $classname($subplugintype, $name, $this->step);
                // Add subplugin paths to the step.
                $paths = array_merge($paths, $restoresubplugin->define_subplugin_structure($element));
            }
        }
        return $paths;
    }

}
