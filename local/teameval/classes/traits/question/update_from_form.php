<?php

namespace local_teameval\traits\question;

use external_function_parameters;
use external_value;

use stdClass;
use coding_exception;
use moodle_exception;

/*

You can use this trait in your external API to allow updating of your question from an ajaxform.

To make your life easier, you should follow this naming convention:

* Your form class should be namespaced under 'forms'
* Your form class should be called 'edit_form' (i.e. \teamevalquestion_PLUGINNAME\forms\edit_form)
* You should declare a hidden field for the question ID called 'id'
* You should declare a hidden field for the question ordinal called 'ordinal'
* You should store your question's settings in a table with the same name as your plugin
  (i.e. teamevalquestion_PLUGINNAME)

Both bundled plugins use this trait, so you can examine them for some ideas.

 */

use local_teameval\team_evaluation;

// You don't have to declare that you implement this interface,
// in fact you kind of can't. But you do have to actually implement
// the methods.
interface update_from_form_interface {
    public static function plugin_name();

    public static function update_record($record, $formdata, $teameval);
}

trait update_from_form {

    // ---
    // Override these methods for customistation
    // ---

    protected static function table_name() {
        $plugin = static::plugin_name();
        return "teamevalquestion_$plugin";
    }

    protected static function form_class() {
        $plugin = static::plugin_name();
        return "\\teamevalquestion_$plugin\\forms\\edit_form";
    }

    protected static function question_id($formdata) {
        return $formdata->id;
    }

    protected static function ordinal($formdata) {
        return $formdata->ordinal;
    }

    protected static function get_record($id) {
        global $DB;
        $tablename = static::table_name();
        $record = ($id > 0) ? $DB->get_record($tablename, array('id' => $id)) : new stdClass;
        return $record;
    }

    protected static function save_record($transaction, $record) {
        global $DB;
        $tablename = static::table_name();
        if ($transaction->id > 0) {
            $DB->update_record($tablename, $record);
        } else {
            $transaction->id = $DB->insert_record($tablename, $record);
        }
    }

    // ---
    // Don't override these methods
    // ---

    public static function update_question_parameters() {
        return new external_function_parameters([
            'teamevalid' => new external_value(PARAM_INT, 'id of teameval'),
            'formdata' => new external_value(PARAM_RAW, 'encoded form data')
        ]);
    }

    public static function update_question_returns() {
        return new external_value(PARAM_INT, 'id of question');
    }

    public static function update_question($teamevalid, $formdata) {
        global $DB, $USER, $PAGE;

        $context = team_evaluation::guard_capability($teamevalid, ['local/teameval:createquestionnaire']);
        $PAGE->set_context($context);

        $teameval = new team_evaluation($teamevalid);

        $parsedformdata = [];
        parse_str($formdata, $parsedformdata);
        $formclass = static::form_class();
        $form = new $formclass(null, null, 'post', '', null, true, $parsedformdata);

        if (!$form->is_submitted()) {
            throw new moodle_exception('formnotsubmitted', '', '', $parsedformdata);
        }

        $data = $form->get_data();

        if (!$data) {
            throw new moodle_exception('forminvalid', '', '', $form->get_errors());
        }

        $id = static::question_id($data);

        $plugin = static::plugin_name();

        $transaction = $teameval->should_update_question($plugin, $id, $USER->id);

        if ($transaction == null) {
            throw new moodle_exception("cannotupdatequestion", "local_teameval");
        }

        $record = static::get_record($id);

        static::update_record($record, $data, $teameval);

        static::save_record($transaction, $record);

        $ordinal = static::ordinal($data);

        $teameval->update_question($transaction, $ordinal);

        return $transaction->id;

    }

}
