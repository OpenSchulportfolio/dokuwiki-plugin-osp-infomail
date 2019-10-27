var infomail = {
    $dialog: null,

    /**
     * Attach click handler to our link
     */
    init: function () {
        jQuery('a.plugin_infomail').click(infomail.initform);
    },

    /**
     * Initializes the form dialog on click
     *
     * @param {Event} e
     */
    initform: function (e) {
        e.stopPropagation();
        e.preventDefault();

        var url = new URL(e.target.href);
        // searchParams only works, when no URL rewriting takes place
        // from Dokuwiki - else there is no parameter id and this 
        // returns null
        var id = url.searchParams.get('id');
        if ( id === null ) {
            // Convert url to string an get the last part without 
            // any parameters from actions and the like
            url = String(url);
            id = url.split('/').pop().split('?')[0];
        }

        infomail.$dialog = jQuery('<div></div>');
        infomail.$dialog.dialog(
            {
                modal: true,
                title: LANG.plugins.infomail.formname + ' ' + id,
                minWidth: 680,
                height: "auto",
                close: function () {
                    infomail.$dialog.dialog('destroy')
                }
            }
        );

        jQuery.get(
            DOKU_BASE + 'lib/exe/ajax.php',
            {
                'call': 'infomail',
                'id': id
            },
            infomail.handleResult,
            'html'
        );
    },

    /**
     * Display the result and attach handlers
     *
     * @param {string} data The HTML
     */
    handleResult: function (data) {
        infomail.$dialog.html(data);
        infomail.$dialog.find('button[type=reset]').click(infomail.cancel);
        infomail.$dialog.find('button[type=submit]').click(infomail.send);
    },

    /**
     * Cancel the infomail form
     *
     * @param {Event} e
     */
    cancel: function (e) {
        e.preventDefault();
        e.stopPropagation();
        infomail.$dialog.dialog('destroy');
    },

    /**
     * Serialize the form and send it
     *
     * @param {Event} e
     */
    send: function (e) {
        e.preventDefault();
        e.stopPropagation();

        var data = infomail.$dialog.find('form').serialize();
        data = data + '&call=infomail';

        infomail.$dialog.html('...');
        jQuery.post(
            DOKU_BASE + 'lib/exe/ajax.php',
            data,
            infomail.handleResult,
            'html'
        );
    }
};
jQuery(infomail.init);
