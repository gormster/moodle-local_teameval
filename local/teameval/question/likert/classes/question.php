<?php

namespace teamevalquestion_likert;

use coding_exception;
    
class question implements \local_teameval\question {
    
    public $id;
    
    protected $teameval;
    protected $title;
    protected $description;
    protected $minval;
    protected $maxval;

    public function __construct(\local_teameval\team_evaluation $teameval, $questionid = null) {
        global $DB;

        $this->id               = $questionid;
        $this->teameval         = $teameval;

        if ($questionid > 0) {
            $record = $DB->get_record('teamevalquestion_likert', array("id" => $questionid));

            $this->title            = $record->title;
            $this->description      = $record->description;
            $this->minval           = $record->minval;
            $this->maxval           = $record->maxval;
            $this->meanings         = json_decode($record->meanings);
        }
    }
    
    public function submission_view($userid, $locked = false) {
        global $DB;

        // what I need to end up with:

        // context[id, title, description, users[ord, remaining, name], options[value, meaning, users[userid, name, checked]]]

        $context = ["id" => $this->id, "title" => $this->title, "description" => $this->description, "self" => $this->teameval->get_settings()->self];

        $options = [];
        $totalstrlen = 0;
        $maxstrlen = 0;
        $maxwordlen = 0;

        for ($i=$this->minval; $i <= $this->maxval; $i++) { 
            $o = ["value" => $i];
            if (isset($this->meanings->$i)) {
                $meaning = $this->meanings->$i;
                $o["meaning"] = $meaning;
                $totalstrlen += strlen($meaning);
                $maxstrlen = max($maxstrlen, strlen($meaning));
                $longestword = array_reduce(str_word_count($meaning, 1), function($v, $p) {
                    return strlen($v) > strlen($p) ? $v : $p;
                });
                $maxwordlen = max($maxwordlen, strlen($longestword));
            }
            $options[] = $o;
        }

        $grid = true;

        if ($totalstrlen > 150) {
            $grid = false;
        } else if ($maxstrlen > ((12 - count($options)) * 6) + 15) {
            $grid = false;
        } else if ($maxwordlen > ((12 - count($options)) * 2) + 3) {
            $grid = false;
        }

        $context['waterfall'] = !$grid;
        $context['grid'] = $grid;

        // if the user can respond to this teameval
        if (has_capability('local/teameval:submitquestionnaire', $this->teameval->get_context(), $userid, false)) {
            // get any response this user has given already
            $response = new response($this->teameval, $this, $userid);
            $marks = $response->raw_marks();
            
            $members = $this->teameval->teammates($userid);

            $ord = 0;

            $headers = [];
            $previous = [];

            $users = [];

            foreach ($members as $user) {
                $opts = [];
                $ord++;

                $fullname = $user->id == $userid ? get_string('yourself', 'local_teameval') : fullname($user);

                $headers[] = [
                    "ord" => $ord,
                    "remaining" => count($members) - $ord + 1,
                    "name" => $fullname,
                    "previous" => $previous
                ];

                $previous[] = $fullname;

                // set user options for grid format

                foreach($options as $o) {
                    if (isset($marks[$user->id])) {
                        $mark = $marks[$user->id];
                        if ($o['value'] == $mark) { $o['checked'] = true; }
                    }
                    $opts[] = $o;
                }

                $c = [
                    "name" => fullname($user),
                    "userid" => $user->id,
                    "options" => $opts
                ];

                if ($user->id == $userid) {
                    $c['self'] = true;
                    $c['name'] = get_string('yourself', 'local_teameval');
                }

                $users[] = $c;
            }

            $context['headers'] = $headers;
            $context['users'] = $users;

            $opts = [];
            foreach($options as $o) {
                
                $users = [];
                foreach($members as $markeduser) {
                    $u = [
                        "userid" => $markeduser->id,
                        "name" => fullname($markeduser)
                    ];
                    if ($markeduser->id == $userid) {
                        $u["name"] = get_string('yourself', 'local_teameval');
                    }

                    if (isset($marks[$markeduser->id])) {
                        $mark = $marks[$markeduser->id];
                        $u['checked'] = ($o['value'] == $mark);
                    }    
                    $users[] = $u;
                }
                $o['users'] = $users;

                $opts[] = $o;
            }

            $context['options'] = $opts;
            $context['optionwidth'] = 100 / count($opts);

            $context['locked'] = $locked;
            if ($locked) {
                $context['incomplete'] = !$response->marks_given();
            }


        } else {
            $context['demo'] = true;

            $opts = [];

            if ($this->teameval->get_settings()->self) {
                $context['headers'] = [
                    [
                        "ord" => 1,
                        "remaining" => 2,
                        "name" => "Yourself",
                        "previous" => []
                    ],
                    [
                        "ord" => 2,
                        "remaining" => 1,
                        "name" => "Example user",
                        "previous" => ["Yourself"]
                    ]
                ];

                foreach ($options as $o) {
                $o["users"] = [
                    [
                        "name" => "Yourself",
                        "userid" => $userid,
                        "checked" => false
                    ],
                    [
                        "name" => "Example user",
                        "userid" => 0,
                        "checked" => false
                    ]

                ];
                $opts[] = $o;

                $yourself = ["name" => "Yourself", "userid" => -1];
                $user = ["name" => "Example user", "userid" => 0];
                foreach ($options as $o) {
                    $yourself["options"][] = ["value" => $o['value'], "checked" => false];
                    $user["options"][] = ["value" => $o['value'], "checked" => false];
                }
                $context['users'] = [$yourself, $user];
            }

            } else {
                $context['headers'] = [
                    [
                        "ord" => 1,
                        "remaining" => 1,
                        "name" => "Example user",
                        "previous" => []
                    ]
                ];

                $o["users"] = [
                    [
                        "name" => "Example user",
                        "userid" => 0,
                        "checked" => false
                    ]
                ];

                $user = ["name" => "Example user", "userid" => 0];
                foreach ($options as $o) {
                    $user["options"][] = ["value" => $o['value'], "checked" => false];
                }
                $context['users'] = [$user];
            }
            
            $context['options'] = $opts;
            $context['optionwidth'] = 100 / count($opts);
            

        }

        return $context;
    }
    
