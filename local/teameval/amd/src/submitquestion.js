/*
 * @package    local_teameval
 * @copyright  2015 Morgan Harris
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 /**
  * Submit questionnaire button for teameval blocks
  * @module local_teameval/submitquestion
  */
define(['jquery', 'core/templates', 'core/ajax', 'core/notification', 'core/str'], function($, Templates, Ajax, Notification, Str) {

    var _cmid;

    return {

        submit: function() {

            var incompletes = 0;

            $('.local-teameval-submit-buttons .submit').prop('disabled', true);

            // These promises resolve to objects with question, ajaxCall and deferred properties
            // This allows submit methods to return their AJAX call data asynchronously if necessary
            var questions = $('#local-teameval-questions').find('.question-container').map(function() {
                return $(this).data('question');
            });

            // Optional questions first, so the questionnaire doesn't lock halfway through saving
            questions.sort(function(a, b) {
                return parseInt(b.optional) - parseInt(a.optional);
            });

            var questionPromises = questions.map(function() {
                var p = $.Deferred();

                var complete = this.submit(function(args) {
                    var d = $.Deferred();

                    if (args && args.methodname) {
                        p.resolve({
                            question: this,
                            ajaxCall: args,
                            deferred: d
                        });
                    } else {
                        p.resolve();
                        d.resolve();
                    }

                    return d.promise();
                });

                if (! complete) {
                    incompletes++;
                }

                return p;

            });

            $.when.apply($, questionPromises).done(function() {

                var questions = [].slice.call(arguments).filter(function(v) { return v !== undefined; });

                // Add the completed call
                var allDone = $.Deferred();
                questions.push({
                    ajaxCall: {
                        methodname: "local_teameval_questionnaire_submitted",
                        args: {
                            cmid: _cmid
                        }
                    },
                    deferred: allDone
                });

                var promises = Ajax.call(questions.map(function(v) { return v.ajaxCall; }));

                for (var i = 0; i < questions.length; i++) {
                    var p = promises[i];
                    var d = questions[i].deferred;
                    p.done(d.resolve);
                    p.fail(d.reject);
                    p.progress(d.notify);
                }

                var allPromises = $.when.apply($, promises);
                allPromises.always(function() {
                    $('.local-teameval-submit-buttons .submit').prop('disabled', false);
                }).done(function() {
                    if (incompletes > 0) {
                        var key = incompletes == 1 ? 'incompletewarning1' : 'incompletewarning';
                        Str.get_string(key, 'local_teameval', incompletes)
                            .done(function(string) {
                            $('.local-teameval-submit-buttons .results.incomplete').text(string).show('fast');
                        });
                    } else {
                        $('.local-teameval-submit-buttons .results.incomplete').hide('fast');
                        $('.local-teameval-submit-buttons .results.saved').show('fast').delay(5000).hide('fast');
                    }
                }).fail(Notification.exception);

            });

        },

        initialise: function(cmid) {

            _cmid = cmid;

            Templates.render('local_teameval/submit_buttons', {}).done(function(html, js) {
                var questionContainer = $('.local-teameval-containerbox');
                questionContainer.append(html);
                Templates.runTemplateJS(js);

                $('.local-teameval-submit-buttons .submit').click(this.submit);
            }.bind(this));

        }

    };

});
