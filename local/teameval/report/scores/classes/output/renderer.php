<?php

namespace teamevalreport_scores\output;

use plugin_renderer_base;
use stdClass;
use user_picture;

use teamevalreport_scores\output\scores_report;

class renderer extends plugin_renderer_base {

    public function render_scores_report(scores_report $report) {

        $data = $report->export_for_template($this);
        return parent::render_from_template('teamevalreport_scores/report', $data);

    }

}
