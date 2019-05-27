<?php

namespace teamevalreport_feedback;

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


use local_teameval;
use local_teameval\team_evaluation;

class external extends external_api {

    public static function update_states_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'cmid of teameval'),
            'states' => new external_multiple_structure(
                new external_single_structure([
                    'questionid' => new external_value(PARAM_INT, 'id of question'),
                    'markerid' => new external_value(PARAM_INT, 'user id of marker'),
                    'targetid' => new external_value(PARAM_INT, 'user id of markee'),
                    'state' => new external_value(PARAM_INT, '-1 for rescind, 0 for unset, 1 for approve')
                ])
            )
        ]);
    }

    public static function update_states_returns() {
        return null;
    }

    public static function update_states($cmid, $states) {
        global $USER, $PAGE;

        team_evaluation::guard_capability(['cmid' => $cmid], ['local/teameval:invalidateassessment'], ['must_exist' => true]);

        $teameval = team_evaluation::from_cmid($cmid);

        $PAGE->set_context($teameval->get_context());

        foreach($states as $s) {
            $teameval->rescind_feedback_for($s['questionid'], $s['markerid'], $s['targetid'], $s['state']);
        }

        // now determine if we should release anyone's marks

        $rescinds = $teameval->all_rescind_states();

        $approves = [];
        foreach ($rescinds as $s) {
            $uid = $s->targetid;
            if (!isset($approves[$uid])) {
                $approves[$uid] = 0;
            }
            // either approve or reject counts
            $approves[$uid]++;
        }

        $qs = $teameval->get_questions();
        $qs = array_filter($qs, function($v) {
            return $v->question->has_feedback();
        });

        $requirednum = count($qs);

        foreach($approves as $uid => $n) {
            $p = count($teameval->teammates($uid));
            if ($n >= $requirednum * $p) {
                $teameval->release_marks_for_user($uid);
            }
        }

    }

}
