<?php

namespace local_teameval;

class events {

    public static function module_deleted($evt) {
        $cmid = $evt->objectid;

        team_evaluation::delete_teameval(null, $cmid);

    }

}
