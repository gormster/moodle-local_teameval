<?php

use local_teameval\team_evaluation;

function local_teameval_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {

    if ($filearea == 'template') {
        require_login($course);

        team_evaluation::guard_capability($context, ['local/teameval:viewtemplate']);

        if (count($args) != 2) {
            return false;
        }

        list($id, $filename) = $args;

        if (!team_evaluation::exists($id)) {
            return false;
        }

        $teameval = new team_evaluation($id);

        // create the file if it doesn't already exist
        $file = $teameval->export_questionnaire();
        if (!$file) {
            return false;
        }
     
        send_stored_file($file, null, 0, $forcedownload);

    }

    if ($filearea == 'report') {

        require_login($course);

        require_capability('local/teameval:viewallteams', $context);

        if (count($args) != 3) {
            return false;
        }

        list($cmid, $reportplugin, $filename) = $args;

        if (team_evaluation::exists(null, $cmid)) {
            $teameval = team_evaluation::from_cmid($cmid);
            $plugininfo = $teameval->get_report_plugin($reportplugin);
            $cls = $plugininfo->get_report_class();
            $report = new $cls($teameval);
            
            $report->export($filename);
        }

    }

    return false;

}