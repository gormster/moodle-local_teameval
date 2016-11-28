<?php

namespace local_teameval;

use moodle_exception;

abstract class evaluation_context {

    // cm_info
    protected $cm;

    // team_evaluation
    protected $teameval;

    /**
     * You must call parent::__construct in your constructor.
     */
    public function __construct($cm) {
        $this->cm = $cm;
    }

    /**
     * Should evaluation be shown to this or any user?
     * This default implementation does very basic checking. You MUST override this.
     * You are under no obligation to call parent, however it's not a bad idea.
     * @param type|null $userid If null, check if evaluation is even possible in this context
     * @return bool
     */
    public function evaluation_permitted($userid = null) {
        if ($userid == null) {
            return true; // see? BASIC.
        }

        // I'm going to assume if you even called evaluation_permitted you've established
        // that the user is enrolled in this course.
        $visible = \core_availability\info_module::is_user_visible($this->cm, $userid, false);
        if ($visible == false) {
            return false;
        }

        return true;
    }

    /**
     * What group is this user associated with?
     * @param type $userid User ID
     * @return stdClass groups record
     */
    abstract public function group_for_user($userid);

    /**
     * Every group that might be returned by group_for_user
     * @return type
     */
    abstract public function all_groups();

    /**
     * Which users are marking in this context?
     * @return [int => stdClass] user id to user records
     */
    abstract public function marking_users();

    /**
     * This is never used to calculate grades, just in reports.
     * @param int id of the group in question
     * @return float grade for group
     */
    abstract public function grade_for_group($groupid);

    /**
     * Called when teameval knows that adjusted grades will have changed
     * Teameval is not responsible for making sure that the users specified herein have
     * been assigned grades in your plugin - you have to check that yourself.
     * @param [int] $users optional array of user ids whose grades have changed
     */
    abstract public function trigger_grade_update($users = null);

    /**
     * Override this if your class isn't in your plugin's namespace, or just for
     * performance's sake.
     */
    public static function plugin_namespace() {
        return explode('\\', get_called_class())[0];
    }

    /**
     * Implement this as get_string('modulenameplural', 'yourmodule')
     */
    public static function component_string() {
        $ns = static::plugin_namespace();
        if (strstr($ns, 'mod_')) {
            return get_string('modulenameplural', substr($ns, 4));
        }
        return get_string('pluginname', $ns);
    }

    /**
     * You can implement this function if you feel like you might need to give a specific reason why
     * one of the submitters can see the questionnaire.
     * @codeCoverageIgnore
     */
    public function questionnaire_locked_hint($user) {
        return get_string('lockedhintvisible', 'local_teameval');
    }

    /**
     * You can override this function to customise the appearance of Teameval feedback in the gradebook.
     * TODO make this less awful (use a template)
     * @codeCoverageIgnore
     */
    protected function format_feedback($feedbacks) {
        $o = '<h3>Team Evaluation</h3>';
        foreach($feedbacks as $q) {
            $o .= "<h4>{$q->title}</h4><ul>";
            foreach($q->feedbacks as $fb) {
                $feedback = clean_text($fb->feedback);
                if (isset($fb->from)) {
                    $o .= "<li><strong>{$fb->from}:</strong> $feedback</li>";
                } else {
                    $o .= "<li>$feedback</li>";
                }
            }
            $o .= '</ul>';
        }
        return $o;
    }

