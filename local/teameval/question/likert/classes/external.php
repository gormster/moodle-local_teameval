<?php

namespace teamevalquestion_likert;

require_once("$CFG->libdir/externallib.php");

use external_api;
use external_function_parameters;
use external_value;
use external_format_value;
use external_single_structure;
use external_multiple_structure;
use invalid_parameter_exception;
use context_module;
use stdClass;
use moodle_exception;

use local_teameval;
use local_teameval\team_evaluation;
use local_teameval\traits;

class external extends external_api {

    /* update_question */

    // Since we need to modify the return value, we import this function aliased
    use traits\question\update_from_form {
        update_question as update_question_internal;
    }

    protected static function plugin_name() {
        return 'likert';
    }

    protected static function form_class() {
        return '\teamevalquestion_likert\forms\settings_form';
    }

    protected static function update_record($record, $data, $teameval) {
        $id = $data->id;
        $any_response_submitted = false;
        if ($id > 0) {
            $question = new question($teameval, $id);
            $any_response_submitted = $question->any_response_submitted();
        }

        //update the values
        $record->title = $data->title;
        $record->description = $data->description['text'];
        if ($any_response_submitted == false) {
            $record->minval = min(max(0, $data->range['min']), 1); //between 0 and 1
            $record->maxval = min(max(3, $data->range['max']), 10); //between 3 and 10
        }

        $record->meanings = new stdClass;
        foreach ($data->meanings as $k => $m) {
            $record->meanings->$k = $m;
        }

        $record->meanings = json_encode($record->meanings);
    }

    public static function update_question_returns() {
        return new external_single_structure([
            "id" => new external_value(PARAM_INT, 'id of question'),
            "submissionContext" => new external_value(PARAM_RAW, 'json encoded submission context')
        ]);
    }

    public static function update_question($teamevalid, $formdata) {
        global $PAGE;

        // Call the function imported from the update_from_form trait
        $record = static::update_question_internal($teamevalid, $formdata);

        $teameval = new team_evaluation($teamevalid);

        $question = new question($teameval, $record['id']);

        $output = $PAGE->get_renderer('teamevalquestion_likert');
        $record['submissionContext'] = json_encode($question->submission_view()->export_for_template($output));
        return $record;

    }

    /* delete_question */

    use traits\question\simple_delete;

    public static function delete_records($id) {
        global $DB;
        $DB->delete_records('teamevalquestion_likert', array('id' => $id));
        $DB->delete_records('teamevalquestion_likert_resp', array('questionid' => $id));
    }

    /* submit_response */

    public static function submit_response_parameters() {
        return new external_function_parameters([
            'teamevalid' => new external_value(PARAM_INT, 'id of teameval'),
            'id' => new external_value(PARAM_INT, 'id of question'),
            'marks' => new external_multiple_structure(
                new external_single_structure([
                    'touser' => new external_value(PARAM_INT, 'userid of user being rated'),
                    'value' => new external_value(PARAM_INT, 'selected value')
                ])
            )
        ]);
    }

    public static function submit_response_returns() {
        return null;
    }

    public static function submit_response($teamevalid, $id, $marks) {
        global $USER;

        team_evaluation::guard_capability($teamevalid, ['local/teameval:submitquestionnaire'], ['doanything' => false]);

        $teameval = new team_evaluation($teamevalid);

        if ($teameval->can_submit_response('likert', $id, $USER->id)) {
            $formdata = [];

            foreach($marks as $m) {
                $touser = $m['touser'];
                $value = $m['value'];
                $formdata[$touser] = $value;
            }

            $question = new question($teameval, $id);
            $response = new response($teameval, $question, $USER->id);
            $response->update_response($formdata);

            $teameval->did_submit_response('likert', $id, $USER->id);
        }
    }

}
