<?php

namespace local_teameval\output;

use local_teameval;
use stdClass;
use user_picture;

class feedback implements \renderable, \templatable {

    protected $userid;

    protected $score;

    protected $feedback;

    public function __construct(local_teameval\team_evaluation $teameval, $userid) {

        $this->userid = $userid;

        $adjusted = $teameval->adjusted_grade($userid);
        $this->score = is_null($adjusted) ? null : $teameval->get_evaluation_context()->format_grade($adjusted);

        $allquestions = $teameval->get_questions();

        $questions = array_filter($allquestions, function($q) {
            return $q->question->has_feedback();
        });		

        $teammates = $teameval->teammates($userid);

        $this->feedback = [];


        foreach($questions as $qi) {
            $question = new stdClass;
            $question->title = $qi->question->get_title();
            $question->teammates = [];
            $question->feedbackrenderer = 'teamevalquestion_' . $qi->plugininfo->name;
            $question->anonymous = $qi->question->is_feedback_anonymous();

            foreach($teammates as $uid => $m) {

                if ($teameval->rescinded($qi->id, $uid, $userid) == \local_teameval\FEEDBACK_RESCINDED) {
                    continue;
                }

                $response = $teameval->get_response($qi, $uid);
                $f = new stdClass;

                if(!$question->anonymous) {
                    $f->user = $m;
                }

                $f->feedback = $response->feedback_for_readable($userid);

                // TODO: if there is no feedback, don't add

                $question->teammates[] = $f;
            }
            $this->feedback[] = $question;
        }

    }

    public function export_for_template(\renderer_base $output) {

        $context = new stdClass;

        global $PAGE;

        foreach($this->feedback as $q) {
            $renderer = $PAGE->get_renderer($q->feedbackrenderer);
            unset($q->feedbackrenderer);

            foreach($q->teammates as $t) {
                if (isset($t->user)) {
                    $t->userpic = $output->render(new user_picture($t->user));
                    if($t->user->id == $this->userid) {
                        $t->self = true;
                        $t->name = get_string('yourself', 'local_teameval');
                    } else {
                        $t->name = fullname($t->user);
                    }
                }
                $t->feedback = $renderer->render($t->feedback);
                unset($t->user);
            }

        }

        $context->score = $this->score;
        $context->questions = $this->feedback;

        return $context;

    }

}