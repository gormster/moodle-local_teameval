<?php

namespace local_teameval\traits\question;

trait no_value {
    function has_value() {
        return false;
    }

    function minimum_value() {
        return 0;
    }

    function maximum_value() {
        return 0;
    }
}
