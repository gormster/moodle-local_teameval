<?php

namespace mod_assign;

class evaluation_context extends \local_teameval\evaluation_context {
    
    protected $assign;

    public function __construct(\assign $assign) {
        $this->assign = $assign;
        parent::__construct($assign->get_course_module());
    }

    public function evaluation_permitted($userid = null) {
        $enabled = $this->assign->get_instance()->teamsubmission;
        if ($userid) {
            if ($this->assign->is_any_submission_plugin_enabled()) {
                $groupsub = $this->assign->get_group_submission($userid, 0, false);
                if (($groupsub == false) || 
                    ($groupsub->status != ASSIGN_SUBMISSION_STATUS_SUBMITTED) ||
                    ($this->assign->submission_empty($groupsub))) {
                    $enabled = false;
                }
            } else {
                $grade = $this->assign->get_user_grade($userid, false);
            if (!($grade && $grade->grade !== null && $grade->grade >= 0)) {
                $enabled = false;
            }
            }
        }
        return $enabled;

    }

    public function group_for_user($userid) {
        return $this->assign->get_submission_group($userid);
    }

    public function all_groups() {
        $grouping = $this->assign->get_instance()->teamsubmissiongroupingid;
        $groups = groups_get_all_groups($this->assign->get_course()->id, 0, $grouping);
        return $groups;
    }

    public function marking_users($fields = 'u.id') {
        $grouping = $this->assign->get_instance()->teamsubmissiongroupingid;
        
        $groups = groups_get_all_groups($this->assign->get_course()->id, 0, $grouping, 'g.id');

        // we want only group IDs
        $groups = array_keys($groups);

        $ctx = $this->assign->get_context();

        return get_users_by_capability($ctx, 'local/teameval:submitquestionnaire', $fields, '', '', '', $groups);
    }

    public function grade_for_group($groupid) {
        //TODO: you can actually assign different grades for everyone
        //check if that has happened

        // get any user from this group
        $mems = groups_get_members($groupid, 'u.id');
        $user = key($mems);

        if ($user > 0) {
            $grade = $this->assign->get_user_grade($user, false);
            if ($grade) {
                return $grade->grade;
            }
        }

        return null;
    }

    public function trigger_grade_update($users = null) {
        global $DB;

        if (is_null($users)) {
            $users = array_keys($this->assign->list_participants(0, true));
        }

        foreach($users as $u) {
            $grade = $this->assign->get_user_grade($u, false);
            if ($grade) {
                $this->assign->update_grade($grade);
            }
        }
    }

}