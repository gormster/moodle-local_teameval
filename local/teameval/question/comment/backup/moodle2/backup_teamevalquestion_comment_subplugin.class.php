<?php

class backup_teamevalquestion_comment_subplugin extends backup_subplugin {

    public function define_question_subplugin_structure() {

        try {
            $userinfo = $this->get_setting_value('userinfo');
        } catch (base_plan_exception $e) {
            $userinfo = false;
        }

        $subplugin = $this->get_subplugin_element(null, '../../qtype', 'comment');

        $wrapper = new backup_nested_element($this->get_recommended_name());

        $subplugin->add_child($wrapper);

        $question = new backup_nested_element('commentquestion', ['id'],
            ['title',
            'description',
            'anonymous',
            'optional']);

        $wrapper->add_child($question);

        $question->set_source_table('teamevalquestion_comment', ['id' => '../../../../questionid']);

        if ($userinfo) {

            $responses = new backup_nested_element('commentresponses');
            $response = new backup_nested_element('commentresponse', ['id'], ['fromuser', 'touser', 'comment']);
            $responses->add_child($response);
            $question->add_child($responses);

            $response->set_source_table('teamevalquestion_comment_res', ['questionid' => backup::VAR_PARENTID]);

        }

        return $subplugin;

    }

}
