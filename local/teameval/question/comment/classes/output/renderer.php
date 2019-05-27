<?php

namespace teamevalquestion_comment\output;

use teamevalquestion_comment\forms;
use plugin_renderer_base;

class renderer extends plugin_renderer_base {

    public function render_feedback_readable(feedback_readable $report) {
        $data = $report->export_for_template($this);
        return parent::render_from_template('teamevalquestion_comment/feedback_readable', $data);
    }

    public function render_opinion_readable_short(opinion_readable_short $report) {
        $data = $report->export_for_template($this);
        return parent::render_from_template('teamevalquestion_comment/opinion_readable_short', $data);
    }

    public function render_submission_view(submission_view $view) {
        $data = $view->export_for_template($this);
        return parent::render_from_template('teamevalquestion_comment/submission_view', $data);
    }

    public function render_editing_view(editing_view $view) {
        $form = new forms\edit_form(null, ['locked' => $view->locked]);
        $form->set_data($view->formdata);
        return $form->render();
    }

}
