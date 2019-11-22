define(['jquery', 'local_teameval/question', 'core/templates', 'core/notification',
    'core/str', 'local_teameval/formparse', 'local_teameval/editor'],
    function($, Question, Templates, Notification, Strings, Formparse, Editor){

    function CommentQuestion(container, teameval, contextid, self, editing, optional, questionID, context) {
        Question.apply(this, arguments);

        this._self = self;
        this._editing = editing;

        context = context || {};
        this._editingcontext = context.editingcontext || {_newquestion: true};
        this._editinglocked = context.editinglocked || false;
        this._submissioncontext = context.submissioncontext || {};

        this.pluginName = 'comment';
    }

    CommentQuestion.prototype = new Question();

    CommentQuestion.prototype.submissionContext = function() { return this._submissioncontext; };

    CommentQuestion.prototype.submissionView = function() {
        return Question.prototype.submissionView.apply(this, arguments);
    };

    CommentQuestion.prototype.editingContext = function() { return this._editingcontext; };

    CommentQuestion.prototype.editingView = function() {
        return this.editForm('\\teamevalquestion_comment\\forms\\edit_form',
            $.param(this._editingcontext), {'locked': this._editinglocked});
    };

    CommentQuestion.prototype.save = function(ordinal) {
        var form = this.container.find('form');
        Editor.saveAll(form);

        var data = Formparse.serializeObject(form);

        var savePromise = this.saveForm(form, ordinal);
        var strPromise = Strings.get_strings([
                {key: 'exampleuser', component: 'local_teameval'},
                {key: 'yourself', component: 'local_teameval'}
            ]);

        return $.when(savePromise, strPromise).then( function(result, str) {
            var demoUsers = [{ userid: 0, name: str[0] }];
            if (this._self) {
                demoUsers.unshift({userid: -1, name: str[1], self: true});
            }

            this._submissioncontext = $.extend({}, Formparse.serializeObject(form, { fixCheckboxes: true }), {
                users: demoUsers,
            });
            this._submissioncontext.description = data.description.text;

            this._editingcontext = data;

            return result;
        }.bind(this));
    };

    CommentQuestion.prototype.validateData = function(form) {

        var deferred = $.Deferred();

        var data = function(v) { return $(form).find('[name="'+v+'"]').val(); };

        if ((data('title').trim().length === 0) && (data('description').trim().length === 0)) {
            Strings.get_string('titleordescription', 'teamevalquestion_comment').done(function(str) {
                deferred.reject({invalid: true, errors: { title: str, description: str} });
            });
        } else {
            deferred.resolve();
        }

        return deferred.promise();
    };

    CommentQuestion.prototype.submit = function(call) {
        var comments = [];
        this.container.find('.comments textarea').each(function() {
            var toUser = $(this).data('touser');
            var m = {};
            m.touser = toUser;
            m.comment = $(this).val();
            comments.push(m);
        });

        call({
            methodname: 'teamevalquestion_comment_submit_response',
            args: {
                teamevalid: this.teameval,
                id: this.questionID,
                comments: comments
            }
        });

        var incomplete = false;
        if (!this._submissioncontext.optional) {
            incomplete = this.checkComplete();
        }

        return !incomplete;
    };

    CommentQuestion.prototype.checkComplete = function() {
        var incomplete = this.container.find('textarea').filter(function() {
            return $(this).val().trim().length === 0;
        });

        if (incomplete.length > 0) {
            this.container.parent().addClass('incomplete');
        } else {
            this.container.parent().removeClass('incomplete');
        }

        return incomplete.length > 0;
    }

    return CommentQuestion;

});