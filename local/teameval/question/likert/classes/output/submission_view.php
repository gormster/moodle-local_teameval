<?php

namespace teamevalquestion_likert\output;

use teamevalquestion_likert\question;
use teamevalquestion_likert\response;
use local_teameval\team_evaluation;
use renderable;
use templatable;
use stdClass;
use renderer_base;

class submission_view implements renderable, templatable {

    function __construct(question $question, team_evaluation $teameval, $locked = false) {
        $this->question = $question;
        $this->teameval = $teameval;
        $this->locked = $locked;
    }

    function export_for_template(renderer_base $output) {
        global $USER;

        $userid = $USER->id;

        // what I need to end up with:

        // context[id, title, description, users[ord, remaining, name], options[value, meaning, users[userid, name, checked]]]

        $context = ["id" => $this->question->id, "title" => $this->question->title, "description" => $this->question->description, "self" => $this->teameval->self];

        $options = [];
        for ($i=$this->question->minval; $i <= $this->question->maxval; $i++) {
            $o = ["value" => $i];
            if (isset($this->question->meanings->$i)) {
                $meaning = $this->question->meanings->$i;
                $o["meaning"] = $meaning;
            }
            $options[] = $o;
        }

        $grid = $this->fits_in_grid($options);

        $context['waterfall'] = !$grid;
        $context['grid'] = $grid;

        // if the user can respond to this teameval
        if (has_capability('local/teameval:submitquestionnaire', $this->teameval->get_context(), $userid, false)) {
            // get any response this user has given already
            $response = new response($this->teameval, $this->question, $userid);
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

            $context['locked'] = $this->locked;
            if ($this->locked) {
                $context['incomplete'] = !$response->marks_given();
            }


        } else {
            $context['demo'] = true;

            $opts = [];

            if ($this->teameval->self) {
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
                    foreach ($options as $o2) {
                        $yourself["options"][] = ["value" => $o2['value'], "checked" => false];
                        $user["options"][] = ["value" => $o2['value'], "checked" => false];
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

                foreach ($options as $o) {

                    $o["users"] = [
                        [
                            "name" => "Example user",
                            "userid" => 0,
                            "checked" => false
                        ]
                    ];

                    $opts[] = $o;

                    $user = ["name" => "Example user", "userid" => 0];
                    foreach ($options as $o2) {
                        $user["options"][] = ["value" => $o2['value'], "checked" => false];
                    }
                    $context['users'] = [$user];
                }
            }

            $context['options'] = $opts;
            $context['optionwidth'] = 100 / count($opts);

        }

        return $context;
    }

    private function fits_in_grid($options) {

        $totalstrlen = 0;
        $maxstrlen = 0;
        $maxwordlen = 0;

        foreach ($options as $o) {
            if (!empty($o['meaning'])) {
                $meaning = $o['meaning'];
                $totalstrlen += strlen($meaning);
                $maxstrlen = max($maxstrlen, strlen($meaning));
                $longestword = array_reduce(str_word_count($meaning, 1), function($v, $p) {
                    return strlen($v) > strlen($p) ? $v : $p;
                });
                $maxwordlen = max($maxwordlen, strlen($longestword));
            }
        }

        $grid = true;

        if ($totalstrlen > 150) {
            $grid = false;
        } else if ($maxstrlen > ((12 - count($options)) * 6) + 15) {
            $grid = false;
        } else if ($maxwordlen > ((12 - count($options)) * 2) + 3) {
            $grid = false;
        }

        return $grid;

    }

}
