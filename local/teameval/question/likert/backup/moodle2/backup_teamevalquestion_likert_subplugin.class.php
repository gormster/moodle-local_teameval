<?php

class backup_teamevalquestion_likert_subplugin extends backup_subplugin {

    public function define_question_subplugin_structure() {

        try {
            $userinfo = $this->get_setting_value('userinfo');
        } catch (base_plan_exception $e) {
            $userinfo = false;
        }

        $subplugin = $this->get_subplugin_element(null, '../../qtype', 'likert');

        $wrapper = new backup_nested_element($this->get_recommended_name());

        $subplugin->add_child($wrapper);

        $question = new backup_nested_element('likertquestion', ['id'],
            ['title',
            'description',
            'minval',
            'maxval',
            'meanings']);

        $wrapper->add_child($question);

        $question->set_source_table('teamevalquestion_likert', ['id' => '../../../../questionid']);

        if ($userinfo) {

            $responses = new backup_nested_element('likertresponses');
            $response = new backup_nested_element('likertresponse', ['id'], ['fromuser', 'touser', 'mark', 'markdate']);
            $responses->add_child($response);
            $question->add_child($responses);

            $response->set_source_table('teamevalquestion_likert_resp', ['questionid' => backup::VAR_PARENTID]);

        }

        return $subplugin;

    }

}
