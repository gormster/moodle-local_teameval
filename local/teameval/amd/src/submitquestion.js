/*
 * @package    local_teameval
 * @copyright  2015 Morgan Harris
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
 /**
  * Submit questionnaire button for teameval blocks
  * @module local_teameval/submitquestion
  */
define(['jquery', 'core/templates', 'core/ajax', 'core/notification', 'core/str'], function($, templates, ajax, notification, Str) {

    var _cmid;

    return {

        submit: function() {

            var questions = $('#local-teameval-questions');
            var promises = [];
            questions.find('.question-container').each(function() {

                var uiblocker = $('<div class="ui-blocker" />');
                $(this).append(uiblocker);
                var questionObject = $(this).data('question');
                var p = questionObject.submit();
                promises.push(p);
                p.always(function () {
                    uiblocker.remove();
                });

            });

            var allPromises = $.when.apply($, promises);
            allPromises.done(function() {
                
                var incompletes = $.grep(arguments, function(el) {
                    if (el) {
                        return el.incomplete === true;
                    }
                }).length;

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
            }).fail(notification.exception);

        },

        initialise: function(cmid) {
            
            _cmid = cmid;

            templates.render('local_teameval/submit_buttons', {}).done(function(html, js) {
                var questionContainer = $('.local-teameval-containerbox');
                questionContainer.append(html);
                templates.runTemplateJS(js);

                $('.local-teameval-submit-buttons .submit').click(this.submit);
            }.bind(this));

        }

    };

});