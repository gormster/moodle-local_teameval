<?php

namespace block_teameval_templates;

use external_api;
use external_function_parameters;
use external_value;
use invalid_parameter_exception;
use moodle_exception;

use local_teameval;
use local_teameval\team_evaluation;
use local_teameval\evaluation_context;
use stdClass;

class external extends external_api {

    public static function update_title_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'id of team eval'),
            'title' => new external_value(PARAM_RAW, 'title for team eval template')
        ]);
    }

    public static function update_title_returns() {
        return new external_value(PARAM_RAW, 'title after validation');
    }

    public static function update_title($id, $title) {
        global $PAGE;

        $context = team_evaluation::guard_capability($id, ['local/teameval:createquestionnaire']);
        $PAGE->set_context($context);

        $teameval = new team_evaluation($id);

        $r = new stdClass;
        $r->title = $title;
        $settings = $teameval->update_settings($r);

        return $settings->title;
    }

    /* delete_template */

    public static function delete_template_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'id of team eval template')
        ]);
    }

    public static function delete_template_returns() {
        return null;
    }

    public static function delete_template($id) {
        require_login();

        if (team_evaluation::exists($id)) {

            $teameval = new team_evaluation($id);

            if ($teameval->get_coursemodule()) {
                // can't delete non-template teamevals
                return;
            }

            if ($teameval->num_questions() == 0) {
                team_evaluation::delete_teameval($id);
            } else {
                if (has_capability('block/teameval_templates:deletetemplate', $teameval->get_context())) {
                    team_evaluation::delete_teameval($id);
                } else {
                    throw new moodle_exception('cantdeletequestions', 'block_teameval_templates');
                }
            }

        }

    }

    /* add_to_module */

    public static function add_to_module_parameters() {
        return new external_function_parameters([
            'from' => new external_value(PARAM_INT, 'id of teameval to add questions from'),
            'to' => new external_value(PARAM_INT, 'cmid of activity to add questions to')
        ]);
    }

    public static function add_to_module_returns() {
        return new external_value(PARAM_URL, 'url to redirect to');
    }

    public static function add_to_module($from, $to) {

        $context = team_evaluation::guard_capability(['cmid' => $to], ['local/teameval:createquestionnaire']);
        team_evaluation::guard_capability($from, ['local/teameval:viewtemplate'], ['child_context' => $context]);

        $cm = get_course_and_cm_from_cmid($to)[1];
        $evalcontext = evaluation_context::context_for_module($cm);

        if ($evalcontext->evaluation_permitted() == false) {
            throw new invalid_parameter_exception("Activity does not support evaluation");
        }

        $from = new team_evaluation($from);

        // We might be about to enabled evaluation on this activity, but we can still fail initialisation
        // if the questionnaire will be immediately locked upon evaluation start. In that case, we should
        // set evaluation back to disabled.
        // Check what the enabled setting is now, and make sure to set it back if we fail.
        $enabled = $evalcontext->evaluation_enabled();
        $created = false;

        $to = team_evaluation::from_cmid($to);

        // If we've gone from disabled to enabled, temporarily set it back to disabled
        // (This can pretty much only happen when we're creating a new team evaluation)
        if (($enabled == false) && ($to->enabled == true)) {
            $created = true;
            $to->update_settings(['enabled' => false]);
        }

        $locked = $to->questionnaire_locked();
        if ($locked) {
            list($reason, $user) = $locked;
            $reason = team_evaluation::questionnaire_locked_reason($reason);
            throw new moodle_exception('questionnairelocked', 'local_teameval', null, $reason);
        }

        // actually do the thing
        $to->add_questions_from_template($from);

        // If we changed this value earlier, change it back
        if ($created) {
            $to->update_settings(['enabled' => true]);
        }

        $redirect = $cm->url;
        $redirect->set_anchor("team_evaluation");

        return $redirect->out();

    }

}
