<?php

namespace teamevalquestion_comment;

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
use coding_exception;
use moodle_exception;

use local_teameval;
use local_teameval\team_evaluation;
use local_teameval\traits;

class external extends external_api {

    use traits\question\update_from_form;

    protected static function plugin_name() {
        return 'comment';
    }

    protected static function update_record($record, $data, $teameval) {
        $id = $data->id;

        $any_response_submitted = false;
        if ($id > 0) {
            $question = new question($teameval, $id);
            $any_response_submitted = $question->any_response_submitted();
        }

        $record->title = $data->title;
        $record->description = $data->description['text'];
        if ($any_response_submitted == false) {
            $record->anonymous = $data->anonymous;
            $record->optional = $data->optional;
        }
    }

    /* delete_question */

    use traits\question\simple_delete;

    protected static function delete_records($id) {
        global $DB;
        $DB->delete_records('teamevalquestion_comment', ['id' => $id]);
        $DB->delete_records('teamevalquestion_comment_res', ['questionid' => $id]);
    }

    /* submit_response */

    public static function submit_response_parameters() {
        return new external_function_parameters([
            'teamevalid' => new external_value(PARAM_INT, 'id of teameval'),
            'id' => new external_value(PARAM_INT, 'id of question'),
            'comments' => new external_multiple_structure(
                new external_single_structure([
                    'touser' => new external_value(PARAM_INT, 'id of target user'),
                    'comment' => new external_value(PARAM_RAW, 'comments made by user')
                ])
            )
        ]);
    }

    public static function submit_response_returns() {
        return null;
    }

    public static function submit_response($teamevalid, $id, $comments) {
        global $USER;

        team_evaluation::guard_capability($teamevalid, ['local/teameval:submitquestionnaire'], ['doanything' => false]);

        $teameval = new team_evaluation($teamevalid);

        if ($teameval->can_submit_response('comment', $id, $USER->id)) {
            $question = new question($teameval, $id);
            $response = new response($teameval, $question, $USER->id);

            $formdata = [];

            foreach($comments as $c) {
                $touser = $c['touser'];
                $comment = $c['comment'];
                $formdata[$touser] = $comment;
            }

            $response->update_comments($formdata);

            $teameval->did_submit_response('comment', $id, $USER->id);
        }
    }
}
