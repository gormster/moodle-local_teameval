<?php

namespace teamevalreport_feedback\output;

use plugin_renderer_base;

class renderer extends plugin_renderer_base {

    public function render_feedback_report(feedback_report $report) {

        $data = $report->export_for_template($this);
        return parent::render_from_template('teamevalreport_feedback/report', $data);

    }

}
