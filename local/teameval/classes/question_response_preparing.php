<?php

namespace local_teameval;

interface question_response_preparing extends question {

    public function prepare_responses($users);

    public function get_response($userid);

}