    /**
     * Grades aren't always out of 100! If you want to make changes to the way teameval presents
     * grades to your users, you can do it here.
     */
    public function format_grade($grade) {
        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod', 
            'itemmodule' => $this->cm->modname, 
            'iteminstance' => $this->cm->instance, 
            'itemnumber' => 0]);
        if ($gradeitem) {
            return (string)round($grade, $gradeitem->get_decimals());
        }
        return (string)round($grade, 2);
    }







    /*
     * The above methods were teameval calling in to your plugin. 
     * These are methods for you to call into teameval.
     * You should probably not override these, as teameval uses them as well.
     */

    /**
     * Get the actual evaluation context for a given module. Does not create or start team evaluation.
     * @param cm_info $cm The coursemodule object we're interested in
     * @param bool $throw If you don't care if the module doesn't support team evaluation, pass false
     * @return type
     */
    public static function context_for_module($cm, $throw = true) {
        global $CFG;

        $modname = $cm->modname;
        include_once("$CFG->dirroot/mod/$modname/lib.php");

        $function = "{$modname}_get_evaluation_context";
        if (!function_exists($function)) {
            // throw something
            if ($throw) {
                throw new moodle_exception("noevaluationcontext");
            }
            return null;
        }

        return $function($cm);
    }

    public function evaluation_enabled() {
        // This can be called even when evaluation is not possible.
        // For this reason we don't use get_settings()
        global $DB;
        $enabled = $DB->get_field('teameval', 'enabled', ['cmid' => $this->cm->id]);
        return (bool)$enabled;
    }

    /**
     * This will return the teameval object if it exists, but will never create it.
     * @return team_evaluation|null
     */
    public function team_evaluation() {
        if (team_evaluation::exists(null, $this->cm->id)) {
            if (!isset($this->teameval)) {
                $this->teameval = team_evaluation::from_cmid($this->cm->id);
            }
            return $this->teameval;
        }
        return null;
    }

    /**
     * Deprecated. Use team_evaluation() instead.
     * @deprecated
     * @param int $userid 
     * @return bool
     */
    public function marks_available($userid) {
        $teameval = $this->team_evaluation();
        if ($teameval) {
            return $teameval->marks_available($userid);
        }
        return false;
    }

    /**
     * Deprecated. Use team_evaluation() instead.
     * @deprecated
     * @param int $userid 
     * @return float
     */
    public function user_completion($userid) {
        $teameval = $this->team_evaluation();
        if ($teameval) {
            return $teameval->user_completion($userid);
        }
        return 1;
    }

    public function update_grades($grades) {

        // If evaluation isn't permitted or enabled here, don't do anything
        if (!$this->evaluation_permitted() || !$this->evaluation_enabled()) {
            return $grades;
        }

        if (is_object($grades)) {
            $grades = array($grades->userid=>$grades);
        } else if (array_key_exists('userid', $grades)) {
            $grades = array($grades['userid']=>$grades);
        }

        $teameval = $this->team_evaluation();

        foreach($grades as $userid => $grade) {
            if (!is_object($grade)) {
                $grade = (object)$grade;
                $grades[$userid] = $grade;
            }

            if (isset($grade->rawgrade)) {

                if ($this->marks_available($userid)) {
                    $grade->rawgrade *= $teameval->multiplier_for_user($userid);
                    $grade->rawgrade = min(max(0, $grade->rawgrade), 100);
                    $feedbacks = $teameval->all_feedback($userid);
                    if(count($feedbacks)) {
                        if (empty($grade->feedbacks)) {
                            $grade->feedback = "";
                        }
                        $grade->feedback .= $this->format_feedback($feedbacks);
                    }
                } else {
                    $grade->rawgrade = null;
                }

            }
        }

        return $grades;
    }
    

    // COURSE RESET

    /**
     * These are not tested for the fairly simply reason that testing them basically involves rewriting the functions.
     * They don't involved complex logic, just presentation.
     * @codeCoverageIgnore
     */
    public static function reset_course_form_definition(&$mform) {

        $ns = static::plugin_namespace() . '_';

        $mform->addElement('static', $ns.'teameval_hr', '', '<hr />');

        $mform->addElement('checkbox', $ns.'reset_teameval_responses', get_string('resetresponses', 'local_teameval'));

        $mform->addElement('checkbox', $ns.'reset_teameval_questionnaire', get_string('resetquestionnaire', 'local_teameval'));
        $mform->disabledIf($ns.'reset_teameval_questionnaire', $ns.'reset_teameval_responses');

    }

    /**
     * @codeCoverageIgnore
     */
    public static function reset_course_form_defaults() {
        $ns = static::plugin_namespace() . '_';
        return [$ns.'reset_teameval_responses' => 1, $ns.'reset_teameval_questionnaire' => 0];
    }

    public function reset_userdata($options) {
        global $DB;

        $ns = static::plugin_namespace() . '_';

        $resetresponses = $ns . 'reset_teameval_responses';
        $resetresponses = !empty($options->$resetresponses);
        $resetquestionnaire = $ns . 'reset_teameval_questionnaire';
        $resetquestionnaire = !empty($options->$resetquestionnaire);

        $status = [];

        if ($this->evaluation_enabled()) {

            $teameval = $this->team_evaluation();

            if ($resetresponses) {

                $status[] = $teameval->reset_userdata();

                if ($resetquestionnaire) {
                    $status[] = $teameval->delete_questionnaire();
                } else {
                    $teameval->reset_questionnaire();
                }

            }

        }

        return $status;

    }


}
