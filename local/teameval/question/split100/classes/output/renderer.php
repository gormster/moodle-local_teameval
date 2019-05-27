<?php

namespace teamevalquestion_split100\output;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;

class renderer extends plugin_renderer_base {

    public function render_submission_view(submission_view $view) {
        $data = $view->export_for_template($this);
        return parent::render_from_template('teamevalquestion_split100/submission_view', $data);
    }

    public function render_editing_view(editing_view $view) {
        return $view->form->render();
    }

    public function render_opinion(opinion $view) {
        $data = $view->export_for_template($this);
        return parent::render_from_template('teamevalquestion_split100/opinion', $data);
    }

}
