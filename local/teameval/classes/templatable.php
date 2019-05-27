<?php

namespace local_teameval;

abstract class templatable implements \templatable {

    public function nominal_template() {

        $components = explode('\\', get_class($this));
        $first = reset($components);
        $last = end($components);
        error_log("$first $last");
        return "$first/$last";

    }

    /**
     * If your template needs a JS init call, specify it here
     * It will be called with the containing element of your template
     * @return array(module, call) | null
     */
    public function amd_init_call() {

        return null;

    }

}
