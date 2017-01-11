/* jshint shadow: true */
define(['local_teameval/question', 'jquery', 'core/str', 'local_teameval/formparse', 'core/ajax'], 
    function(Question, $, Strings, FormParse, Ajax) {

    // 5% width = 0% score
    var MIN_SIZE = 5;

    // ---- Convenience functions

    function sumToIndex(array, index) {
        return array.reduce(function(a, b, i) {
            if (i >= index) {
                return a;
            }
            return a + b;
        }, 0);
    }

    function displayToReal (display, n) {
        var m = 100 / (100 - MIN_SIZE * n);
        return m * display - (MIN_SIZE * m);
    }

    function realToDisplay (real, n) {
        var m = 100 / (100 - MIN_SIZE * n);
        return (real + MIN_SIZE * m) / m;
    }

    // percents MUST sum to exactly 100
    // fixFirst is an array of indices that should be adjusted first
    // namely when a user is dragging a handle, they don't expect other
    // segments to change value
    function fixDisplayValues(percents, fixFirst) {
        var fixed = percents.map(function(v) {
            return Math.round(v);
        });

        fixFirst = fixFirst || [];
        
        var sum = sumToIndex(fixed);
        
        if (sum != 100) {
            
            var difference = sum - 100;
            
            var mapped = percents.map(function(v, i) {
                return {i: i, v: v};
            });
            
            var corrector = (difference < 0) ? 1 : -1;
            // sort by the difference between the actual values and the corrected fixed values
            // we want the numbers which will be made *more* accurate by correcting them
            mapped.sort(function(a, b) {
                var fixa = fixFirst.indexOf(a.i) != -1;
                var fixb = fixFirst.indexOf(b.i) != -1;
                if (fixa != fixb) {
                    return fixa ? -1 : 1;
                }
                return Math.abs(fixed[a.i] + corrector - a.v) - Math.abs(fixed[b.i] + corrector - b.v);
            });

            // by definition, |difference| < percents.length
            // as difference is the sum of the fractional parts
            for(var i = 0; i < Math.abs(difference); i++) {
                var index = mapped[i].i;
                fixed[index] += corrector;
            }
        }
        
        return fixed;
    }

    // ----- Class definition

    function Split100Question(container, teameval, contextid, self, editable, questionID, context) {
        Question.apply(this, arguments);
        this.self = self;

        this.context = context || {};
        this.pluginName = "split100";

        if (this.container.find('.hundred').length) {
            // we've already got submission contents, initialise it
            this.updateView();
            this.initialiseSubmissionView();
        }

        $(window).on('resize', function() {
            this.updateView();
        }.bind(this));
    }

    Split100Question.prototype = Object.create(Question.prototype);

    Split100Question.prototype.displayToReal = function(display) {
        return displayToReal(display, this.sizes.length);
    };

    Split100Question.prototype.realToDisplay = function(real) {
        return realToDisplay(real, this.sizes.length);
    };

    Split100Question.prototype.showOverflow = function(index) {
        var overflow = this.container.find('.overflows').children('.name').eq(index);
        var split = this.container.find('.hundred').children('.split').eq(index);

        overflow.show();

        var middle = split.position().left + split.width() / 2;
        var contentSize = overflow.find('span').width() + 20;
        var width = this.container.find('.hundred').width();

        // figure out which hook we need to show
        // if neither work, we go with the top hook (which needs its own positioning code)

        var order = (middle < (width / 2)) ? ['left', 'right'] : ['right', 'left'];
        var allowed = [];
        if (middle + contentSize < width) {
            allowed.push('left');
        }
        if (middle - contentSize > 0) {
            allowed.push('right');
        }

        overflow.removeClass('left right middle');
        overflow.css('margin-left', '');
        overflow.css('margin-right', '');

        for(var i = 0; i < order.length; i++) {
            var direction = order[i];
            if(allowed.indexOf(direction) !== -1) {
                overflow.addClass(direction);

                overflow.css('z-index', 100-index);

                switch(direction) {
                case 'left':
                    overflow.css('left', middle - 10 + 'px');
                    break;
                case 'right':
                    overflow.css('right', (width - middle) + 'px');
                    break;
                }

                break;
            }
        }
    };

    Split100Question.prototype.updateView = function(movingIndices) {
        
        if (!this.context) {
            // no context? can't display.
            return;
        }

        var n = this.context.users.length;

        if (!this.sizes) {
            this.sizes = this.context.users.map(function(v) {
                return realToDisplay(parseFloat(v.pct), n);
            }, this);
        }

        var split100 = this.container.find('.hundred');

        if (split100.length === 0) {
            // we're in the editing view or some other weird thing has happened. bail.
            return;
        }

        // fix the sizes first
        this.sizes = this.sizes.map(function(v) {
            return Math.max(v, MIN_SIZE);
        });
        
        var sum = sumToIndex(this.sizes);
        var realValues = this.sizes.map(this.displayToReal, this);
        if (sum != 100) {
            var realSum = sumToIndex(realValues);
            var scale = 100 / realSum;
            realValues = realValues.map(function (v) {
                 return v * scale;
            });
            this.sizes = realValues.map(this.realToDisplay, this);
        }

        var displayValues = fixDisplayValues(realValues, movingIndices);
        
        var splits = split100.children('.split');
        var handles = split100.children('.handle');
        var partialSum = 0;
        for(var i = 0; i < this.sizes.length; i++) {
            var size = this.sizes[i];
            splits.eq(i).css({
                width: size + '%',
                left: partialSum + '%',
            })
                .find('.name')
                    .show().end()
                .find('.value')
                    .html(displayValues[i] + '%');
            partialSum += size;
            handles.eq(i).css('left', partialSum + '%');
            
            var split = splits.get(i);
            
            if ((split.scrollWidth > split.clientWidth)
                    || (split.scrollHeight > split.clientHeight)) {
                $(split).find('.split-label .name').hide();
                this.showOverflow(i);
            } else {
                this.container.find('.overflows').children('.name').eq(i).hide();
            }
        }

    };

    Split100Question.prototype.initialiseSubmissionView = function() {
        var moving;
        var offset;
        var width;
        var movingIndex;
        var trackingTouch;

        var split100 = this.container.find('.hundred');

        if (window.TouchEvent) {
            this.container.find('.handle').addClass('touch');
        }

        split100.on('mousedown touchstart', '.handle', function(evt) {
            if (!moving) {
                evt.preventDefault();
                
                moving = $(evt.target);
                movingIndex = $(evt.delegateTarget).children('.handle').index(moving);
                offset = $(evt.delegateTarget).offset();
                width = $(evt.delegateTarget).width();

                if (evt.originalEvent.touches) {
                    trackingTouch = evt.originalEvent.touches[0].identifier;
                }

                if (movingIndex == -1) {
                    throw "The DOM tree is messed up; reload the page";
                }
            }
        })
        .on('mousemove touchmove', function(evt) {
            if (moving) {
                evt.preventDefault();

                var pageX;
                if (evt.pageX) {
                    pageX = evt.pageX;
                } else if (evt.originalEvent.touches) {
                    for (var i = 0; i < evt.originalEvent.changedTouches.length; i++) {
                        var touch = evt.originalEvent.changedTouches[i];
                        if (touch.identifier == trackingTouch) {
                            pageX = touch.pageX;
                        }
                    }
                }

                if (pageX === undefined) {
                    // got an invalid or unrelated event. do nothing. 
                    return;
                }
                
                var target = ((pageX - offset.left) / width) * 100;
                
                var leftSum = sumToIndex(this.sizes, movingIndex + 1);
                
                var shift = target - leftSum;
                
                var leftIndex = movingIndex;
                var rightIndex = movingIndex + 1;

                // Handle the case where you need to move "through" a zero-percent split
                if (shift < 0) {
                    leftIndex = null;
                    for (var i = movingIndex; i >= 0; i--) {
                        if (this.sizes[i] > MIN_SIZE) {
                            leftIndex = i;
                            break;
                        }
                    }
                }
                
                if (shift > 0) {
                    rightIndex = null;
                    for (var i = movingIndex + 1; i < this.sizes.length; i++) {
                        if (this.sizes[i] > MIN_SIZE) {
                            rightIndex = i;
                            break;
                        }
                    }
                }
                
                // If either index is not defined, we're pushing up against the end of the bar
                if ((leftIndex !== null) && (rightIndex !== null)) {
                    this.sizes[leftIndex] += shift;
                     this.sizes[rightIndex] -= shift;
                }
                
                this.updateView([leftIndex, rightIndex]);
            }
        }.bind(this))
        .on('mouseup mouseleave touchend touchcancel', function(evt) {
            evt.preventDefault();
            moving = null;
            split100.children('.split').css('color', '');
        });

    };

    Split100Question.prototype.submissionView = function() {
        return Question.prototype.submissionView.apply(this, arguments).done(function() {
            this.updateView();
            this.initialiseSubmissionView();
        }.bind(this));
    };

    Split100Question.prototype.submissionContext = function() {
        return this.context;
    };

    Split100Question.prototype.editingView = function() {
        var formdata = {
            title: this.context.title,
            description: {
                format: 1,
                text: this.context.description
            }
        };
        return this.editForm('\\teamevalquestion_split100\\forms\\edit_form', $.param(formdata), {});
    };

    Split100Question.prototype.save = function(ordinal) {
        var form = this.container.find('form');

        var data = FormParse.serializeObject(form);
        this.context.title = data.title;
        this.context.description = data.description.text;

        var strs = [this.self ? 'yourself' : 'exampleuser', 'exampleuser', 'exampleuser', 'exampleuser'].map(function(v, i) {
            return {key: v, component: 'teamevalquestion_split100', param: this.self ? i : i + 1};
        });

        var valuePromise = Strings.get_strings(strs).then(function(s) {
            var pcts = [20, 10, 15, 55];
            this.users = s.map(function(v, i) {
                return {
                    name: v,
                    id: -i,
                    pct: pcts[i]
                };
            });
        });

        var savePromise = this.saveForm(form, ordinal);

        return $.when(valuePromise, savePromise);
    };

    Split100Question.prototype.submit = function() {
        if (!this.sizes) {
            // we've never moved the sliders, so don't bother sending anything
            var deferred = $.Deferred();
            deferred.resolve();
            return deferred;
        }
        var percents = [];
        for (var i = this.context.users.length - 1; i >= 0; i--) {
            var user = this.context.users[i];
            var size = this.sizes[i];
            user.pct = this.displayToReal(size);
            percents.push({'userid': user.id, 'pct': user.pct});
        }

        var data = {
            teamevalid: this.teameval,
            questionid: this.questionID,
            percents: percents
        };

        var promises = Ajax.call([{
            methodname: 'teamevalquestion_split100_submit_response',
            args: data
        }]);

        return promises[0];
    };

    return Split100Question;

});