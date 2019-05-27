<?php

namespace teamevalquestion_comment\output;

use plugin_renderer_base;

class plaintext_renderer extends plugin_renderer_base {

    public function render_opinion_readable_short(opinion_readable_short $report) {
        return $report->export_for_plaintext();
    }

}
