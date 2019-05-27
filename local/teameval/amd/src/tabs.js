define(['jquery'], function($) { return {

    initialise: function() {

        $('.local-teameval-containerbox .tab').each(function() {

            $(this).click(function(evt) {
                evt.preventDefault();
                $('.tab-content').hide();
                var id = $(this).data('tab-content-id');
                $('#'+id).trigger('viewWillAppear');
                $('#'+id).show();
                $('#'+id).trigger('viewDidAppear');
            });

        });

    }

};
});
