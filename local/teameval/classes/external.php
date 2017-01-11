<?php

namespace local_teameval;

use external_api;
use external_function_parameters;
use external_value;
use external_format_value;
use external_single_structure;
use external_multiple_structure;
use invalid_parameter_exception;
use required_capability_exception;

use stdClass;
use local_searchable\searchable;
use context;
use context_system;
use context_module;
use context_user;

defined('MOODLE_INTERNAL') || die();

class external extends external_api {

    /* turn_on */

    public static function turn_on_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'coursemodule id for the teameval')
        ]);
    }

    public static function turn_on_returns() {
        return null;
    }

    public static function turn_on($cmid) {
        team_evaluation::guard_capability(['cmid' => $cmid], ['local/teameval:changesettings']);
        $teameval = team_evaluation::from_cmid($cmid);
        $settings = new stdClass;
        $settings->enabled = true;
        $teameval->update_settings($settings);
    }

    /* get_settings */

    public static function get_settings_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'coursemodule id for the teameval')
        ]);
    }
    
    public static function get_settings_returns() {
        return new external_single_structure([
            'enabled' => new external_value(PARAM_BOOL, 'is teameval enabled for this module'),
            'public' => new external_value(PARAM_BOOL, 'is the questionnaire for this teameval publicly available'),
            'fraction' => new external_value(PARAM_FLOAT, 'how much does evaluation affect the final grade'),
            'noncompletionpenalty' => new external_value(PARAM_FLOAT, 'how much does non completion of the questionnaire reduce final grade'),
            'deadline' => new external_value(PARAM_INT, 'timestamp - datetime of questionnaire deadline')
        ]);
    }

    public static function get_settings($cmid) {
        //todo: this should 100% go through some kind of output thing. needs permissions checks, for starters.
        $teameval = team_evaluation::from_cmid($cmid);
        return $teameval->get_settings();
    }

    /* update_settings */

    public static function update_settings_parameters() {
        global $PAGE; // we need to set the context to construct a moodleform
        $PAGE->set_context(context_system::instance()); 

        $settingsform = new \local_teameval\forms\settings_form;
        return $settingsform->external_parameters();
    }

    public static function update_settings_returns() {
        global $PAGE;
        $PAGE->set_context(context_system::instance());

        $settingsform = new \local_teameval\forms\settings_form;
        error_log(print_r($settingsform->returns(), true));
        return $settingsform->returns();
    }

    public static function update_settings($form) {
        global $PAGE;
        $PAGE->set_context(context_system::instance());

        $settingsform = new \local_teameval\forms\settings_form();
        $settingsform->process_data($form);
        $settings = $settingsform->get_data();

        team_evaluation::guard_capability($settings->id, ['local/teameval:changesettings']);

        $settings->public = $settings->public ? true : false;
        $settings->enabled = $settings->enabled ? true : false;
        $settings->autorelease = $settings->autorelease ? true : false;

        $settings->fraction = $settings->fraction / 100.0;
        $settings->noncompletionpenalty = $settings->noncompletionpenalty / 100.0;

        $teameval = new team_evaluation($settings->id);
        $settings = $teameval->update_settings($settings);

        return $settings;
    }

    /* questionnaire_set_order */

    public static function questionnaire_set_order_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'coursemodule id for the teameval'),
            'order' => new external_multiple_structure(
                new external_single_structure([
                    'type' => new external_value(PARAM_PLUGIN, 'question type'),
                    'id' => new external_value(PARAM_INT, 'question id')
                ])
            )
        ]);
    }

    public static function questionnaire_set_order_returns() {
        return null;
    }

    public static function questionnaire_set_order($id, $order) {
        team_evaluation::guard_capability($id, ['local/teameval:createquestionnaire']);
        $teameval = new team_evaluation($id);
        $teameval->questionnaire_set_order($order);
    }

    /* report */

    public static function report_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'coursemodule id for the teameval'),
            'plugin' => new external_value(PARAM_PLUGIN, 'report plugin name')
        ]);
    }

    public static function report_returns() {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'rendered HTML code for the report', VALUE_OPTIONAL),
            'template' => new external_value(PARAM_PATH, 'template name to be used for rendering report', VALUE_OPTIONAL),
            'data' => new external_value(PARAM_RAW, 'JSON encoded data to be used for rendering report', VALUE_OPTIONAL)
        ]);
    }

    public static function report($cmid, $plugin) {
        global $USER, $PAGE;

        $context = team_evaluation::guard_capability(['cmid' => $cmid], ['local/teameval:viewallteams'], ['must_exist' => true]);

        $PAGE->set_context($context);

        $teameval = team_evaluation::from_cmid($cmid);
        $teameval->set_report_plugin($plugin);
        $report = $teameval->get_report();

        $renderer = $PAGE->get_renderer("teamevalreport_$plugin");

        // Reports can optionally be templatable. If they are, return just the template and context data.
        if ($report instanceof \templatable) {
            $data = json_encode( $report->export_for_template($renderer) );
            if (method_exists($report, "template_name")) {
                $template = $report->template_name();
            } else {
                $template = "teamevalreport_$plugin/report";
            }

            return ['template' => $template, 'data' => $data];
        } else {
            $html = $renderer->render($report);
            return ['html' => $html];
        }

    }

    /* release */

    public static function release_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'cmid of this team eval'),
            'release' => new external_multiple_structure(
                new external_single_structure([
                    'level' => new external_value(PARAM_INT, 'release level'),
                    'target' => new external_value(PARAM_INT, 'target of release'),
                    'release' => new external_value(PARAM_BOOL, 'set or unset release')
                ])
            )
        ]);
    }

    public static function release_returns() {
        return null;
    }

    public static function release($cmid, $release) {
        team_evaluation::guard_capability(['cmid' => $cmid], ['local/teameval:invalidateassessment'], ['must_exist' => true]);

        $teameval = team_evaluation::from_cmid($cmid);

        foreach($release as $r) {
            $teameval->release_marks_for($r['target'], $r['level'], $r['release']);    
        }
        
    }

    /* get_release */

    public static function get_release_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'cmid of this teameval')
        ]);
    }

    public static function get_release_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'level' => new external_value(PARAM_INT, 'release level'),
                'target' => new external_value(PARAM_INT, 'target of release'),
                'release' => new external_value(PARAM_BOOL, 'set or unset release')
            ])
        );
    }

    public static function get_release($cmid) {
        global $DB;

        team_evaluation::guard_capability(['cmid' => $cmid], ['local/teameval:viewallteams'], ['must_exist' => true]);

        return array_values($DB->get_records('teameval_release', ['cmid' => $cmid]));
    }

    /* template_search */

    public static function template_search_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'id of this teameval'),
            'term' => new external_value(PARAM_RAW, 'search term')
        ]);
    }

    public static function template_search_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'template id'),
                'title' => new external_value(PARAM_RAW, 'template title'),
                'from' => new external_value(PARAM_RAW, 'template location'),
                'tags' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'matching tags')
                ),
                'numqs' => new external_value(PARAM_INT, 'number of questions'),
            ])
        );
    }

    public static function template_search($id, $term) {
        global $DB, $PAGE;

        $context = team_evaluation::guard_capability($id, ['local/teameval:createquestionnaire']);

        $PAGE->set_context($context);

        // now, search!

        $query = str_word_count($term, 1);
        $query = array_map("local_teameval\\team_evaluation::tagify", $query);
        $results = [];

        $offset = 0;

        while(count($results) < 20) {
            $newresults = searchable::results('teameval', $query, true, 20, $offset);
            $offset += count($newresults);

            foreach($newresults as $result) {
                try {
                    team_evaluation::guard_capability($result->objectid, ['local/teameval:viewtemplate'], ['child_context' => $context]);

                    error_log("check passed");

                    $teameval = new team_evaluation($result->objectid);
                    $numqs = $teameval->num_questions();
                    $tags = $result->tags;
                    $results[] = [
                        'title' => $teameval->get_title(),
                        'id' => $teameval->id, 
                        'from' => $teameval->get_context()->get_context_name(), 
                        'tags' => $tags,
                        'numqs' => $numqs
                    ];

                } catch (required_capability_exception $e) {
                    continue;
                } catch (invalid_parameter_exception $e) {
                    continue;
                }
            }

            if (count($newresults) < 20) {
                // we've reached the end of the results
                break;
            }

        }

        return $results;

    }

    /* add_from_template */

    public static function add_from_template_parameters() {
        return new external_function_parameters([
            'from' => new external_value(PARAM_INT, 'id of teameval to add questions from'),
            'to' => new external_value(PARAM_INT, 'id of teameval to add questions to')
        ]);
    }

    public static function add_from_template_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'type' => new external_value(PARAM_PLUGIN, 'question type'),
                'questionid' => new external_value(PARAM_INT, 'question id'),
                'context' => new external_value(PARAM_RAW, 'json encoded context data'),
            ])
        );
    }

    public static function add_from_template($from, $to) {
        global $USER, $PAGE;

        $childcontext = team_evaluation::guard_capability($to, ['local/teameval:createquestionnaire'], ['must_exist' => true]);
        team_evaluation::guard_capability($from, ['local/teameval:viewtemplate'], ['child_context' => $childcontext, 'must_exist' => true]);

        $PAGE->set_context($childcontext);

        $from = new team_evaluation($from);
        $to = new team_evaluation($to);

        if ($to->questionnaire_locked() !== false) {
            throw new invalid_parameter_exception('Questionnaire is locked.');
        }

        $oldquestions = $to->num_questions();

        $to->add_questions_from_template($from);

        $newquestions = array_slice($to->get_questions(), $oldquestions);

        $returns = [];
        foreach($newquestions as $q) {
            $r = new stdClass;
            $r->type = $q->type;
            $r->questionid = $q->questionid;
            $r->context = json_encode($q->question->context_data($PAGE->get_renderer("teamevalquestion_{$q->type}")));
            $returns[] = $r;
        }

        return $returns;

    }

    /* upload_template */

    public static function upload_template_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'team eval id'),
            'file' => new external_value(PARAM_FILE, 'file name'),
            'itemid' => new external_value(PARAM_INT, 'draft file item id')
        ]);
    }

    public static function upload_template_returns() {
        return self::add_from_template_returns();
    }

    public static function upload_template($id, $file, $itemid) {
        global $USER, $PAGE;

        $context = team_evaluation::guard_capability($id, ['local/teameval:createquestionnaire']);

        $PAGE->set_context($context);

        // for reasons I don't totally get the draft files implementation is pretty weak
        // there's no function to just get a named file out of draft files...
        $fs = get_file_storage();
        $usercontext = context_user::instance($USER->id);

        $file = $fs->get_file($usercontext->id, 'user', 'draft', $itemid, '/', $file);

        if (empty($file)) {
            throw new invalid_parameter_exception('File did not upload correctly.');
        }

        $teameval = new team_evaluation($id);

        if ($teameval->questionnaire_locked() !== false) {
            throw new invalid_parameter_exception('Questionnaire is locked.');
        }

        $oldquestions = $teameval->num_questions();

        $teameval->import_questionnaire($file);

        $newquestions = array_slice($teameval->get_questions(), $oldquestions);

        $returns = [];
        foreach($newquestions as $q) {
            $r = new stdClass;
            $r->type = $q->type;
            $r->questionid = $q->questionid;
            $r->context = json_encode($q->question->context_data($PAGE->get_renderer("teamevalquestion_{$q->type}")));
            $returns[] = $r;
        }

        return $returns;

    }


}