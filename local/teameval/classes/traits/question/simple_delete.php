<?php

namespace local_teameval\traits\question;

use external_function_parameters;
use external_value;

use stdClass;
use coding_exception;
use moodle_exception;

use local_teameval\team_evaluation;

/*

This trait is designed to work with the default implementation of delete() in question.js.

All you need to implement is plugin_name() and delete_records().

 */

interface simple_delete_interface {
    static function plugin_name();

    static function delete_records($questionid);
}

trait simple_delete {

    // ---
    // Don't override these methods
    // ---

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
        global $USER;

        team_evaluation::guard_capability($teamevalid, ['local/teameval:createquestionnaire']);

        $teameval = new team_evaluation($teamevalid);

        $plugin = static::plugin_name();

        $transaction = $teameval->should_delete_question($plugin, $id, $USER->id);

        if ($transaction) {
            static::delete_records($id);

            $teameval->delete_question($transaction);
        } else {
            throw new \required_capability_exception($teameval->get_context(), 'local/teameval:createquestionnaire', 'nopermissions');
        }
    }
}
