<?php

namespace teamevalreport_feedback\output;

use renderer_base;
use stdClass;
use local_teameval;
use user_picture;

class feedback_report implements \renderable, \templatable {

    protected $groups;

    protected $cmid;

    protected $members = [];

    protected $reports = [];

    protected $questions = [];

    protected $states = [];

    public function __construct($teameval, $groups, $questions) {

        $this->groups = $groups;

        $this->cmid = $teameval->get_coursemodule()->id;

        foreach($groups as $gid => $group) {

            $members = $teameval->group_members($gid);
            $this->members[$gid] = $members;

            foreach($members as $uid => $user) {
                foreach($questions as $q) {

                    $this->questions[$q->id] = $q;
                    $response = $teameval->get_response($q, $uid);
                    $released = $teameval->marks_released($uid);

                    foreach($teameval->teammates($uid) as $t => $teammate) {
                        $this->reports[$uid][$q->id][$t] = $response->feedback_for_readable($t);
                        if ($released) {
                            // if the state is unset and the marks are released it is implicitly approved
                            $this->states[$q->id][$uid][$t] = \local_teameval\FEEDBACK_APPROVED;
                        }
                    }

                }

            }

        }

        $rescinds = $teameval->all_rescind_states();

        foreach($rescinds as $r) {
            // if it's unset we don't want to override an implicit approv
            if ($r->state != \local_teameval\FEEDBACK_UNSET) {
                // going to use some PHP magic here
                $this->states[$r->questionid][$r->markerid][$r->targetid] = $r->state;
            }
        }

    }

    public function export_for_template(renderer_base $output) {
        global $PAGE;

        $c = new stdClass;

        $c->rejectrelease = $output->help_icon('rejectrelease', 'teamevalreport_feedback', true);

        $c->cmid = $this->cmid;
        $c->groups = [];

        foreach($this->groups as $gid => $group) {
            $g = new stdClass;
            $g->name = $group->name;
            $g->groupid = $gid;

            $g->markers = [];

            foreach($this->members[$gid] as $mid => $marker) {
                $m = new stdClass;
                $m->userpic = $output->render(new user_picture($marker));
                $m->fullname = fullname($marker);
                $m->userid = $mid;

                $m->questions = [];

                if (!isset($this->reports[$mid])) {
                    continue;
                }

                foreach($this->reports[$mid] as $qid => $reports) {
                    $q = new stdClass;
                    $q->title = $this->questions[$qid]->question->get_title();
                    $q->questionid = $qid;

                    $q->feedbacks = [];

                    $renderer = $PAGE->get_renderer('teamevalquestion_' . $this->questions[$qid]->plugininfo->name);

                    $odd = true;
                    foreach($reports as $uid => $report) {
                        if ($report) {
                            $f = new stdClass;
                            if ($uid == $mid) {
                                $f->name = get_string('themselves', 'local_teameval');
                                $f->self = true;
                            } else {
                                $f->name = fullname($this->members[$gid][$uid]);
                            }

                            $userpic = new user_picture($this->members[$gid][$uid]);
                            $userpic->size = 16;
                            $f->userpic = $output->render($userpic);
                            $f->markedid = $uid;
                            $f->odd = $odd;
                            $f->feedback = $renderer->render($report);

                            if (isset($this->states[$qid]) && isset($this->states[$qid][$mid]) && isset($this->states[$qid][$mid][$uid])) {
                                switch($this->states[$qid][$mid][$uid]) {
                                    case local_teameval\FEEDBACK_RESCINDED:
                                        $f->state = 'rejected';
                                        break;
                                    case local_teameval\FEEDBACK_APPROVED:
                                        $f->state = 'checked';
                                        break;
                                }
                            }

                            $odd = !$odd;

                            $q->feedbacks[] = $f;
                        }
                    }

                    if (count($q->feedbacks)) {
                        $m->questions[] = $q;
                    }
                }

                if (count($m->questions)) {
                    $g->markers[] = $m;
                }

            }

            if (count($g->markers)) {
                $c->groups[] = $g;
            }
        }

        return $c;
    }

}
