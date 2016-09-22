<?php
    
namespace local_teameval\output;

use plugin_renderer_base;
use local_teameval\forms;
use stdClass;
use moodle_url;
use file_picker;

// TODO
// Are we supposed to check permissions here? It feels right, because this is the last
// stop before output, but it also feels like a violation of principles. Not sure.

class renderer extends plugin_renderer_base {

    public function render_team_evaluation_block(team_evaluation_block $block) {
        
        global $PAGE, $USER;

        $context = $block->context;

        if (empty($block->teameval)) {

            // Teameval isn't enabled and can't be enabled, so return nothing
            if (empty($block->context)) {
                return '';
            }
            if (team_evaluation::check_capability($context, ['local/teameval:changesettings'])) {
                return $this->render_from_template('local_teameval/turn_on', ['cmid' => $context->instanceid]);
            }

            // If you can't turn it on, don't show anything.
            return '';

        }

        if ($block->disabled) {

            if (team_evaluation::check_capability($context, ['local/teameval:changesettings'])) {
                return $this->render_from_template('local_teameval/disabled', []);
            }

            return '';

        }
        
        $c = new stdClass; // template context

        if (isset($block->settings)) {
            $c->settings = $this->render_from_template('local_teameval/settings', ['form' => $block->settings->render()]);
        }

        if (isset($block->addquestion)) {
            $c->addquestion = $this->render($block->addquestion);
        } else if (isset($block->downloadtemplate)) {
            $c->addquestion = $this->render($block->downloadtemplate);
        }

        if (isset($block->results)) {
            $c->results = $this->render($block->results);
        }

        if (isset($block->release)) {
            $c->release = $this->render($block->release);
        }

        if (isset($block->feedback)) {
            $c->feedback = $this->render($block->feedback);
        }

        $c->questionnaire = $this->render($block->questionnaire);

        $c->hiderelease = $block->hiderelease;
        
        if (\local_teameval\is_developer()) {
            $PAGE->requires->js_call_amd('local_teameval/developer', 'initialise');
        }

        $PAGE->requires->js_call_amd('local_teameval/tabs', 'initialise');
        return $this->render_from_template('local_teameval/block', $c);
        
    }

    public function render_feedback(feedback $feedback) {
        $context = $feedback->export_for_template($this);
        return $this->render_from_template('local_teameval/feedback', $context);
    }

    public function render_add_question(add_question $addquestion) {
        global $PAGE;
        $context = $addquestion->export_for_template($this);
        $options = $addquestion->get_filepicker_options();
        if ($options) {
            $PAGE->requires->js_init_call('M.core_filepicker.init', [$options], true);
        }
        $PAGE->requires->strings_for_js(['fromtemplate', 'matchingtags', 'templatepreview'], 'local_teameval');
        return $this->render_from_template('local_teameval/add_question', $context);
    }

    public function render_results(results $results) {
        return $this->render_from_template('local_teameval/results', $results->export_for_template($this));
    }

    public function render_release(release $release) {
        return $this->render_from_template('local_teameval/release', $release->export_for_template($this));
    }

    public function render_questionnaire(questionnaire $questionnaire) {
        return $this->render_from_template('local_teameval/questionnaire_submission', $questionnaire->export_for_template($this));
    }

    public function render_download_template(download_template $downloadtemplate) {
        return $this->render_from_template('local_teameval/download_template', $downloadtemplate->export_for_template($this));
    }    

}