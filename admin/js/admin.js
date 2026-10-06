(function($){
    'use strict';

    $(document).on('click', '.sk-delete', function(e){
        if (!confirm(SKBlogger.i18n.confirm_delete)) { e.preventDefault(); }
    });

    $('#sk-process-now').on('click', function(){
        var $btn = $(this);
        $btn.prop('disabled', true).text(SKBlogger.i18n.processing);
        $.ajax({
            url: SKBlogger.rest + '/process',
            method: 'POST',
            beforeSend: function(xhr){ xhr.setRequestHeader('X-WP-Nonce', SKBlogger.nonce); }
        }).always(function(){
            location.reload();
        });
    });

})(jQuery);