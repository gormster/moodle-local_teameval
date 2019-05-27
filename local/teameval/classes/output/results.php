<?php

namespace local_teameval\output;

use local_teameval\team_evaluation;

use renderable;
use templatable;
use renderer_base;
use coding_exception;

use stdClass;

class results implements renderable, templatable {

    protected $cmid;

    protected $types;

    protected $report;

    protected $current_plugin;

    public function __construct(team_evaluation $teameval, $reporttypes) {

        $cm = $teameval->get_coursemodule();

        if (empty($cm)) {
            throw new coding_exception('Tried to get results for team evaluation template');
        }

        $this->cmid = $cm->id;

        $this->current_plugin = $teameval->get_report_plugin()->name;
        $this->report = $teameval->get_report();

        $this->types = [];
        foreach($reporttypes as $plugininfo) {
            $type = ['name' => $plugininfo->displayname, 'plugin' => $plugininfo->name];
            if ($plugininfo->name == $this->current_plugin) {
                $type['selected'] = true;
            }
            $this->types[] = $type;
        }
    }

    public function export_for_template(renderer_base $output) {
        global $PAGE;

        $c = new stdClass;

        $report_renderer = $PAGE->get_renderer("teamevalreport_{$this->current_plugin}");
        $c->report = $report_renderer->render($this->report);
        $c->types = $this->types;
        $c->cmid = $this->cmid;

        return $c;
    }

}
