define(['jquery', 'local_teameval/langen'], function($, LanGen) { return {

    initialise: function() {

        var developerButtons = $('<div class="local-teameval-developer-buttons" />');
        $('.local-teameval-containerbox').append(developerButtons);

        // Add a randomise button
        var randomiseButton = $('<button type="button">Randomise</button>');
        randomiseButton.click(function() {
            //randomise likert responses
            $('.teamevalquestion-likert-question-submission table.responses tbody tr').each(function() {
                var things = $(this).find('input');
                var rando = Math.floor(Math.random()*things.length);
                $(things[rando]).prop('checked', true);
            });

            $('.teamevalquestion-comment-container table.comments textarea').each(function() {
                $(this).val(LanGen.generateSentence());
            });

        });

        $('.local-teameval-developer-buttons').append(randomiseButton);
    }

};
});