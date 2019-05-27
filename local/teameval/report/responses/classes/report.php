<?php

namespace teamevalreport_responses;

require_once("{$CFG->dirroot}/local/teameval/lib.php");
require_once("{$CFG->libdir}/csvlib.class.php");

use user_picture;
use stdClass;
use csv_export_writer;
use local_teameval\traits;

class report implements \local_teameval\report {

    use traits\report\delegated_export;

    protected $teameval;

    public function __construct(\local_teameval\team_evaluation $teameval) {

        $this->teameval = $teameval;

    }

    public function generate_report() {
        $questions = $this->teameval->get_questions();
        $allgroups = $this->teameval->get_evaluation_context()->all_groups();

        $responses = [];
        $questiontypes = [];

        // this will end up looking like:
        // [ questioninfo => questioninfo, groups => [
        //   groupid => [ group => group, members => [ userid => [user => user, response => response]]
        // ]]

        foreach($questions as $q) {
            $responseinfo = new stdClass;
            $responseinfo->questioninfo = $q;
            $responseinfo->groups = [];
            $responses[] = $responseinfo;
            $questiontypes[$q->type] = $q->type;
        }

        $groupmembers = [];

        foreach($allgroups as $gid => $grp) {
            $groupmembers[$gid] = $this->teameval->group_members($gid);
        }

        foreach($responses as $r) {
            foreach($allgroups as $gid => $grp) {
                $groupinfo = new stdClass;
                $groupinfo->group = $grp;
                $groupinfo->members = [];

                foreach($groupmembers[$gid] as $uid => $user) {
                    $memberinfo = new stdClass;
                    $memberinfo->user = $user;

                    $qi = $r->questioninfo;
                    $resp = $this->teameval->get_response($qi, $uid);
                    $memberinfo->response = $resp;

                    $groupinfo->members[$uid] = $memberinfo;
                }

                $r->groups[] = $groupinfo;
            }
        }

        $filename = 'questions_' . $this->teameval->get_title() . '.csv';
        $downloadlink = $this->teameval->report_download_link('responses', $filename);

        return new output\responses_report($responses, $questiontypes, $downloadlink);
    }

    public function export_questions_csv($filename) {

        $report = $this->generate_report();

        $data = $report->export_for_csv();

        $csv = new csv_export_writer();
        $csv->set_filename($filename);

        foreach($data as $question) {
            $titlerow = [$question->title];
            if (isset($question->max)) {
                $titlerow[] = get_string('maxvalue', 'teamevalreport_responses');
                $titlerow[] = $question->max;
            }
            $csv->add_data($titlerow);
            $csv->add_data([]);
            foreach($question->groups as $group) {
                $csv->add_data(array_merge([$group->name], $group->header));
                foreach($group->rows as $row) {
                    $csv->add_data($row);
                }
                $csv->add_data([]);
            }
            $csv->add_data([]);
            $csv->add_data([]);
        }

        $csv->download_file();

    }


}
