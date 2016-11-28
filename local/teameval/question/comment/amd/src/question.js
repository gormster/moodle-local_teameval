define(['jquery', 'local_teameval/question', 'core/ajax', 'core/templates', 'core/notification', 'core/str', 'local_teameval/formparse'], function($, Question, Ajax, Templates, Notification, Strings, Formparse){

    function CommentQuestion(container, teameval, contextid, self, editing, questionID, context) {
        Question.apply(this, arguments);

        this._self = self;
        this._editing = editing;

        var context = context || {};
        this._editingcontext = context.editingcontext || {_newquestion: true};
        this._editinglocked = context.editinglocked || false;
        this._submissioncontext = context.submissioncontext || {};

        this.pluginName = 'comment';
    }

    CommentQuestion.prototype = new Question;

    CommentQuestion.prototype.submissionContext = function() { return this._submissioncontext; }

    CommentQuestion.prototype.submissionView = function() {
        console.log(this._submissioncontext);
        return Question.prototype.submissionView.apply(this, arguments);
    }
    
    CommentQuestion.prototype.editingContext = function() { return this._editingcontext; }

    CommentQuestion.prototype.editingView = function() {
        console.debug($.param(this._editingcontext));
        return this.editForm('\\teamevalquestion_comment\\forms\\edit_form', $.param(this._editingcontext), {'locked': this._editinglocked});
    }

    CommentQuestion.prototype.save = function(ordinal) {
        var form = this.container.find('form');

        var data = Formparse.serializeObject(form);

        return this.saveForm(form, ordinal).then(function(result) {

            return Strings.get_strings([
                {key: 'exampleuser', component: 'local_teameval'},
                {key: 'yourself', component: 'local_teameval'}
            ]).then(function(str) {

                var demoUsers = [{ userid: 0, name: str[0] }];
                if (this._self) {
                    demoUsers.unshift({userid: -1, name: str[1], self: true});
                }

                this._submissioncontext = $.extend({}, data, {
                    users: demoUsers,
                });
                this._submissioncontext.description = data.description.text;

                this._editingcontext = data;

                return result;
            }.bind(this));
        }.bind(this));
    };

    function validateData(data) {

        var deferred = $.Deferred();
        
        if ((data.title.trim().length == 0) && (data.description.trim().length == 0)) {
            Strings.get_string('titleordescription', 'teamevalquestion_comment').done(function(str) {
                deferred.reject({invalid: true, errors: { title: str, description: str} });
            });
        } else {
            deferred.resolve();
        }

        return deferred.promise();
    }

    CommentQuestion.prototype.submit = function() {
        var comments = [];
        this.container.find('.comments textarea').each(function(v, k) {
            var toUser = $(this).data('touser');
            var m = {};
            m.touser = toUser;
            m.comment = $(this).val();
            comments.push(m);
        });

        var promises = Ajax.call([{
            methodname: 'teamevalquestion_comment_submit_response',
            args: {
                teamevalid: this.teameval,
                id: this.questionID,
                comments: comments
            }
        }]);

        var incomplete = false;
        if (this._submissioncontext.optional) {
            incomplete = checkComplete();
        }

        return promises[0].then(function() {
            return {'incomplete': incomplete};
        });
    }

    function checkComplete() {
        var incomplete = this.container.find('textarea').filter(function() {
            return $(this).val().trim().length == 0;
        });

        if (incomplete.length > 0) {
            questionContainer.parent().addClass('incomplete');
        } else {
            questionContainer.parent().removeClass('incomplete');
        }

        return incomplete.length > 0;
    }

    CommentQuestion.prototype.delete = function() {

        var promises = Ajax.call([{
            methodname: 'teamevalquestion_comment_delete_question',
            args: {
                teamevalid: this.teameval,
                id: this.questionID
            }
        }]);

        return promises[0];

    };

    return CommentQuestion;

});