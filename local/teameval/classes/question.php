<?php

namespace local_teameval;

use renderer_base;

interface question {

    /**
     * @param team_evaluation $teameval this teameval instance
     * @param int $questionid the ID of the question. may be null if this is a new question.
     */
    public function __construct(team_evaluation $teameval, $questionid = null);

    /*

    These next two things are templatables, not renderables. There is a good reason for
    this! Simply put, these are virtually always rendered client-side, via a webservice.
    Teameval can't guarantee that your custom rendering code will run, and indeed it
    almost always won't be. If you need to run code in your view that can't be handled
    by Mustache, include it as Javascript in a {{#js}}{{/js}} block and it will be run
    every time your view is rendered.

    Keep that in mind - it will be run EVERY TIME YOUR VIEW IS RENDERED. Be performant,
    and make sure not to install event handlers twice. Strongly consider using AMD so that
    your code is cached.

    */

    /**
     * The view that a submitting user should see. Rendered with submission_view.mustache
     * @return stdClass|array template data. @see templatable
     *
     * You MUST attach an event handler for the "delete" event. This handler must return
     * a $.Deferred whose results will be ignored.
     *
     * You MUST attach an event handler for the "submit" event. This handler must return
     * a $.Deferred whose results should be an object with an 'incomplete' property indicating
     * if the submitted data was a complete response to the question. If there is an error
     * in submission, return a non-200 status.
     *
     * You should return a version that cannot be edited if $locked is set to true.
     *
     * You should indicate that the form is incomplete after the first "submit" event
     * or if $locked is true. You should set the CSS class "incomplete" on your template's
     * direct ancestor if you do.
     */
    public function submission_view($locked = false);

    /**
     * The view that an editing user should see. Rendered with editing_view.mustache
     * When being created for the first time, a question's editing view will be rendered
     * with a context consisting of just one key-value pair: _newquestion: true. This
     * template must render properly without any context.
     *
     * You MUST attach an event handler for the "save" event to the parent .question-container.
     * This event must return a $.Deferred which will resolve with the new
     * question data which will be returned from $this->submission_view.
     *
     * Once submitting users have started submitting responses to your question, you should
     * prevent editing users from changing aspects of your question that would affect marks.
     * For example, in the Likert question, you could no longer change the minimum and maximum
     * values. However, you may allow some aspects of your question to be edited, such as
     * the title or description. It's up to you to ensure that users don't edit your question
     * in such a way that the responses become unreadable.
     *
     * @return stdClass|array template data. @see templatable
     */
    public function editing_view();

    /**
     * Returns data to be passed to your JavaScript class along with the standard parameters.
     * You might want to use this to include JSON-encodable versions of your submission and editing
     * views' export_for_template data. Whatever you need to render your question in JavaScript,
     * you should return from this function.
     *
     * You SHOULD only return as much data as is readable by the user. Returning more data
     * than necessary may provide users with attack vectors. Be sure to use
     * team_evaluation::check_capability as this will allow users to modify templates in their
     * own user contexts.
     *
     * @return mixed JSON-encodable data.
     */
    public function context_data(renderer_base $output, $locked = false);

    /**
     * Return the name of this teamevalquestion subplugin
     * @return type
     */
    public function plugin_name();

    /**
     * Does this contribute a numeric value towards the user's evaluation score? Return false if
     * your question is either not a question (such as a structural element) or if it records
     * feedback only.
     * @return type
     */
    public function has_value();

    /**
     * Does this question contribute toward completion? has_value must be false if this is false.
     * @return bool
     */
    public function has_completion();

    /**
     * The minimum value that can be returned from this question. Usually 0, even if a higher minimum is
     * specified by the user; think: does the user expect 1 / 5 to be 20% or 0%? Do they expect 4 / 5
     * to be 80% or 75%? Must be implemented if has_value is true;
     * @return int
     */
    public function minimum_value();

    /**
     * The maximum value that can be returned from this question. Must be greater than minimum_value,
     * must be implemented if has_value is true.
     * @return int
     */
    public function maximum_value();

    /**
     * Return a brief, meaningful title for this question. This will be used in reports.
     * @return type
     */
    public function get_title();

    /**
     * If this function returns true, the corresponding response class must implement response_feedback
     * @see response_feedback
     * @return bool
     */
    public function has_feedback();

    /**
     * Return true if the feedback given by your question should not be associated with the person
     * who left that feedback when shown to the target of that feedback. Teacher roles can always
     * see who gave feedback.
     * @return bool
     */
    public function is_feedback_anonymous();

    /**
     * Make a new copy of this question. We handle calling should_update_question and update_question.
     * @param int $questionid The ID of the old question
     * @param team_evaluation $newteameval The new team evaluation your question is being copied into.
     * @return int The questionid for the new question (that would normally be passed to update_question)
     */
    public static function duplicate_question($questionid, $newteameval);

    /**
     * Delete these questions from disk.
     * @param array $questionids The plugin-local question ID you passed to should_update_question
     */
    public static function delete_questions($questionids);

    /**
     * Reset user data for these questions, including responses.
     * @param array $questionids The plugin-local question ID you passed to should_update_question
     */
    public static function reset_userdata($questionids);

    /**
     * Supported renderer subtypes. You should implement, at the very least, a plaintext renderer that
     * returns a plaintext version of your plugin's renderable response objects - that is, anything
     * that might be returned from opinion_of_readable or feedback_for_readable.
     *
     * @see plugininfo\teamevalquestion
     */
    // public static function supported_renderer_subtypes();
}
