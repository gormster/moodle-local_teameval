<?php

namespace local_teameval;

global $CFG;

// because backup doesn't use autoloading...
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
require_once(dirname(dirname(__FILE__)) . '/backup/moodle2/backup_local_teameval_plugin.class.php');

use backup;
use backup_task;
use backup_nested_element;
use backup_optigroup;
use backup_local_teameval_plugin;
use backup_structure_step;
use backup_execution_step;
use create_and_clean_temp_stuff;
use create_taskbasepath_directory;
use backup_zip_contents;
use drop_and_clean_temp_stuff;
use error_log_logger;
use base_plan_exception;

use stdClass;

class export_task extends backup_task {

    protected $teamevalid;

    protected $contextid;

    protected $destination;

    protected $exportid;

    public $file;

    protected $progress;

    protected $logger;

    public function __construct($name, $teamevalid, $contextid, $destination, $plan = null) {
        $this->teamevalid = $teamevalid;
        $this->contextid = $contextid;
        $this->destination = $destination;

        $this->exportid = uniqid('te_export_');
        $this->progress = new \core\progress\none();

        parent::__construct($name, $plan);
    }

    function build() {
        $this->add_step(new create_and_clean_temp_stuff('clean'));
        $this->add_step(new create_taskbasepath_directory('create_template_directory'));
        $this->add_step(new export_step('export', 'template.xml'));
        $this->add_step(new save_to_file_storage_step('save'));
        $this->add_step(new drop_and_clean_temp_stuff('drop'));
        $this->built = true;
    }

    public function get_teamevalid() {
        return $this->teamevalid;
    }

    public function get_contextid() {
        return $this->contextid;
    }

    public function get_destination() {
        return $this->destination;
    }

    public function get_backupid() {
        return $this->exportid;
    }

    public function get_progress() {
        return $this->progress;
    }

    public function get_logger() {
        if (empty($this->logger)) {
            $this->logger = new error_log_logger(backup::LOG_DEBUG);
        }
        return $this->logger;
    }

    // wish there was a better place to do this, but the built-in helper classes
    // have it hard wired as /backup/.
    public function get_basepath() {
        global $CFG;

        return $CFG->tempdir . '/backup/' . $this->exportid;
    }

    public function define_settings() {
        // do nothing
    }

    // this function has to return something
    // but it's only used by file.php links which basically no longer exist
    // so... ignore it
    public function get_courseid() {
        return 0;
    }

    // This task doesn't have any settings.
    public function get_setting($name) {
        throw new base_plan_exception('setting_by_name_not_found', $name);
    }

}

class export_step extends backup_structure_step {

    protected function define_structure() {
        $root = new backup_nested_element('template', ['id']);
        $optigroup = new backup_optigroup('local_teameval_plugin');
        $root->add_child($optigroup);

        $source = new stdClass;
        $source->id = $this->task->get_teamevalid();

        $root->set_source_array([$source]);

        $plugin = new backup_local_teameval_plugin('local', 'teameval', $optigroup, $this);
        $plugin->define_plugin_structure('template');

        return $root;
    }

}

class save_to_file_storage_step extends backup_execution_step {

    protected function define_execution() {

        $zippacker = get_file_packer('application/vnd.moodle.backup');

        $this->task->file = $zippacker->archive_to_storage(
            ['template.xml' => $this->task->get_basepath() . '/template.xml'],
            $this->task->get_contextid(),
            'local_teameval',
            'template',
            $this->task->get_teamevalid(),
            '/',
            $this->task->get_destination()
            );

    }

}
