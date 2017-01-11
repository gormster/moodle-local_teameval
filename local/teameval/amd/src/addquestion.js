/*
 * @package    local_teameval
 * @copyright  2015 Morgan Harris
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
 /**
  * Add question button for teameval blocks
  * @module local_teameval/addquestion
  */
define(['jquery', 'jqueryui', 'core/str', 'core/templates', 'core/ajax', 'core/notification'], 
    function($, ui, str, templates, ajax, notification) {

    "use strict";

    var _id;

    var _contextid;

    var _subplugins;

    var _self;

    var _locked;

    var _addButton;

    var _searchBar;

    var _initialised;

    var _templateAddButton;

    var _templatePreviewFunction; // called with no args to reset

    return {

        // Ask the user what kind of question they want to add
        preAddQuestion: function(evt) {

            //todo: check that you CAN add a question right now

            //todo: if there's only one question subplugin skip this step and assume they mean that

            //todo: mustache this
            var dropdown = $('<ul class="local-teameval-question-dropdown" />');
            $.each(_subplugins, function(name, subplugin) {
                var li = $("<li />");
                li.html('<a>' + subplugin.displayname + '</a>');
                li.data('type',subplugin.name);
                dropdown.append(li);
            });

            $(".local-teameval-containerbox").append(dropdown);
            var coords = _addButton.position();

            dropdown.css('top', coords.top + 'px');
            dropdown.css('right', '0px');

            // If we don't do this, we'll accidentally trigger our click-outside handler
            evt.stopPropagation();

            var _this = this;
            $(document).one('click', function(evt) {
                if ($(evt.target).closest('.local-teameval-question-dropdown').length > 0) {
                    var type = $(evt.target).closest('li').data('type');
                    _this.addQuestion(type).done(function(question) {
                        _this.editQuestion(question);
                    });
                }
                dropdown.remove();
            });

        },

        addQuestion: function(type, questionID, context) {
            var question = $('<li class="local-teameval-question" />');
            var questionContainer = $('<div class="question-container" />');
            question.append(questionContainer);
            $('#local-teameval-questions').append(question);
            this.addEditingControls(question);

            question.data('questiontype', type);
            if (questionID) {
                question.data('questionid', questionID);
            }

            var d = $.Deferred();
            require(['teamevalquestion_'+type+'/question'], function(QuestionClass) {
                var qObject = new QuestionClass(questionContainer, _id, _contextid, _self, true, questionID, context);
                questionContainer.data("question", qObject);
                d.resolve(question);
            });

            return d.promise();
        },

        addEditingControls: function(question) {
            var _this = this;

            templates.render('local_teameval/question_actions', {locked:_locked}).done(function(html) {

                var actionBar = $(html);
                question.prepend(actionBar);
                actionBar.find('.edit').click(function() {
                    _this.editQuestion(question);
                });
                actionBar.find('.delete').click(function() {
                    _this.deleteQuestion(question);
                });


                //if we're in editing mode, hide the edit and delete buttons
                if (question.hasClass('editing')) {
                    actionBar.hide();
                }

            });

            // and our Save and Cancel buttons for editing

            templates.render('local_teameval/save_cancel_buttons', {}).done(function(html) {
                var buttonArea = $(html);
                buttonArea.find(".save").click(function() {
                    _this.saveQuestion(question);
                });
                buttonArea.find(".cancel").click(function() {
                    // The cancel button should delete a question if it isn't saved
                    if (question.data('questionid') === undefined) {
                        _this.deleteQuestion(question);
                    } else {
                        _this.showQuestion(question);
                    }
                });
                question.append(buttonArea);

                if (!question.hasClass('editing')) {
                    buttonArea.hide();
                }

            });
        },

        editQuestion: function(question) {

            // hide the action bar
            question.find('.local-teameval-question-actions').hide();
            var questionContainer = question.find('.question-container');

            var questionObject = questionContainer.data("question");
            
            questionObject.editingView().done(function() {

                question.addClass('editing');
                question.find('.local-teameval-save-cancel-buttons').show();

            }).fail(function () {

                question.find('.local-teameval-question-actions').show();

            });

        },

        saveQuestion: function(question) {

            // todo: do save

            var questionContainer = question.find('.question-container');
            var questionObject = questionContainer.data("question");
            var ordinal = question.index('.local-teameval-question');
            var promise = questionObject.save(ordinal);
            promise.done(function(questionID) {
                question.data('questionid', questionID);
                this.showQuestion(question);
            }.bind(this));
            promise.fail(function() {
                // at the moment, do nothing
                // rely on the question plugin to relay that saving has failed
            }.bind(this));

        },

        showQuestion: function(question) {

            var questionContainer = question.find('.question-container');
            var questionObject = questionContainer.data('question');

            questionObject.submissionView().done(function() {

                question.removeClass('editing');
                
                question.find('.local-teameval-save-cancel-buttons').hide();
                question.find('.local-teameval-question-actions').show();

            }).fail(notification.exception);

        },

        deleteQuestion: function(question) {
            var questionContainer = question.find('.question-container');
            if (question.data('questionid') === undefined) {
                // just pull it out of the DOM
                questionContainer.removeData('question');
                question.remove();
            } else {
                // actually delete it from the database
                var questionObject = questionContainer.data('question');
                questionObject.delete().done(function() {
                    questionContainer.removeData('question');
                    question.remove();
                }).fail(notification.exception);
            }
        },

        setOrder: function() {
            var order = $("#local-teameval-questions li").map(function() {
                return {type: $(this).data('questiontype'), id: $(this).data('questionid')};
            }).filter(function() {
                return this !== undefined;
            }).get();

            var promises = ajax.call([{
                methodname: 'local_teameval_questionnaire_set_order',
                args: {
                    id: _id,
                    order: order
                }
            }]);

            promises[0].done(function() {
                
            }).fail(notification.exception);
        },

        addFromTemplate: function() {
            var templateid = _searchBar.data('template-id');

            if (templateid > 0) {

                _templateAddButton.prop('disabled', true);

                var _this = this;

                var promises = ajax.call([{
                    methodname: 'local_teameval_add_from_template',
                    args: {
                        from: templateid,
                        to: _id
                    }
                }]);

                promises[0].done(function(questions) {
                    _this.addQuestions(questions);

                    //reset the view
                    _templatePreviewFunction();
                    _searchBar.val('');
                });

                promises[0].fail(notification.exception);
            }
        },

        addQuestions: function(questions) {
            for (var i = 0; i < questions.length; i++) {
                var qdata = questions[i];
                this.addQuestion(qdata.type, qdata.questionid, JSON.parse(qdata.context))
                .done(function(question) {
                    this.showQuestion(question);
                }.bind(this));
            }
        },

        // This might seem like a bit of a weird thing to put here
        // but it's to avoid a race condition in dependent modules
        // We only initialise once, after all.
        initialised: function() {
            if (!_initialised) {
                _initialised = $.Deferred();
            }
            return _initialised.promise();
        },

        initialise: function(options) {

            // available options
            // containers: addButton, templateSearch, templateIO
            // settings: id, self, locked, download, filepickerid, filepickeritemid, subplugins

            _id = options.id;
            _contextid = options.contextid;
            _self = options.self;
            _locked = options.locked;
            _subplugins = options.subplugins;
            _addButton = $(options.addButton);

            // some locals. some of these might not be set.

            var templateSearch = $(options.templateSearch);
            var templateIO = $(options.templateIO);

            // stupid javascript scoping

            var _this = this;

            // add the controls to the questions already in the block

            $('#local-teameval-questions .local-teameval-question').each(function() {
                _this.addEditingControls($(this));
            });

            templateIO.find('.template-download').click(function() {
                window.location.href = options.download;
            });

            if (!_locked) {

                $('#local-teameval-questions').sortable({
                    handle: '.local-teameval-question-actions .move',
                    axis: "y",
                    update: function() {
                        _this.setOrder();
                    }
                });

                _addButton.find('a').click(_this.preAddQuestion.bind(_this));

                // SET UP SEARCH BAR

                _templateAddButton = templateSearch.find('button');
                _templateAddButton.click(_this.addFromTemplate.bind(_this));
                _templateAddButton.prop('disabled', true);

                _templatePreviewFunction = options.templatePreviewFunction;

                _searchBar = templateSearch.find('input');
                _searchBar.autocomplete({
                    minLength: 2,
                    source: function(request, response) {
                        var promises = ajax.call([{
                            methodname: 'local_teameval_template_search',
                            args: {
                                id: _id,
                                term: request.term
                            }
                        }]);

                        promises[0].done(function(results) {
                            response(results);
                        });

                        promises[0].fail(notification.exception);
                    },
                    focus: function( event, ui ) {
                        _searchBar.val( ui.item.title );
                        return false;
                      },
                    select: function( event, ui ) {
                        if (ui.item) {
                            _searchBar.val( ui.item.title );
                            _searchBar.data('template-id', ui.item.id);
                            _templateAddButton.prop('disabled', false);
                            _templatePreviewFunction(ui.item);
                        } else {
                            _templateAddButton.prop('disabled', true);
                            _templatePreviewFunction();
                        }
                        return false;
                      }
                });

                _searchBar.focus(function() {
                    _searchBar.val('');
                    _templateAddButton.prop('disabled', true);
                    _templatePreviewFunction();
                });

                _searchBar.change(function() {
                    _templateAddButton.prop('disabled', true);
                    _templatePreviewFunction();
                });

                _searchBar.autocomplete( "instance" )._renderItem = options.autocompleteRenderFunction;

                // SET UP TEMPLATE DOWNLOAD

                templateIO.find('.template-upload').click(function() {
                    var instance = M.core_filepicker.instances[options.filepickerid];
                    instance.options.formcallback = function(file) {
                        var promises = ajax.call([{
                            methodname: 'local_teameval_upload_template',
                            args: {
                                'id': _id,
                                'itemid': options.filepickeritemid,
                                'file': file.file,
                            }
                        }]);


                        promises[0].done(function(questions) {
                            _this.initialised().done(function() {
                                _this.addQuestions(questions);
                            });
                        });

                        promises[0].fail(notification.exception);
                    };

                    instance.show();
                });

                // FIRE INITIALISED
                if (!_initialised) {
                    _initialised = $.Deferred();
                }
                _initialised.resolve();
                

            }

        }

    };

});