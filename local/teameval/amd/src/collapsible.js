define(['jquery'], function($) {

    var defaultSettings = {
        'selector' : '.collapsible',
        'target'   : '.label',
        'expanded' : 'expanded',
        'collapsed': 'collapsed',
    };

    return {

        init: function(settings) {
            var S = $.extend({}, defaultSettings, settings);
            $(S.selector).each(function() {
                // this can sometimes be called multiple times on the same element
                // we want to ignore subsequent calls
                var initialised = $(this).data('collapsible-initialised');

                if (initialised) {
                    return;
                }

                $(this).data('collapsible-initialised', true);
                $(this).on('click', S.target, function(evt){
                    var collapser = $(evt.delegateTarget);
                    if (collapser.hasClass(S.expanded)) {
                        collapser.removeClass(S.expanded);
                        collapser.addClass(S.collapsed);
                    } else {
                        collapser.removeClass(S.collapsed);
                        collapser.addClass(S.expanded);
                    }
                });

            });
        }

    };

});
