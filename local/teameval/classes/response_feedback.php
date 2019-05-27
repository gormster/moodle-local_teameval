<?php

namespace local_teameval;

interface response_feedback extends response {

    /**
     * What is this user's feedback for a particular teammate? This is a straight plain-text interpretation.
     * @param int $userid Team mate's user ID
     * @return string
     */
    public function feedback_for($userid);

    /**
     * Return a renderable version of this response for inclusion in a report
     * @return renderable
     */
    public function feedback_for_readable($userid);

}
