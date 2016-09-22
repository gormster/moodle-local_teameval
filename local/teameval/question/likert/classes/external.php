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

class external extends external_api {

    /* update_question */

    public static function update_question_parameters() {
        return new external_function_parameters([
            'teamevalid' => new external_value(PARAM_INT, 'id of teameval'),
            'ordinal' => new external_value(PARAM_INT, 'ordinal of question'),
            'id' => new external_value(PARAM_INT, 'id of question', VALUE_DEFAULT, 0),
            'title' => new external_value(PARAM_TEXT, 'title of question'),
            'description' => new external_value(PARAM_RAW, 'description of question'),
            'minval' => new external_value(PARAM_INT, 'minimum value'),
            'maxval' => new external_value(PARAM_INT, 'maximum value'),
            'meanings' => new external_multiple_structure(
                new external_single_structure([
                    'value' => new external_value(PARAM_INT, 'value meaning represents'),
                    'meaning' => new external_value(PARAM_TEXT, 'meaning of value')
                ])
            )
        ]);
    }

    public static function update_question_returns() {
        return new external_single_structure([
            "id" => new external_value(PARAM_INT, 'id of question'),
            "submissionContext" => new external_value(PARAM_RAW, 'json encoded submission context')
            ]);
    }

    public static function update_question($teamevalid, $ordinal, $id, $title, $description, $minval, $maxval, $meanings) {
        global $DB, $USER;

        team_evaluation::guard_capability($teamevalid, ['local/teameval:createquestionnaire']);

        $teameval = new team_evaluation($teamevalid);
        $transaction = $teameval->should_update_question("likert", $id, $USER->id);

        if ($transaction == null) {
            throw new moodle_exception("cannotupdatequestion", "local_teameval");
        }

        $any_response_submitted = false;
        if ($id > 0) {
            $question = new question($teameval, $id);
            $any_response_submitted = $question->any_response_submitted();
        }

        //get or create the record
        $record = ($id > 0) ? $DB->get_record('teamevalquestion_likert', array('id' => $id)) : new stdClass;
        
        //update the values
        $record->title = $title;
        $record->description = $description;
        if ($any_response_submitted == false) {
            $record->minval = min(max(0, $minval), 1); //between 0 and 1
            $record->maxval = min(max(3, $maxval), 10); //between 3 and 10
        }

        $record->meanings = new stdClass;
        foreach ($meanings as $m) {
            $val = $m['value'];
            $record->meanings->$val = $m['meaning'];
        }

        $record->meanings = json_encode($record->meanings);

        //save the record back to the DB
        if ($id > 0) {
            $DB->update_record('teamevalquestion_likert', $record);
        } else {
            $transaction->id = $DB->insert_record('teamevalquestion_likert', $record);
        }
        
        //finally tell the teameval we're done
        $teameval->update_question($transaction, $ordinal);

        $question = new question($teameval, $transaction->id);

        return ["id" => $id, "submissionContext" => json_encode($question->submission_view($USER->id))];

    }

    /* delete_question */

    public static function delete_question_parameters() {
        return new external_function_parameters([
            'teamevalid' => new external_value(PARAM_INT, 'id of teameval'),
            'id' => new external_value(PARAM_INT, 'id of question')
        ]);
    }

    public static function delete_question_returns() {
        return null;
    }

    public static function delete_question($teamevalid, $id) {
        global $DB, $USER;

        team_evaluation::guard_capability($teamevalid, ['local/teameval:createquestionnaire']);

        $teameval = new team_evaluation($teamevalid);

        $transaction = $teameval->should_delete_question("likert", $id, $USER->id);
        if ($transaction == null) {
            throw new moodle_exception("cannotupdatequestion", "local_teameval");
        }

        $DB->delete_records('teamevalquestion_likert', array('id' => $id));

        $teameval->delete_question($transaction);
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
        global $DB, $USER;

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
        }
    }

}