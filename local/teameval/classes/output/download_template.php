<?php

namespace local_teameval\output;

use local_teameval\team_evaluation;

use renderable;
use templatable;
use renderer_base;
use coding_exception;
use moodle_url;

use stdClass;

class download_template implements renderable, templatable {

    protected $download;

    public function __construct(team_evaluation $teameval) {
        $this->download = moodle_url::make_pluginfile_url(
            $teameval->get_context()->id,
            'local_teameval',
            'template',
            $teameval->id,
            '/', $teameval->template_file_name());
    }

    public function export_for_template(renderer_base $output) {
        $c = new stdClass;

        $c->download = $this->download;

        return $c;
    }

}
