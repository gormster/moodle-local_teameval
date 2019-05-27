<?php

namespace teamevalquestion_split100;

use external_api;
use external_function_parameters;
use external_value;
use external_multiple_structure;
use external_single_structure;
use local_teameval\traits;
use local_teameval\team_evaluation;

use moodle_exception;

class external extends external_api {

    use traits\question\update_from_form;
    use traits\question\simple_delete;

    public static function plugin_name() {
        return 'split100';
    }

    public static function update_record($record, $formdata, $teameval) {
        $record->title = $formdata->title;
        $record->description = $formdata->description['text'];
    }

    public static function delete_records($questionid) {
        global $DB;
        $DB->delete_records('teamevalquestion_split100', ['id' => $questionid]);
        $DB->delete_records('teamevalquestion_split100_rs', ['id' => $questionid]);
    }

    public static function submit_response_parameters() {
        return new external_function_parameters([
            'teamevalid' => new external_value(PARAM_INT, 'id of teameval'),
            'questionid' => new external_value(PARAM_INT, 'id of question'),
            'percents' => new external_multiple_structure(
                new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'user id'),
                    'pct' => new external_value(PARAM_FLOAT, 'percentage allocated to that user')
                ])
            )
        ]);
    }

    public static function submit_response_returns() {
        return null;
    }

    public static function submit_response($teamevalid, $questionid, $percents) {
        global $DB, $USER;

        $context = team_evaluation::guard_capability($teamevalid, ['local/teameval:submitquestionnaire'], ['doanything' => false]);

        $teameval = new team_evaluation($teamevalid);

        if ($teameval->can_submit_response('split100', $questionid, $USER->id)) {
            $question = new question($teameval, $questionid);
            $response = new response($teameval, $question, $USER->id);
            $splits = [];
            foreach($percents as $p) {
                $splits[$p['userid']] = $p['pct'];
            }

            $response->update_splits($splits);

            $teameval->did_submit_response('split100', $questionid, $USER->id);
        } else {
            // todo: which exception is the right one here
            throw new moodle_exception('nopermission');
        }
    }

}
