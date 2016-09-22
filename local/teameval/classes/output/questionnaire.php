<?php

namespace local_teameval\output;

use local_teameval\team_evaluation;

use renderable;
use templatable;
use renderer_base;
use coding_exception;

use stdClass;

class questionnaire implements renderable, templatable {

    protected $questions;

    protected $deadline;

    protected $submission;

    protected $editing;

    protected $noncompletion;

    protected $locked;

    protected $lockedreason;

    protected $lockedhint;

    protected $id;

    public function __construct(team_evaluation $teameval) {
        global $USER;

        $context = $teameval->get_context();
        
        $this->id = $teameval->id;

        $this->questions = [];

        foreach($teameval->get_questions() as $q) {
            $locked = !$teameval->can_submit_response($q->plugininfo->name, $q->questionid, $USER->id);
            $submissionview = $q->question->submission_view($USER->id, $locked);
            $editingview = $q->question->editing_view($USER->id);
            $this->questions[] = [
                "template" => $q->submissiontemplate,
                "type" => $q->plugininfo->name,
                "questionid" => $q->questionid,
                "submissioncontext" => $submissionview,
                "editingcontext" => $editingview
                ];
        }

        $this->deadline = $teameval->get_settings()->deadline;

        $this->noncompletion = null;

        $this->submission = $teameval->can_submit($USER->id);

        if ($this->submission) {
            
            $this->cmid = $teameval->get_coursemodule()->id;

        } else if (has_capability('local/teameval:submitquestionnaire', $context, null, false)) {
            // if we have this capability but can't submit then we need to communicate noncompletion
            $completion = $teameval->user_completion($USER->id);
            if ($completion < 1) {
                $n = count($this->questions) - round($completion * count($this->questions));
                $penalty = round($teameval->non_completion_penalty($USER->id) * 100, 2);
                $this->noncompletion = ['n' => $n, 'penalty' => $penalty];
            }
        }

        $this->editing = false;

        if (team_evaluation::check_capability($context, ['local/teameval:createquestionnaire'])) {
            $this->editing = true;
            $this->locked = $teameval->questionnaire_locked();
            if (!empty($this->locked)) {
                list($reason, $user) = $this->locked;
                $this->lockedreason = team_evaluation::questionnaire_locked_reason($reason);
                $this->lockedhint = team_evaluation::questionnaire_locked_hint($reason, $user, $teameval->get_evaluation_context());
            }
        }

    }

    public function export_for_template(renderer_base $output) {

        $c = new stdClass;

        $c->questions = [];
        foreach($this->questions as $q) {
            $q['content'] = $output->render_from_template($q['template'], $q['submissioncontext'] + 
                ["_id" => $this->id, '_submission' => $this->submission, '_editing' => $this->editing]);

            $q['submissioncontext'] = json_encode($q['submissioncontext']);

            if ($this->editing) {
                $q['editingcontext'] = json_encode($q['editingcontext']);
            } else {
                unset($q['editingcontext']);
            }

            unset($q['template']);
            
            $c->questions[] = $q;
        }

        if ($this->deadline > 0) {
            $c->deadline = userdate($this->deadline);
        }

        $c->noncompletion = $this->noncompletion;

        $c->submission = $this->submission;
        $c->editing = $this->editing;

        if ($this->submission) {
            $c->cmid = $this->cmid;
        }

        if (!empty($this->locked)) {
            $c->locked = true;
            $c->lockedreason = $this->lockedreason;
            $c->lockedhint = $this->lockedhint;
        } else {
            $c->locked = false;
        }

        return $c;

    }

}