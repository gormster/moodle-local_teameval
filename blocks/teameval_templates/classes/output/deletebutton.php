<?php

namespace block_teameval_templates\output;

use stdClass;
use templatable;
use renderable;
use renderer_base;

class deletebutton implements templatable, renderable {

        protected $templateid;

        protected $contexturl;

        public function __construct($teameval) {
                $this->templateid = $teameval->id;
                $this->contexturl = $teameval->get_context()->get_url();
        }

        public function export_for_template(renderer_base $output) {
                $c = new stdClass;
                $c->templateid = $this->templateid;
                $c->contexturl = $this->contexturl->out();
                return $c;
        }

}
