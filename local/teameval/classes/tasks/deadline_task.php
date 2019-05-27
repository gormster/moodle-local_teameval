<?php

namespace local_teameval\tasks;

use \local_teameval\team_evaluation;
use coding_exception, moodle_exception;

class deadline_task extends \core\task\adhoc_task {

    private $teameval;

    private $deadline;

    public function __construct($id = NULL, $deadline = NULL) {
        if (is_null($id)) {
            if (!CLI_SCRIPT) {
                throw new coding_exception('Missing argument 1 for deadline_task::__construct');
            }
        } else if (!empty($deadline)) {
            // run at least one minute after the deadline
            $this->set_next_run_time($deadline + 60);
            $this->set_custom_data([ "id" => $id, "deadline" => $deadline]);
        }
    }

    public function execute() {

        $data = $this->get_custom_data();
        if ($data) {
            $id = $data->id;
            $this->teameval = new team_evaluation($id);
            $this->deadline = $data->deadline;
        }

        if (empty($this->teameval)) {
            throw new coding_exception('Team evaluation with ID ' . $id . ' does not exist');
        }

        if (empty($this->teameval->get_settings()->deadline)) {
            // do nothing
            return;
        }

        if ($this->teameval->deadline != $this->deadline) {
            // The deadline changed, and (presumably) another task was scheduled
            return;
        }

        if ($this->teameval->deadline > time()) {
            // This should probably never happen?
            throw new moodle_exception('tooearly', 'local_teameval');
        }

        // Otherwise, trigger a grade update event for everyone in the team eval
        $this->teameval->get_evaluation_context()->trigger_grade_update();

    }

}