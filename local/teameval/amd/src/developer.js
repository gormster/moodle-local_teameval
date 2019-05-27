define(['jquery', 'local_teameval/langen'], function($, LanGen) { return {

    initialise: function() {

        var developerButtons = $('<div class="local-teameval-developer-buttons" />');
        $('.local-teameval-containerbox').append(developerButtons);

        // Add a randomise button
        var randomiseButton = $('<button type="button">Randomise</button>');
        randomiseButton.click(function() {
            //randomise likert responses
            $('.teamevalquestion-likert-question-submission table.responses.grid tbody tr').each(function() {
                var things = $(this).find('input');
                var rando = Math.floor(Math.random()*things.length);
                $(things[rando]).prop('checked', true);
            });

            $('.teamevalquestion-likert-question-submission table.responses.waterfall').each(function() {
                var peeps = $(this).find('thead th:not(.criteria)').length;
                var things = $(this).find('tbody tr').length;
                for(var i = 0; i < peeps; i++) {
                    var rando = Math.floor(Math.random()*things);
                    $(this).find('tbody tr').eq(rando).find('input[type=radio]').eq(i).prop('checked',true);
                }
            });

            $('.teamevalquestion-comment-container table.comments textarea').each(function() {
                $(this).val(LanGen.generateSentence());
            });

            $('.teamevalquestion-split100').closest('.question-container').each(function() {
                var qObject = $(this).data('question');
                var sizes = qObject.sizes;
                sizes = sizes.map(function() {
                    return Math.random();
                });
                var total = sizes.reduce(function(a, b) {
                    return a + b;
                });
                sizes = sizes.map(function(v) {
                    return parseInt(v / total * 100);
                });
                qObject.sizes = sizes;
                qObject.updateView();
            });

        });

        $('.local-teameval-developer-buttons').append(randomiseButton);
    }

};
});