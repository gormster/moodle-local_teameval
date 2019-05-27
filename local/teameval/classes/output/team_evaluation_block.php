<?php

namespace local_teameval\output;

use local_teameval\team_evaluation;
use local_teameval\evaluation_context;
use local_teameval\forms;
use core_plugin_manager;
use renderable;
use context_module;
use moodle_exception;

class team_evaluation_block implements renderable {

    public $cm;

    public $context;

    public $disabled;

    public $questionnaire;

    public $teameval;

    public $settings;

    public $addquestion;

    public $results;

    public $release;

    public $feedback;

    public $hiderelease;

    /**
     * @param int $cmid This is the cmid of the activity module this teameval belongs to
     */

    public static function from_cmid($cmid) {
        global $DB;

        // if teameval is not enabled we should just show the button and not load the class
        $enabled = $DB->get_field('teameval', 'enabled', ['cmid' => $cmid]);

        $teameval = null;
        $context = null;
        if ($enabled) {
            $teameval = team_evaluation::from_cmid($cmid);
        } else {
            $cm = get_coursemodule_from_id(null, $cmid);
            $evalcontext = evaluation_context::context_for_module($cm);
            if ($evalcontext->evaluation_permitted()) {
                $context = context_module::instance($cmid);
            }
        }

        return new static($teameval, $context);

    }

    public function __construct($teameval, $context = null) {
        global $USER, $DB;

        // If teameval is not set, we just want to show the big button saying "Start Team Evaluation"
        if ($teameval) {

            $this->teameval = true;
            $this->context = $teameval->get_context();

            if ($context) {
                // If we've been passed a viewing context that's not the same as the teameval context
                // then we need to make sure it is a child context of the teameval context.
                $child = in_array($this->context->id, $context->get_parent_context_ids(true));
                if (!$child) {
                    throw new moodle_exception('contextnotchild', 'local_teameval', '', ['child' => $context->get_context_name(), 'parent' => $this->context->get_context_name()]);
                }
            } else {
                $context = $this->context;
            }

            $evalcontext = $teameval->get_evaluation_context();

            $canview = team_evaluation::check_capability($context, ['local/teameval:viewtemplate']);
            $canchangesettings = team_evaluation::check_capability($this->context, ['local/teameval:changesettings']);
            $cancreate = team_evaluation::check_capability($this->context, ['local/teameval:createquestionnaire']);
            $cansubmit = team_evaluation::check_capability($this->context, ['local/teameval:submitquestionnaire'], ['doanything' => false]);
            $canreview = team_evaluation::check_capability($this->context, ['local/teameval:viewallteams']);
            $canrelease = team_evaluation::check_capability($this->context, ['local/teameval:invalidateassessment']);

            $cm = $teameval->get_coursemodule();

            // If the user can submit and the teameval is not enabled, then hide it from them.
            if ($cm && $cansubmit && ($teameval->enabled == false)) {

                $this->disabled = true;

            // If the user can create questionnaires, then check against null (the general case).
            // Otherwise, we need to hide the questionnaire if evaluation is not currently permitted.
            } else if ($cm && ($evalcontext->evaluation_permitted($cancreate ? null : $USER->id) == false)) {

                $this->disabled = true;

            } else {

                $questiontypes = $teameval->get_question_plugins();
                if ($cancreate) {
                    $this->addquestion = new add_question($teameval, $questiontypes);
                } else if ($canview) {
                    $this->downloadtemplate = new download_template($teameval);
                }

                if ($cancreate || $canview || $cansubmit || $canreview) {
                    $this->questionnaire = new questionnaire($teameval);
                }

                if ($cm) {
                    $this->cm = $cm;

                    // we actually only need to ever change settings in real team evals

                    if ($canchangesettings) {
                        $settings = $teameval->get_settings();
                        $settings->fraction *= 100;
                        $settings->noncompletionpenalty *= 100;
                        $settings->id = $teameval->id;

                        $settingsform = new forms\settings_form();
                        $settingsform->set_data($settings);

                        $this->settings = $settingsform;
                    }

                    if ($canreview) {
                        $reporttypes = core_plugin_manager::instance()->get_plugins_of_type("teamevalreport");
                        $this->results = new results($teameval, $reporttypes);
                    }

                    if ($canrelease) {
                        $releases = $DB->get_records('teameval_release', ['cmid' => $cm->id]);
                        $this->release = new release($teameval, $releases);
                        $this->hiderelease = $teameval->autorelease;
                    }

                    if ($cansubmit) {

                        if ($teameval->marks_available($USER->id)) {
                            $this->feedback = new feedback($teameval, $USER->id); // more than 200ms
                        }

                    }
                }

            }

        } else {

            $this->context = $context;

        }


    }

}
