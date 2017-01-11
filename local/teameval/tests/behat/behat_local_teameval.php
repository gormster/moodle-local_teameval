<?php

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/lib/behat/behat_base.php');
require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/group/lib.php');

use Behat\Gherkin\Node\TableNode as TableNode;
use Behat\Mink\Exception\DriverException as DriverException;

use local_teameval\team_evaluation;

// todo: should this be a subclass of behat_data_generator?
class behat_local_teameval extends behat_base {

    private function user_record($fullname) {
        $names = explode(' ', $fullname);
        if (count($names) == 1) {
            $first = $fullname;
            $last = 'Example';
        } else {
            $first = implode(' ', array_slice($names, 0, floor(count($names) / 2)));
            $last = implode(' ', array_slice($names, floor(count($names) / 2)));
        }
        $username = strtolower(implode('', $names));
        $r = [$username, $first, $last, $username . "@example.com"];
        return $r;
    }

    private function get_cm_by_idnumber($course, $idnumber) {
        global $DB;

        if (!$id = $DB->get_field('course', 'id', array('shortname' => $course))) {
            throw new Exception('The specified course with shortname "' . $shortname . '" does not exist');
        }

        if (!$cm = $DB->get_record('course_modules', array('idnumber' => $idnumber))) {
            throw new Exception('The specified course module with idnumber "' . $idnumber . '" does not exist');
        }

        return $cm;
    }

    private function get_suboptions($prefix, $options, $defaults = []) {
        $suboptions = $defaults;
        foreach ($options as $key => $value) {
            if (substr($key, 0, strlen("$prefix ")) == "$prefix ") {
                $suboptions[substr($key, strlen("$prefix "))] = $value;
            }
        }
        return $suboptions;
    }

    /**
     * Creates an assignment with team evaluation with a given set of users
     * Required fields:
     * Course (string) name of course
     * Course shortname (string) shortname of course
     * Groups (int) the number of groups
     * Students per group (int) the number of students per group
     *
     * Optional fields:
     * Teacher (string) full name of the teacher
     * Group name format (string) format for group names
     * Student name format (string) format for student names
     * Assignment (string) an existing assignment
     * Assignment [anything] (mixed) options for the assignment
     * Teameval [anything] (mixed) options for the teameval
     *
     * @Shortcuts start_team_evaluation.feature
     * @Given /^the following team evaluation exists:$/
     * @param TableNode $data
     */
    public function the_following_teameval_exists($data) {
        $options = $data->getRowsHash();

        // lowercase all the keys
        // of course there is a built in for this. PHP.
        $options = array_change_key_case($options);

        $required = ['course', 'course shortname', 'groups', 'students per group'];
        foreach ($required as $r) {
            if (!isset($options[$r])) {
                throw new DriverException($r . ' is a required key');
            }
        }

        $optional = [
            'group name format' => 'Group #',
            'student name format' => 'Student #',
            'teacher' => 'Teacher 1'
        ];

        foreach ($optional as $key => $default) {
            if (!isset($options[$key])) {
                $options[$key] = $default;
            }
        }

        $teacher = $options['teacher'];
        $course = $options['course'];
        $shortcourse = $options['course shortname'];
        $ngroups = (int)$options['groups'];
        $npergroup = (int)$options['students per group'];
        $assignment = null;
        $groupformat = $options['group name format'];
        $studentformat = $options['student name format'];

        if (isset($options['assignment'])) {
            $assignment = $options['assignment'];
        }

        $groups = [['idnumber', 'course']];
        $groupmembers = [['user', 'group']];
        $users = [['username', 'firstname', 'lastname', 'email']];
        $enrolments = [['user', 'course', 'role']];

        $teacheruser = $this->user_record($teacher);
        $users[] = $teacheruser;
        $enrolments[] = [$teacheruser[0], $shortcourse, 'editingteacher'];

        for ($i=0; $i < $ngroups; $i++) {
            $groupname = groups_parse_name($groupformat, $i);
            $groups[] = [$groupname, $shortcourse];

            for ($j=0; $j < $npergroup; $j++) {
                $fullname = groups_parse_name($studentformat, $i * $npergroup + $j);
                $user = $this->user_record($fullname);
                $users[] = $user;
                $enrolments[] = [$user[0], $shortcourse, 'student'];
                $groupmembers[] = [$user[0], $groupname];
            }
        }

        $this->execute('behat_data_generators::the_following_exist', ['users', new TableNode($users)]);
        $this->execute('behat_data_generators::the_following_exist', ['course enrolments', new TableNode($enrolments)]);
        $this->execute('behat_data_generators::the_following_exist', ['groups', new TableNode($groups)]);
        $this->execute('behat_data_generators::the_following_exist', ['group members', new TableNode($groupmembers)]);

        if (empty($assignment)) {
            // create the assignment
            $assignmentdefaults = [
                'activity' => 'assign',
                'course' => $shortcourse,
                'idnumber' => 'assign1',
                'name' => 'Test Teameval Assignment',
                'intro' => 'Description for '.$assignment,
                'assignsubmission_onlinetext_enabled' => 1,
                'assignsubmission_file_enabled' => 0,
                'teamsubmission' => 1,
                'groupmode' => 1
            ];
            $assignmentdata = $this->get_suboptions('assignment', $options, $assignmentdefaults);

            $assignment = $assignmentdata['name'];

            $this->execute('behat_data_generators::the_following_exist', ['activities', new TableNode([array_keys($assignmentdata), array_values($assignmentdata)])]);
        }

        // TODO: replace this with team_evaluation::from_cmid? is that even possible?
        $cm = $this->get_cm_by_idnumber($shortcourse, $assignmentdata['idnumber']);
        $teameval = team_evaluation::from_cmid($cm->id);

        $teamevaloptions = (object)$this->get_suboptions('teameval', $options);
        if (!empty($teamevaloptions)) {
            $teameval->update_settings($teamevaloptions);
        }

    }

}