    public function editing_view() {
        $context = ["id" => $this->id, "title" => $this->title, "description" => $this->description, "minval" => $this->minval, "maxval" => $this->maxval];

        $meanings = [];
        for ($i=$this->minval; $i <= $this->maxval; $i++) { 
            $o = ["value" => $i];
            if (isset($this->meanings->$i)) {
                $o["meaning"] = $this->meanings->$i;
            }
            $meanings[] = $o;
        }

        $context['meanings'] = $meanings;

        if ($this->any_response_submitted()) {
            $context['locked'] = true;
        }

        return $context;
    }

    public function any_response_submitted() {
        global $DB;
        return $DB->record_exists('teamevalquestion_likert_resp', ['questionid' => $this->id]);
    }

    public function plugin_name() {
        return 'likert';
    }

    public function has_value() {
        return true;
    }

    public function has_completion() {
        return true;
    }

    public function minimum_value() {
        return 0; // even if $minval == 1, return 0; it's what users expect
    }

    public function maximum_value() {
        return $this->maxval;
    }
    
    public function get_title() {
        return $this->title;
    }

    public function has_feedback() {
        return false;
    }

    public function is_feedback_anonymous() {
        return false;
    }

    public static function supported_renderer_subtypes() {
        return ['csv', 'plaintext'];
    }

    public static function duplicate_question($questionid, $newteameval) {
        global $DB;

        $record = $DB->get_record('teamevalquestion_likert', ['id' => $questionid]);
        unset($record->id);
        $newid = $DB->insert_record('teamevalquestion_likert', $record);

        return $newid;
    }

    public static function delete_questions($ids) {
        global $DB;

        self::reset_userdata($ids);

        $DB->delete_records_list('teamevalquestion_likert', 'id', $ids);
    }

    public static function reset_userdata($ids) {
        global $DB;

        $DB->delete_records_list('teamevalquestion_likert_resp', 'questionid', $ids);
    }
    
}