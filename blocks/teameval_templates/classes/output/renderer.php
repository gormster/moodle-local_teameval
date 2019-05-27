<?php

namespace block_teameval_templates\output;

use plugin_renderer_base;

class renderer extends plugin_renderer_base {

        public function render_title(title $title) {
                return $this->render_from_template('block_teameval_templates/title', $title->export_for_template($this));
        }

        public function render_deletebutton(deletebutton $deletebutton) {
                return $this->render_from_template('block_teameval_templates/deletebutton', $deletebutton->export_for_template($this));
        }

        public function render_addtomodule(addtomodule $addtomodule) {
                return $this->render_from_template('block_teameval_templates/addtomodule', $addtomodule->export_for_template($this));
        }



}
