<?php

namespace local_teameval\traits\report;

trait delegated_export {

    public function export($filename) {

        $info = pathinfo($filename);
        $ext = $info['extension'];
        list($fn, $realname) = explode('_', $info['filename'], 2);

        $methodname = "export_{$fn}_{$ext}";

        if (method_exists($this, $methodname)) {
            $this->$methodname($info['filename']);
        }

        return false;

    }

}
