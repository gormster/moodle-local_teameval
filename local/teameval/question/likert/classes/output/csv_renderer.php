<?php

namespace teamevalquestion_likert\output;

use plugin_renderer_base;

class csv_renderer extends plugin_renderer_base {

    public function render_opinion_readable(opinion_readable $opinion) {
        return $opinion->export_for_csv();
    }

}
