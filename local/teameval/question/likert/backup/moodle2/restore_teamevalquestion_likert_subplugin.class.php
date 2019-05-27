<?php

class restore_teamevalquestion_likert_subplugin extends restore_subplugin {

    protected function define_question_subplugin_structure() {
        try {
            $userinfo = $this->get_setting_value('userinfo');
        } catch (base_plan_exception $e) {
            $userinfo = false;
        }
        $paths = [];

        $paths[] = new restore_path_element('likertquestion', $this->get_pathfor('/likertquestion'));
        if ($userinfo) {
            $paths[] = new restore_path_element('likertresponse', $this->get_pathfor('/likertquestion/likertresponses/likertresponse'));
        }

        return $paths;
    }

    public function process_likertquestion($question) {
        global $DB;

        $question = (object)$question;

        $oldid = $question->id;
        unset($question->id);

        $newid = $DB->insert_record('teamevalquestion_likert', $question);

        $this->set_mapping('likertquestion', $oldid, $newid);

        $this->set_mapping('likert_questionid', $oldid, $newid);

    }

    public function process_likertresponse($response) {
        global $DB;

        $response = (object)$response;

        $response->questionid = $this->get_new_parentid('likertquestion');
        $response->fromuser = $this->get_mappingid('user', $response->fromuser);
        $response->touser = $this->get_mappingid('user', $response->touser);

        $DB->insert_record('teamevalquestion_likert_resp', $response);
    }

    //TODO: if restore failed and teameval_questions was not updated, delete these rows

    public function after_restore_question() {
    }

}
