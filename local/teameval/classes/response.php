<?php

namespace local_teameval;

interface response {

    /**
     * @param team_evaluation $teameval the teamevaluation object this response belongs to
     * @param question $question the question object of the question this is a response to
     * @param int $userid the ID of the user responding to this question
     */
    public function __construct(team_evaluation $teameval, $question, $userid);

    /**
     * Has a complete response been given by this user? Only return if you have a complete
     * response from the user, where marks have been assigned for all teammates.
     * @return bool
     */
    public function marks_given();

    /**
     * What is this user's opinion of a particular teammate? Scaled from 0.0 to 1.0.
     *
     * If you return true from marks_given, and your question has value, you must return
     * a valid float for every teammate, that was actually supplied by the user. If a user
     * doesn't select an option, you should not return zero by default; zero is a meaningful
     * score.
     *
     * If question->has_value is false, you do not have to return a meaningful value.
     *
     * @param type $userid Team mate's user ID
     * @return float 0.0–1.0
     */
    public function opinion_of($userid);

    /**
     * Human readable of above; for reports plugins
     *
     * This is a subclass of our templatable abstract class for performance reasons. If you use JS
     * in your template, even if you're just making a single AMD call to a loaded module, it causes
     * unacceptable slowdown in the browser for large reports. For this reason, you can nominate a
     * single AMD call to be made after the entire report has been rendered.
     *
     * @param int $userid Teammates user ID
     * @param string $source The plugin that is asking for this opinion. Use to customise appearance.
     * @return local_teameval\templatable
     */
    public function opinion_of_readable($userid, $source = null);

}
