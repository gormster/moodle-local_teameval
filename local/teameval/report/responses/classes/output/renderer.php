<?php

namespace teamevalreport_responses\output;

use plugin_renderer_base;
use stdClass;
use user_picture;

use teamevalreport_responses\output\responses_report;

class renderer extends plugin_renderer_base {

    public function render_responses_report(responses_report $report) {

        $data = $report->export_for_template($this);
        return parent::render_from_template('teamevalreport_responses/report', $data);

    }

}
