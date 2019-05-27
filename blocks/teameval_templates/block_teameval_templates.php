<?php

use local_teameval\team_evaluation;

class block_teameval_templates extends block_base {

    public function init() {
        $this->title = get_string('teamevaltemplates', 'block_teameval_templates');
    }

    function applicable_formats() {
      return array(
        'all' => true,
        'mod' => false
      );
    }

    function get_required_javascript() {
        parent::get_required_javascript();
        $this->page->requires->js_call_amd('local_teameval/collapsible', 'init', [['selector' => '.block_teameval_templates .collapsible', 'target' => '.collapse-label']]);
    }

    public function get_content() {
        global $OUTPUT, $USER;

        // throughout we'll be using check|guard_capability for its ability to manage dashboard contexts
        if (team_evaluation::check_capability($this->page->context, ['local/teameval:viewtemplate'])) {

            // if ($this->content !== null) {
            //   return $this->content;
            // }

            $this->content = new stdClass;

            $this->content->text = html_writer::start_tag('ul', ['class' => 'teameval-template-tree']);

            // get all parent contexts of this context, and self

            $contexts = array_reverse($this->page->context->get_parent_contexts(true));

            $lists = [];

            foreach($contexts as  $context) {

                $listitems = [];

                $all_teamevals = team_evaluation::templates_for_context($context->id);

                if (count($all_teamevals) == 0) {
                    continue;
                }

                foreach($all_teamevals as $teameval) {
                    $url = new moodle_url('/blocks/teameval_templates/template.php', array('id' => $teameval->id, 'contextid' => $this->page->context->id));

                    $link = html_writer::link($url, $teameval->get_title());
                    $li = html_writer::tag('li', $link, ['class' => 'teameval-template-item']);
                    $listitems[] = $li;
                }

                $lists[] = ['heading' => $context->get_context_name(), 'items' => $listitems];
            }

            foreach($lists as $i => $list) {

                    $listitems = $list['items'];
                    $heading = $list['heading'];

                    // collapse every list except the last one
                    $class = 'collapsed';
                    if ($i == count($lists) - 1) {
                        $class = 'expanded';
                    }

                    $content = html_writer::tag('h4', $heading, ['class' => 'collapse-label']);
                    $content .= html_writer::tag('ul', implode("\n", $listitems));

                    $this->content->text .= html_writer::tag('li', $content, ['class' => 'collapsible '.$class]);

            }

            $this->content->text .= html_writer::end_tag('ul');

            if (team_evaluation::check_capability($this->page->context, ['local/teameval:createquestionnaire'])) {
                $url = new moodle_url('/blocks/teameval_templates/template.php', array('contextid' => $this->page->context->id));
                $this->content->footer = html_writer::link($url, $OUTPUT->pix_icon('t/add', '') . get_string('newtemplate', 'block_teameval_templates'));
            }

            return $this->content;

        }

        $empty = new stdClass;
        return $empty;

    }

}

?>
