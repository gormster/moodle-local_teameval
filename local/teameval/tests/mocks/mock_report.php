<?php

class mock_report implements local_teameval\report, renderable {

    public $teameval;

    public $generated = false;

    public function __construct(local_teameval\team_evaluation $teameval) {
        $this->teameval = $teameval;
    }

    public function generate_report() {
        $this->generated = true;
        return $this;
    }

    public function export($filename) {
        header('Content-type: text/plain');
        echo "Test report data";
        exit;
    }

    public static function mock_report_plugininfo($phpunit) {

        $plugininfo = $phpunit->getMockBuilder(\local_teameval\plugininfo\teamevalreport::class)
                              ->setMethods(['get_report_class', 'get_response_class'])
                              ->getMock();

        $plugininfo->type = 'teamevalreport';
        $plugininfo->typerootdir = core_component::get_plugin_types()['teamevalreport'];
        $plugininfo->name = 'mock';
        $plugininfo->rootdir = null; // there is no root directory

        $plugininfo->method('get_report_class')
            ->willReturn('mock_report');

        return $plugininfo;

    }

}