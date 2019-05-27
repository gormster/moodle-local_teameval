define(['jquery'], function($) {

    // switch id: [switch jQueries]
    var overrides = {};

    function breakOverrides(el) {
        var overriddenBy = $('#'+el.data('override'));
        if(overriddenBy) {
            // reset
            // el.data('overridden-by', null);
            overriddenBy.trigger('setState', [null, 'break']);
        }
    }

    function overrideSubswitches(el, state) {
        var id = el.attr('id');
        if (overrides[id] !== undefined) {
            for (var i = 0; i < overrides[id].length; i++) {
                var o = overrides[id][i];
                o.trigger('setState', [state, 'override']);
                // o.data('overridden-by', el);
            }
        }
    }

    return {
        init: function(o) {

            o = $(o);

            var tristate = $(o).hasClass("tristate");

            var override = o.data('override');
            if (override !== undefined) {
                if (overrides[override] === undefined) {
                    overrides[override] = [];
                }
                overrides[override].push(o);
            }

            o.html(
'<div class="toggle">' +
'<svg class="loading-indicator" xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 1 1">' +
'    <g fill="black">' +
'       <rect x="0.45" y="0" width="0.1" height="0.37" rx="0.05" ry="0.05" />' +
'       <rect x="0.45" y="0" width="0.1" height="0.37" rx="0.05" ry="0.05" ' +
'           transform="rotate(-45 0.5 0.5)"  style="opacity: 1.0"/>' +
'       <rect x="0.45" y="0" width="0.1" height="0.37" rx="0.05" ry="0.05" ' +
'           transform="rotate(-90 0.5 0.5)"  style="opacity: 0.9"/>' +
'       <rect x="0.45" y="0" width="0.1" height="0.37" rx="0.05" ry="0.05" ' +
'           transform="rotate(-135 0.5 0.5)" style="opacity: 0.8" />' +
'       <rect x="0.45" y="0" width="0.1" height="0.37" rx="0.05" ry="0.05" ' +
'           transform="rotate(-180 0.5 0.5)" style="opacity: 0.7" />' +
'       <rect x="0.45" y="0" width="0.1" height="0.37" rx="0.05" ry="0.05" ' +
'           transform="rotate(-225 0.5 0.5)" style="opacity: 0.6" />' +
'       <rect x="0.45" y="0" width="0.1" height="0.37" rx="0.05" ry="0.05" ' +
'           transform="rotate(-270 0.5 0.5)" style="opacity: 0.5" />' +
'       <rect x="0.45" y="0" width="0.1" height="0.37" rx="0.05" ry="0.05" ' +
'           transform="rotate(-315 0.5 0.5)" style="opacity: 0.4" />' +
'       <animateTransform attributeName="transform" attributeType="XML" ' +
'           type="rotate" from="0 0.5 0.5" to="360 0.5 0.5" dur="1.5s" repeatCount="indefinite"/>' +
'    </g>' +
'</svg>' +
'</div>');

            if (tristate) {
                o.append('<svg class="reject" height="8" viewBox="0 0 10 10"><path d="M 1 1 L 8 8 M 1 8 L 8 1" /></svg>');
                o.append('<svg class="check" viewBox="0 0 10 10"><path d="M 1 6 L 4 8 L 8 1" fill="none" /></svg>');
            }

            var state = o.data('state');

            if (state) {
                o.addClass(state);
            }

            o.attr({
                'aria-role' : 'checkbox',
                'aria-checked': o.hasClass('checked') ? 'true' : 'false'
            });

            o.on('click', function(evt) {

                var newState;

                if (tristate) {

                    if (o.hasClass('rejected') || o.hasClass('checked')) {
                        // always go back to neutral
                        o.trigger('setState', [null, 'user']);

                    } else {

                        var target = $(evt.target);

                        if (target.hasClass('toggle')) {
                            //do nothing. clicking the toggle has no effect.
                            return;
                        }

                        var offsetX = evt.pageX - o.offset().left;


                        if (offsetX < o.width() / 2) {
                            newState = 'rejected';
                        } else {
                            newState = 'checked';
                        }

                        o.trigger('setState', [newState, 'user']);

                    }

                } else {
                    // switch always toggles

                    newState = o.hasClass('checked') ? null : 'checked';
                    o.trigger('setState', [newState, 'user']);

                }

            });

            o.on('showLoading', function() {
                o.find('.loading-indicator').show();
            });

            o.on('hideLoading', function() {
                o.find('.loading-indicator').hide();
            });


            o.on('setState', function (evt, newState, source) {

                var oldState = o.data('state');

                o.removeClass('rejected');
                o.removeClass('checked');

                if (newState) {
                    o.addClass(newState);
                    o.attr('aria-checked', newState == 'checked' ? 'true' : 'false');
                } else {
                    o.attr('aria-checked', 'mixed');

                }

                o.data('state', newState);

                if (source != 'auto') {
                    if (oldState != newState) {
                        o.trigger('changed');
                    }
                }

                if (source == 'user') {
                    breakOverrides(o);
                    overrideSubswitches(o, newState);
                }

                if (source == 'break') {
                    breakOverrides(o);
                }

                if (source == 'override') {
                    overrideSubswitches(o, newState);
                }


            });



        },

        resolveStates: function() {

            // This function is designed to be called after all switches have been initialised

            var changed = false;

            $.each(overrides, function(k, os) {

                var state = os[0].data('state');
                for (var i = os.length - 1; i >= 0; i--) {
                    var o = os[i];
                    if(o.data('state') != state) {
                        state = null;
                    }
                }

                var sw = $('#'+k);
                if (sw.length === 0) {
                    delete overrides[k];
                    return;
                }

                var oldState = sw.data('state');
                if(oldState != state) {
                    sw.trigger('setState', [state, 'auto']);
                    var newState = sw.data('state');
                    if (newState != state) {
                        window.console.log("Update state failed!");
                        return false;
                    }
                    changed = true;
                }

            });

            if(changed) {
                // repeat until stable
                this.resolveStates();
            }

        }


    };

});
