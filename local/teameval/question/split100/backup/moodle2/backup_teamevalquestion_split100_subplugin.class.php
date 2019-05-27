<?php

class backup_teamevalquestion_split100_subplugin extends backup_subplugin {

    public function define_question_subplugin_structure() {

        try {
            $userinfo = $this->get_setting_value('userinfo');
        } catch (base_plan_exception $e) {
            $userinfo = false;
        }

        $subplugin = $this->get_subplugin_element(null, '../../qtype', 'split100');

        $wrapper = new backup_nested_element($this->get_recommended_name());

        $subplugin->add_child($wrapper);

        $question = new backup_nested_element('split100question', ['id'],
            ['title', 'description']);

        $wrapper->add_child($question);

        $question->set_source_table('teamevalquestion_split100', ['id' => '../../../../questionid']);

        if ($userinfo) {

            $responses = new backup_nested_element('split100responses');
            $response = new backup_nested_element('split100response', ['id'], ['fromuser', 'touser', 'pct']);
            $responses->add_child($response);
            $question->add_child($responses);

            $response->set_source_table('teamevalquestion_split100_rs', ['questionid' => backup::VAR_PARENTID]);

        }

        return $subplugin;

    }

}
