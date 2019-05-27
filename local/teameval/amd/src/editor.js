define(['jquery'], function($) {

    return {
        saveAll: function(form) {
            //tinyMCE
            if (window.tinyMCE !== undefined) {
                $(form).find('.feditor textarea').each(function() {
                    var id = $(this).attr('id');
                    var editor = window.tinyMCE.getInstanceById(id);
                    if (editor !== undefined) {
                        editor.save();
                    }
                });
            }

            // atto doesn't need it
            // neither does textarea
            // there really should be some official API for this, ffs
        }
    };

});