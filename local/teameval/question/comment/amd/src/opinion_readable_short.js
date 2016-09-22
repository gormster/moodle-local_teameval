define(['jquery'], function($) {

    var _showing = false;

    return {

        init: function() {

            $('.teamevalquestion-comment-show-comment').each(function() {
                var fullComment = $(this).closest('.teamevalquestion-comment-container')
                    .find('.teamevalquestion-comment-full-comment');
                $(this).data('fullComment', fullComment);
                fullComment.detach();
            });

            $('.teamevalquestion-comment-show-comment').click(function(evt) {

                if(!_showing) {

                    var fullComment = $(this).data('fullComment');

                    $('.local-teameval-containerbox').append(fullComment);

                    _showing = true;

                    var position = $(evt.target).position();

                    var left = position.left;
                    var maxLeft = $('.local-teameval-containerbox').width() / 2 - 15;
                    if (left > maxLeft) {
                        left = maxLeft;
                    }

                    fullComment.css({
                        'position':'absolute',
                        'top': position.top,
                        'left': left
                    });

                    evt.stopPropagation();

                    $(document).click(function() {

                        fullComment.detach();
                        _showing = false;

                    });

                }

            });
        }

    };

});
