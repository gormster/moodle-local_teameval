<?php

namespace teamevalquestion_likert\output;

use plugin_renderer_base;

class renderer extends plugin_renderer_base {

    public function render_opinion_readable(opinion_readable $opinion) {
        $data = $opinion->export_for_template($this);
        return parent::render_from_template('teamevalquestion_likert/opinion_readable', $data);
    }

    public function render_submission_view(submission_view $view) {
        $data = $view->export_for_template($this);
        return parent::render_from_template('teamevalquestion_likert/submission_view', $data);
    }

    public function render_editing_view(editing_view $view) {
        return $view->form->render();
    }

}
