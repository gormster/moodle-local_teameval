<?php

namespace local_teameval;

interface report {

    public function __construct(team_evaluation $teameval);

    /**
     * Generate and return a renderable report.
     * @return type
     */
    public function generate_report();

    /**
     * Generate and print a report with the given filename. Use csv_export_writer::download_file or similar.
     * @param string $filename The filename of a given report. You might want to vary your report based on the filename.
     * @return false if the report is not valid; otherwise do not return: this function must exit.
     */
    public function export($filename);

}
