<?php

class restore_teamevalquestion_split100_subplugin extends restore_subplugin {

    protected function define_question_subplugin_structure() {
        try {
            $userinfo = $this->get_setting_value('userinfo');
        } catch (base_plan_exception $e) {
            $userinfo = false;
        }
        $paths = [];

        $paths[] = new restore_path_element('split100question', $this->get_pathfor('/split100question'));
        if ($userinfo) {
            $paths[] = new restore_path_element('split100response', $this->get_pathfor('/split100question/split100responses/split100response'));
        }

        return $paths;
    }

    public function process_split100question($question) {
        global $DB;

        $question = (object)$question;

        $oldid = $question->id;
        unset($question->id);

        $newid = $DB->insert_record('teamevalquestion_split100', $question);

        $this->set_mapping('split100question', $oldid, $newid);

        $this->set_mapping('split100_questionid', $oldid, $newid);

    }

    public function process_split100response($response) {
        global $DB;

        $response = (object)$response;

        $response->questionid = $this->get_new_parentid('split100question');
        $response->fromuser = $this->get_mappingid('user', $response->fromuser);
        $response->touser = $this->get_mappingid('user', $response->touser);

        $DB->insert_record('teamevalquestion_split100_rs', $response);
    }

}
