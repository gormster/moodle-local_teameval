<?php

namespace teamevalquestion_comment;
    
class question implements \local_teameval\question {

    public $id;

    protected $teameval;

    protected $title;

    protected $description;

    protected $anonymous;

    protected $optional;

    public function __construct(\local_teameval\team_evaluation $teameval, $questionid = null) {
        global $DB;

        $this->id               = $questionid;
        $this->teameval         = $teameval;

        if ($questionid > 0) {
            $record = $DB->get_record('teamevalquestion_comment', array("id" => $questionid));

            $this->title            = $record->title;
            $this->description      = $record->description;
            $this->anonymous        = (bool)$record->anonymous;
            $this->optional         = (bool)$record->optional;

        } else {

            // set defaults
            $this->anonymous        = false;
            $this->optional         = false;

        }
    }

    public function submission_view($userid, $locked = false) {
        $context = ['id' => $this->id, 'title' => $this->title, 'description' => $this->description, 'anonymous' => $this->anonymous, 'optional' => $this->optional];
 

        if(has_capability('local/teameval:submitquestionnaire', $this->teameval->get_context(), $userid, false)) {
            $teammates = $this->teameval->teammates($userid);
            $context['users'] = [];

            foreach($teammates as $t) {
                $response = new response($this->teameval, $this, $userid);
                $comment = $response->comment_on($t->id);

                $c = ['userid' => $t->id, 'name' => fullname($t)];
                if (! is_null($comment)) { 
                    $c['comment'] = $comment;
                }
                if ($t->id == $userid) {
                    $c['self'] = true;
                    $c['name'] = get_string('yourself', 'local_teameval');
                }
                $context['users'][] = $c;
            }
            $context['locked'] = $locked;

            if ($locked) {
                $context['incomplete'] = !$response->marks_given();
            }

        } else {
            $context['users'] = [['userid' => 0, 'name' => 'Example User']];
            if ($this->teameval->get_settings()->self) {
                array_unshift($context['users'], ['userid' => $userid, 'name' => get_string('yourself', 'local_teameval'), 'self' => true]);
            }
        }

        return $context;
    }

    public function editing_view() {
        return ['id' => $this->id, 'title' => $this->title, 'description' => $this->description, 'anonymous' => $this->anonymous, 'optional' => $this->optional, 'locked' => $this->any_response_submitted()];
    }

    public function any_response_submitted() {
        global $DB;
        return $DB->record_exists_select('teamevalquestion_comment_res', 'questionid = :questionid AND comment != :emptystring', ['questionid' => $this->id, 'emptystring' => '']);
    }

    public function plugin_name() {
        return 'comment';
    }

    public function has_value() {
        return false;
    }

    public function has_completion() {
        return $this->optional == false;
    }

    public function has_feedback() {
        return true;
    }

    public function is_feedback_anonymous() {
        return $this->anonymous;
    }

    public function minimum_value() {
        return 0;
    }

    public function maximum_value() {
        return 0;
    }

    public function get_title() {
        return $this->title;
    }

    public static function supported_renderer_subtypes() {
        return ['plaintext'];
    }

    public static function delete_questions($ids) {
        global $DB;

        self::reset_userdata($ids);

        $DB->delete_records_list('teamevalquestion_comment', 'id', $ids);
    }

    public static function reset_userdata($ids) {
        global $DB;

        $DB->delete_records_list('teamevalquestion_comment_res', 'questionid', $ids);
    }

    public static function duplicate_question($questionid, $newteameval) {
        global $DB;

        $record = $DB->get_record('teamevalquestion_comment', ['id' => $questionid]);
        unset($record->id);
        $newid = $DB->insert_record('teamevalquestion_comment', $record);

        return $newid;
    }

}