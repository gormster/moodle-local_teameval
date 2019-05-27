<?php

use local_teameval\evaluation_context;
use local_teameval\team_evaluation;

class mock_evaluation_context extends evaluation_context {

    public $uservisible = [];

    public $groupgrades = [];

    public function evaluation_permitted($userid = null) {
        if ($userid == null) {
            return true;
        }

        return !empty($this->uservisible[$userid]);
    }

    public function default_deadline() {
        return time();
    }

    public function group_for_user($userid) {
        return current(groups_get_all_groups($this->cm->course, $userid, $this->cm->groupingid));
    }

    public function all_groups() {
        return groups_get_all_groups($this->cm->course, 0, $this->cm->groupingid);
    }

    public function marking_users() {
        static $_markingusers;
        if (!isset($_markingusers)) {
            $_markingusers = get_users_by_capability($this->cm->context, 'local/teameval:submitquestionnaire');
        }
        return $_markingusers;
    }

    public function grade_for_group($groupid) {
        if (isset($this->groupgrades[$groupid])) {
            return $this->groupgrades[$groupid];
        }
        return null;
    }

    public function trigger_grade_update($users = null) {
        // does nothing
    }

    public static function plugin_namespace() {
        return 'mock';
    }

    public static function install_mock($teameval) {
        $evalcontext = new self($teameval->get_coursemodule());

        $reflection = new ReflectionClass(team_evaluation::class);
        $prop = $reflection->getProperty('evalcontext');
        $prop->setAccessible(true);
        $prop->setValue($teameval, $evalcontext);

        $reflection = new ReflectionClass(evaluation_context::class);
        $prop = $reflection->getProperty('teameval');
        $prop->setAccessible(true);
        $prop->setValue($evalcontext, $teameval);

        return $evalcontext;
    }

}
