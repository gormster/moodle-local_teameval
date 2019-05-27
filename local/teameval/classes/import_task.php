<?php

namespace local_teameval;

global $CFG;

// because backup doesn't use autoloading...
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/moodle2/restore_plan_builder.class.php');
require_once(dirname(dirname(__FILE__)) . '/backup/moodle2/restore_local_teameval_plugin.class.php');

use restore_local_teameval_plugin;

use backup;
use restore_task;
use restore_controller_dbops;
use restore_drop_and_clean_temp_stuff;
use restore_execution_step;
use restore_structure_step;
use restore_path_element;

use error_log_logger;
use backup_setting;
use base_setting;
use base_plan_exception;

use stdClass;

class import_task extends restore_task {

    protected $teamevalid;

    protected $contextid;

    protected $source;

    protected $importid;

    protected $progress;

    protected $logger;

    public function __construct($name, $teameval, $source, $plan = null) {
        $this->teamevalid = $teameval->id;
        $this->contextid = $teameval->get_context()->id;
        $this->source = $source;

        $this->importid = uniqid('te_import_');
        $this->progress = new \core\progress\none();

        parent::__construct($name, $plan);

        $this->define_settings();

        $this->get_setting('addtoteameval')->set_value($teameval->id);
        $this->get_setting('ordinalbase')->set_value(($teameval->num_questions() > 0) ? $teameval->last_ordinal() + 1 : 0);
    }

    function build() {
        $this->add_step(new create_temp_tables_step('create_clean'));
        $this->add_step(new load_template_step('loadtemplate', $this->source, $this));
        $this->add_step(new import_step('import', 'template.xml'));
        $this->add_step(new restore_drop_and_clean_temp_stuff('drop_clean'));
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

    public function get_restoreid() {
        return $this->importid;
    }

    public function get_progress() {
        return $this->progress;
    }

    // These three functions all get called in the first task
    // but we're not mapping any of this so return zeroes
    public function get_old_courseid() {
        return 0;
    }

    public function get_old_contextid() {
        return 0;
    }

    public function get_old_system_contextid() {
        return 0;
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

        return $CFG->tempdir . '/backup/' . $this->importid;
    }

    public function get_tempdir() {
        return $this->importid;
    }

    public function define_settings() {
        $addtoteameval = new import_setting('addtoteameval', base_setting::IS_INTEGER, 0);
        $this->add_setting($addtoteameval);
        $ordinalbase = new import_setting('ordinalbase', base_setting::IS_INTEGER, 0);
        $this->add_setting($ordinalbase);
    }

    public function get_courseid() {
        return 0;
    }

    // Only return relevant settings
    public function get_setting($name) {
        if (in_array($name, ['addtoteameval', 'ordinalbase'])) {
            return parent::get_setting($name);
        }
        throw new base_plan_exception('setting_by_name_not_found', $name);
    }

}

class import_setting extends backup_setting {}

// we've had to copy this across because the default step wants to do course mapping
// and we could be restoring outside of a course
class create_temp_tables_step extends restore_execution_step {
    protected function define_execution() {
        $exists = restore_controller_dbops::create_restore_temp_tables($this->get_restoreid());
    }
}

class import_step extends restore_structure_step {
    protected function define_structure() {
        $path = new restore_path_element('import', '/template');

        $plugin = new restore_local_teameval_plugin('local', 'teameval', $this);

        $this->prepare_pathelements($plugin->define_plugin_structure($path));

        return [$path];
    }

    public function process_import() {
        // do nothing.
    }
}

class load_template_step extends restore_execution_step {

    protected $file;

    public function __construct($name, $file, $task = null) {
        $this->file = $file;
        parent::__construct($name, $task);
    }

    protected function define_execution() {
        global $CFG;

        $fb = get_file_packer('application/vnd.moodle.backup');
        $result = $fb->extract_to_pathname($this->file, $this->task->get_taskbasepath());
    }

}
