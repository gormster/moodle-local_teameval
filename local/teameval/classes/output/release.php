<?php

namespace local_teameval\output;

use local_teameval;
use stdClass;
use user_picture;

class release implements \renderable, \templatable {

    protected $teameval;

    protected $releases;

    public function __construct($teameval, $releases) {
        $this->teameval = $teameval;
        $this->releases = $releases;
    }

    public function export_for_template(\renderer_base $output) {

        $evalcontext = $this->teameval->get_evaluation_context();

        $groups = $evalcontext->all_groups();
        $scores = $this->teameval->get_evaluator()->scores();

        $released_all = false;
        $released_groups = [];
        $released_users = [];

        foreach($this->releases as $release) {
            if ($release->level == local_teameval\RELEASE_ALL) {
                $released_all = true;
            } else if ($release->level == local_teameval\RELEASE_GROUP) {
                $released_groups[] = $release->target;
            } else if ($release->level == local_teameval\RELEASE_USER) {
                $released_users[] = $release->target;
            }
        }

        $c = new stdClass;
        $c->cmid = $this->teameval->get_coursemodule()->id;
        $c->all = $released_all;
        $c->groups = [];
        $c->releaseallmarksinfo = get_string('releaseallmarkstext', 'local_teameval') .
            $output->help_icon('releaseallmarksinfo', 'local_teameval');

        foreach($groups as $gid => $group) {
            $ggrade = $evalcontext->grade_for_group($gid);

            $g = new stdClass;
            $g->gid = $gid;
            $g->grade = is_null($ggrade) ? '' : $evalcontext->format_grade($ggrade);
            $g->name = $group->name;
            $g->released = in_array($gid, $released_groups);
            $g->overridden = $released_all;

            $g->users = [];

            $users = $this->teameval->group_members($gid);
            foreach($users as $uid => $user) {
                $u = new stdClass;
                $u->id = $uid;
                $u->released = in_array($uid, $released_users);
                $u->name = fullname($user);
                $u->userpic = $output->render(new user_picture($user));
                $u->score = isset($scores[$uid]) ? round($scores[$uid], 2) : '-';
                $u->noncompletionpenalty = round($this->teameval->non_completion_penalty($uid) * 100, 2);
                $u->grade = is_null($ggrade) ? '-' : $evalcontext->format_grade($this->teameval->adjusted_grade($uid, false));
                $u->overridden = ($g->overridden || $g->released);

                $g->users[] = $u;
            }

            $c->groups[] = $g;
        }

        return $c;

    }

}
