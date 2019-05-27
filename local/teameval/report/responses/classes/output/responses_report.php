<?php

namespace teamevalreport_responses\output;

use core_user;
use stdClass;
use user_picture;
use local_teameval\team_evaluation;

class responses_report implements \renderable, \templatable {

    protected $responses;

    protected $questiontypes;

    protected $downloadlink;

    public function __construct($responses, $questiontypes, $downloadlink) {
        $this->responses = $responses;
        $this->questiontypes = $questiontypes;
        $this->downloadlink = $downloadlink;
    }

    public function export_for_template(\renderer_base $output) {
        $c = new stdClass;

        $c->questions = [];
        $amdmodules = [];

        global $PAGE;

        $renderers = [];
        foreach($this->questiontypes as $qtype) {
            $renderers[$qtype] = $PAGE->get_renderer('teamevalquestion_' . $qtype);
        }

        foreach($this->responses as $question) {
            $q = new stdClass;
            $q->title = $question->questioninfo->question->get_title();
            $q->groups = [];

            foreach($question->groups as $groupinfo) {
                $g = new stdClass;
                $g->name = $groupinfo->group->name;
                $g->marked = [];

                $g->marks = [];

                foreach($groupinfo->members as $m) {
                    $marked = new stdClass;
                    $marked->fullname = fullname($m->user);
                    $g->marked[] = $marked;

                    $marks = [];
                    foreach($groupinfo->members as $n) {

                        $readable = $m->response->opinion_of_readable($n->user->id, 'teamevalreport_responses');
                        $mark = new stdClass;
                        $renderer = $renderers[$m->response->question->plugin_name()];

                        $mark->prerendered = $renderer->render($readable);
                        if ($readable->amd_init_call()) {
                            list($module, $call) = $readable->amd_init_call();
                            if (!isset($amdmodules[$module])) {
                                $amdmodules[$module] = [];
                            }
                            if (!in_array($call, $amdmodules[$module])) {
                                $amdmodules[$module][] = $call;
                            }
                        }

                        $marks[] = $mark;

                    }

                    $marker = new stdClass;
                    $marker->marker = fullname($m->user);
                    $marker->scores = $marks;

                    $g->marks[] = $marker;


                }

                $g->markedcount = count($g->marked);
                $g->markscount = count($g->marks) + 1;

                if (count($g->marks) > 0) {
                    $q->groups[] = $g;
                }
            }

            $c->questions[] = $q;
        }


        if($PAGE->has_set_url()) {
            // do stuff with the AMD shiz
            foreach($amdmodules as $module => $calls) {
                foreach ($calls as $call) {
                    $PAGE->requires->js_call_amd($module, $call);
                }
            }
        } else {
            $c->amdmodules = [];
            foreach($amdmodules as $module => $calls) {
                $m = new stdClass;
                $m->module = $module;
                $m->calls = $calls;
                $c->amdmodules[] = $m;
            }
        }

        $c->downloadlink = addslashes($this->downloadlink);

        return $c;
    }

    public function export_for_csv() {
        global $PAGE;

        $questions = [];

        $renderers = [];
        foreach($this->questiontypes as $qtype) {
            $subtypes = team_evaluation::plugin_supports_renderer_subtype('teamevalquestion_' . $qtype, ['csv', 'plaintext']);
            $subtype = current($subtypes);
            $renderers[$qtype] = $PAGE->get_renderer('teamevalquestion_' . $qtype, $subtype);
        }

        foreach($this->responses as $question) {
            $q = new stdClass;
            $qobj = $question->questioninfo->question;
            $q->title = $qobj->get_title();
            $q->max = $qobj->has_value() ? $qobj->maximum_value() : null;
            $q->groups = [];

            foreach($question->groups as $groupinfo) {
                $g = new stdClass;
                $g->name = $groupinfo->group->name;
                $g->header = [];
                $g->rows = [];

                foreach($groupinfo->members as $m) {
                    $g->header[] = fullname($m->user); // this depends on ->members order being reliable but so does this whole script
                    $row = [];
                    $row[] = fullname($m->user);
                    foreach($groupinfo->members as $n) {
                        $markee = fullname($n->user);
                        $opinion = $m->response->opinion_of_readable($n->user->id, 'teamevalreport_responses');
                        $renderer = $renderers[$m->response->question->plugin_name()];
                        $row[] = $renderer->render($opinion);
                    }
                    $g->rows[] = $row;
                }

                $q->groups[] = $g;

            }

            $questions[] = $q;

        }

        return $questions;
    }

}
