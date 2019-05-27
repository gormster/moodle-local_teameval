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

        $this->feedback = $teameval->all_feedback($userid, false);

    }

    public function export_for_template(\renderer_base $output, $rendered = true) {
        global $PAGE;

        $context = new stdClass;

        $feedback = [];

        $context->questions = [];

        foreach($this->feedback as $q) {
            $f = new stdClass;

            if ($rendered) {
                $feedbackrenderer = 'teamevalquestion_' . $q->question->plugininfo->name;
                $renderer = $PAGE->get_renderer($feedbackrenderer);
            }

            $f->title = $q->question->question->get_title();
            $f->anonymous = $q->question->question->is_feedback_anonymous();
            $f->teammates = [];

            foreach($q->teammates as $t) {
                $fb = new stdClass;
                if (isset($t->from)) {
                    if ($rendered) {
                        $fb->userpic = $output->render(new user_picture($t->from));
                    }
                    if($t->from->id == $this->userid) {
                        $fb->self = true;
                        $fb->name = get_string('yourself', 'local_teameval');
                    } else {
                        $fb->name = fullname($t->from);
                    }
                }

                if ($rendered) {
                    $fb->feedback = $renderer->render($t->feedback->feedback_for_readable($this->userid));
                } else {
                    $fb->feedback = $t->feedback->feedback_for($this->userid);
                }

                if (!empty($fb->feedback)) {
                    $f->teammates[] = $fb;
                }
            }

            if (!empty($f->teammates)) {
                $context->questions[] = $f;
            }

        }

        $context->score = $this->score;

        return $context;

    }

}
