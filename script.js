
// holt den wert des url parameters "name"
function getURLParameter(name,uri) {
    return decodeURIComponent((new RegExp('[?|&]' + name + '=' + '([^&;]+?)(&|#|;|$)').exec(uri)||[,""])[1].replace(/\+/g, '%20'))||null;
}


function infomail_handler(event) {
    var aktion = event.data.aktion;

    if ( aktion === "initform") {
        var myuri = jQuery('.infomail__send').attr('href');
        var myid = getURLParameter('id',myuri);
        if ( myid === null ) return false;

        jQuery('#dokuwiki__footer').after('<div id="infomail_box"></div>');
        jQuery('#infomail_box').dialog(
            {
                modal: true,
                title: "Infomail: " + myid,
                minWidth: 680,
                height: "auto",
                dialogClass: "infomail-no-close"
            }
        );
        //alert("URI " + myuri + "ID " + myid + "PARAM " + aktion);
        jQuery.post(DOKU_BASE + 'lib/exe/ajax.php',
            {
                'call': 'plugin_infomail',
                'id': myid
            },
            function (data) {
                jQuery('#infomail_box').html(data);
                jQuery('#infomail__sendmail').click({aktion: "submitform"},infomail_handler);
                //jQuery('#infomail__cancel').click(infomail_cancel);
            }, 'html');
    }

    if ( aktion === "submitform" ) {
        var param_subject = jQuery('#infomail_plugin input[name=subject]').val();
        var param_comment = jQuery('#infomail_plugin textarea[name=comment]').val();
        var param_sectok = jQuery('#infomail_plugin input[name=sectok]').val();
        var param_r_email = jQuery('#infomail_plugin input[name=r_email]').val();
        var param_id = jQuery('#infomail_plugin input[name=id]').val();
        var param_s_name = jQuery('#infomail_plugin input[name=s_name]').val();
        var param_s_email = jQuery('#infomail_plugin input[name=s_email]').val();
        var param_r_predef = jQuery('#infomail_plugin select[name=r_predef]').val();
        var param_archiveopt = jQuery('#infomail_plugin select[name=archiveopt]').val();

//        alert(param_subject + " " + param_comment + " " + param_sectok + " " + param_id + " " + param_r_email + " " + param_s_name + " " + param_s_email + " " + param_r_predef);
        jQuery.post(DOKU_BASE + 'lib/exe/ajax.php',
            {
                'call': 'plugin_infomail',
                'id': param_id,
                'subject': param_subject,
                'comment': param_comment,
                'sectok': param_sectok,
                'r_email': param_r_email,
                's_name': param_s_name,
                's_email': param_s_email,
                'r_predef': param_r_predef,
                'archiveopt': param_archiveopt
            },
            function (data) {
                jQuery('#infomail_box').html(data);
                jQuery('#infomail__sendmail').click({aktion: "submitform"},infomail_handler);
                //jQuery('#infomail__cancel').click(infomail_cancel);
            }, 'html');
    }
    return false;

}



jQuery(function() {
     // init preview button
    jQuery('.infomail__send').click({aktion: "initform"},infomail_handler);
    jQuery('form.btn_infomail input').click({aktion: "initform"},infomail_handler);
    return false;
});
