<?php

namespace block_teameval_templates\output;

use local_teameval\team_evaluation;
use local_teameval\evaluation_context;

use templatable;
use renderable;
use renderer_base;
use stdClass;

class addtomodule implements templatable, renderable {

    protected $id;

    protected $sections;

    protected $module;

    public function __construct(team_evaluation $template, $courseid, $cmid = null) {

        $this->id = $template->id;

        $this->sections = [];

        $modinfo = get_fast_modinfo($courseid);

        if ($cmid) {

            $cm = $modinfo->get_cm($cmid);
            if(team_evaluation::check_capability($cm->context, ['local/teameval:createquestionnaire'])) {
                $module = new stdClass;
                $module->name = $cm->name;
                $module->cmid = $cm->id;
                $this->module = $module;
            }

        } else {

            foreach($modinfo->sections as $sectionnumber => $cmids) {
                $modules = [];

                foreach($cmids as $cmid) {
                    $cm = $modinfo->get_cm($cmid);
                    if(team_evaluation::check_capability($cm->context, ['local/teameval:createquestionnaire'])) {
                        $evalcontext = evaluation_context::context_for_module($cm, false);
                        if ($evalcontext && $evalcontext->evaluation_permitted()) {
                            $module = new stdClass;
                            $module->name = $cm->name;
                            $module->cmid = $cm->id;
                            $modules[] = $module;
                        }
                    }
                }

                if (count($modules)) {
                    $sectioninfo = $modinfo->get_section_info($sectionnumber);

                    $section = new stdClass;
                    $section->label = $sectioninfo->name ?: get_section_name($courseid, $sectionnumber);
                    $section->modules = $modules;

                    $this->sections[] = $section;
                }

            }

        }

    }

    public function is_empty() {
        return empty($this->module) && (count($this->sections) == 0);
    }

    public function export_for_template(renderer_base $output) {
        $c = new stdClass;
        $c->id = $this->id;
        if (isset($this->module)) {
            $c->module = $this->module;
        } else {
            $c->sections = $this->sections;
        }
        return $c;
    }

}
