<?php

namespace teamevalquestion_comment\output;

use teamevalquestion_comment\question;
use teamevalquestion_comment\response;
use local_teameval\team_evaluation;
use renderable;
use templatable;
use stdClass;
use renderer_base;

class submission_view implements renderable, templatable {

    function __construct(question $question, team_evaluation $teameval, $locked = false) {
        $this->question = $question;
        $this->teameval = $teameval;
        $this->locked = $locked;
    }

    function export_for_template(renderer_base $output) {
        global $USER;

        $context = ['id' => $this->question->id, 'title' => $this->question->title, 'description' => $this->question->description, 'anonymous' => $this->question->anonymous, 'optional' => $this->question->optional]; 

        if(has_capability('local/teameval:submitquestionnaire', $this->teameval->get_context(), $USER->id, false)) {
            $teammates = $this->teameval->teammates($USER->id);
            $context['users'] = [];

            foreach($teammates as $t) {
                $response = new response($this->teameval, $this->question, $USER->id);
                $comment = $response->comment_on($t->id);

                $c = ['userid' => $t->id, 'name' => fullname($t)];
                if (! is_null($comment)) { 
                    $c['comment'] = $comment;
                }
                if ($t->id == $USER->id) {
                    $c['self'] = true;
                    $c['name'] = get_string('yourself', 'local_teameval');
                }
                $context['users'][] = $c;
            }
            $context['locked'] = $this->locked;

            if ($this->locked) {
                $context['incomplete'] = !$response->marks_given();
            }

        } else {
            $context['users'] = [['userid' => 0, 'name' => 'Example User']];
            if ($this->teameval->get_settings()->self) {
                array_unshift($context['users'], ['userid' => $USER->id, 'name' => get_string('yourself', 'local_teameval'), 'self' => true]);
            }
        }

        return $context;
    }

}