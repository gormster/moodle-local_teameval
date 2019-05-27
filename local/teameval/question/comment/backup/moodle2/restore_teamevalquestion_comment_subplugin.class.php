<?php

class restore_teamevalquestion_comment_subplugin extends restore_subplugin {

    protected function define_question_subplugin_structure() {
        try {
            $userinfo = $this->get_setting_value('userinfo');
        } catch (base_plan_exception $e) {
            $userinfo = false;
        }

        $paths = [];

        $paths[] = new restore_path_element('commentquestion', $this->get_pathfor('/commentquestion'));
        if ($userinfo) {
            $paths[] = new restore_path_element('commentresponse', $this->get_pathfor('/commentquestion/commentresponses/commentresponse'));
        }

        return $paths;
    }

    public function process_commentquestion($question) {
        global $DB;

        $question = (object)$question;

        $oldid = $question->id;

        $newid = $DB->insert_record('teamevalquestion_comment', $question);

        // this one is for responses
        $this->set_mapping('commentquestion', $oldid, $newid);

        // and this one is for teameval
        $this->set_mapping('comment_questionid', $oldid, $newid);

    }

    public function process_commentresponse($response) {
        global $DB;

        $response = (object)$response;

        $response->questionid = $this->get_new_parentid('commentquestion');
        $response->fromuser = $this->get_mappingid('user', $response->fromuser);
        $response->touser = $this->get_mappingid('user', $response->touser);

        $DB->insert_record('teamevalquestion_comment_res', $response);
    }

    //TODO: if restore failed and teameval_questions was not updated, delete these rows

    public function after_restore_question() {
    }

}
