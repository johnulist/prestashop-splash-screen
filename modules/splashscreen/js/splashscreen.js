$(document).ready(function() {
    
    if($.isFunction($.cookie) && $.isFunction($.fancybox)) {
    
        if(!$.cookie('PrestaShop-' + splashScreen[0]) || $.cookie('PrestaShop-' + splashScreen[0]) != 'accepted') {
            var modal = $('#splashScreen');
            $.fancybox.open(modal, {
                minWidth: '30%',
                maxWidth: '70%',
                closeBtn: false,
                helpers: {
                    overlay: {
                        closeClick: false
                    }
                }
            });
            
            $('#splashScreen .btn-success').on('click', function() {
                $.cookie('PrestaShop-' + splashScreen[0], 'accepted', {
                    expires: splashScreen[1],
                    path: '/'
                });
                $.fancybox.close();
                return false;
            });
        }
    }
    
});