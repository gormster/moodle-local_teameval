<?php

require_once(__DIR__ . '/../../config.php');

global $CFG, $OUTPUT, $PAGE, $USER;

require_once($CFG->dirroot . '/local/teameval/lib.php');

use local_teameval\team_evaluation;
use block_teameval_templates\output;

$id = optional_param('id', 0, PARAM_INT);
$contextid = optional_param('contextid', 0, PARAM_INT);

if (($id == 0) && ($contextid == 0)) {
    print_error('missingparam', '', '', 'id or contextid');
}

if ($id == 0) {
    // make a new teameval template
    // you'll need createquestionnaire in this context
    team_evaluation::guard_capability(['contextid' => $contextid], ['local/teameval:createquestionnaire']);
    $teameval = team_evaluation::new_with_contextid($contextid);
    $url = new moodle_url("/blocks/teameval_templates/template.php", ['id' => $teameval->id]);
    $url->remove_params('contextid');
    redirect($url);
}

$teameval = new team_evaluation($id);
if (!is_null($teameval->get_coursemodule())) {
    print_error('notatemplate', 'block_teameval_templates');
}

if ($contextid == 0) {
    $context = $teameval->get_context();
} else {
    // if we're setting a specific context, it must be a child context
    // of the template context
    $context = context::instance_by_id($contextid);
    $parents = $context->get_parent_context_ids(true);
    if (!in_array($teameval->get_context()->id, $parents)) {
        print_error('notaccessible', 'block_teameval_templates', $context->get_url(), $context->get_context_name());
    }
}

$title = $teameval->title;

// Set up the page.
$url = new moodle_url("/blocks/teameval_templates/template.php");
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('standard');

$coursecontext = $context->get_course_context(false);

$courseid = null;
if ($coursecontext) {
    $courseid = $coursecontext->instanceid;
}

$cmid = null;
if ($context->contextlevel == CONTEXT_MODULE) {
    $cmid = $context->instanceid;
    $cm = get_fast_modinfo($courseid)->get_cm($cmid);
    $PAGE->set_cm($cm);
}

if ($context->contextlevel == CONTEXT_COURSECAT) {
    $node = $PAGE->navigation->find($context->instanceid, navigation_node::TYPE_CATEGORY);
    if ($node) {
        $node->make_active();
    }
}

require_login($courseid);

// We're using $context here, because you actually only need the ability in any CHILD context of the context
// In other words, any course in a category, any module in a course, etc.
team_evaluation::guard_capability($context, ['local/teameval:viewtemplate']);

$PAGE->navbar->add($title);

$output = $PAGE->get_renderer('block_teameval_templates');
echo $output->header();

$title = new output\title($teameval);
echo $output->render($title);

$teameval_renderer = $PAGE->get_renderer('local_teameval');
$teameval_block = new \local_teameval\output\team_evaluation_block($teameval, $context);

echo $teameval_renderer->render($teameval_block);


// If we've set a course ID, then we might want to add these questions to a course modules
if ($courseid) {
    $addtomodule = new output\addtomodule($teameval, $courseid, $cmid);
    if (!$addtomodule->is_empty()) {
        echo $output->render($addtomodule);
    }
}

// If we're a bigshot user capable of deletion OR
// if we've just made this template and it still has no questions

if (team_evaluation::check_capability($teameval->get_context(), ['block/teameval_templates:deletetemplate']) ||
    ($teameval->num_questions() == 0)) {
    $deletebutton = new output\deletebutton($teameval);
    echo $output->render($deletebutton);
}

echo $output->footer();
