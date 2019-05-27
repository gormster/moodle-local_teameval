<?php

namespace local_teameval\output;

use renderable;
use templatable;
use stdClass;
use renderer_base;
use moodle_url;
use file_picker;

use local_teameval\team_evaluation;

class add_question implements renderable, templatable {


    protected $teamevalid;

    protected $self;

    protected $locked;

    protected $subplugins;

    protected $showtoolbox;

    protected $download;

    protected $filepickeroptions;

    public function __construct(team_evaluation $teameval, $questiontypes) {

        $this->teamevalid = $teameval->id;

        $this->contextid = $teameval->get_context()->id;

        $this->self = $teameval->self;

        $this->locked = $teameval->questionnaire_locked() !== false;

        $this->subplugins = $questiontypes;

        $this->showtoolbox = $teameval->num_questions() == 0;

        $this->download = moodle_url::make_pluginfile_url(
            $teameval->get_context()->id,
            'local_teameval',
            'template',
            $teameval->id,
            '/', $teameval->template_file_name());

        if ($this->locked == false) {
            $options = new stdClass;
            $options->accepted_types = '*.mbz';
            $options->context = $teameval->get_context();
            $options->buttonname = 'choose';
            $options->itemid = file_get_unused_draft_itemid();
            $this->filepickeroptions = $options;
        }

    }

    public function export_for_template(renderer_base $output) {

        $c = new stdClass;

        $c->teamevalid = $this->teamevalid;
        $c->contextid = $this->contextid;
        $c->self = $this->self;
        $c->locked = $this->locked;
        $c->subplugins = [];
        foreach($this->subplugins as $plugin) {
            $c->subplugins[] = ['name' => $plugin->name, 'displayname' => $plugin->displayname];
        }
        $c->subplugins = json_encode($c->subplugins);
        $c->download = $this->download;
        $c->showtoolbox = $this->showtoolbox;

        if ($this->locked == false) {
            $filepicker = new file_picker($this->filepickeroptions);
            $c->filepicker = $output->render($filepicker);
            $c->filepickerid = $filepicker->options->client_id;
            $c->filepickeritemid = $filepicker->options->itemid;
            $this->filepickeroptions = $filepicker->options;
        }

        return $c;

    }

    public function get_filepicker_options() {
        if (empty($this->filepickeroptions)) {
            return null;
        }
        return $this->filepickeroptions;
    }

}
