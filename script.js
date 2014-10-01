/**
 * @date 20130413 by Leo Eibler <dokuwiki@sprossenwanne.at> / http://www.eibler.at \n
 *                bugfix: config option Strikethrough \n
 */

/**
 * html-layout:
 *
 * +input[checkbox].todocheckbox
 * +span.todotext
 * -del
 * --span.todoinnertext
 * ---anchor with text or text only
 */

var InfomailPlugin = {
    /**
     * @brief onclick method for input element
     *
     * @param {jQuery} $chk the jQuery input element
     */
    infomail: function () {
            alert("Hallo");
            jQuery.post(
                DOKU_BASE + 'lib/exe/ajax.php',
                {call: 'plugin_infomail', name: 'local'},
                function(data) {
                    alert('Received response');
                },
                'json'
                );
    }
};

jQuery(function(){
    jQuery('a.infomailaction').click(function(){
        InfomailPlugin.infomail();
    });
});
