
// holt den wert des url parameters "name"
function getURLParameter(name,uri) {
    return decodeURIComponent((new RegExp('[?|&]' + name + '=' + '([^&;]+?)(&|#|;|$)').exec(uri)||[,""])[1].replace(/\+/g, '%20'))||null;
}


function infomail_handler () {
    var myuri = jQuery('.infomail__send').attr('href');
    var myid = getURLParameter('id',myuri);
    //alert("URI " + myuri + "ID " + myid);
    if ( myid === null ) return false;


    jQuery('#dokuwiki__footer').after('<div id="infomail_box"></div>');

    jQuery.post(DOKU_BASE + 'lib/exe/ajax.php',
        {
            'call': 'plugin_infomail',
            'id': myid
        },
        function (data) {
            jQuery('#infomail_box').html(data);
        }, 'html');

    return false;

}



jQuery(function() {
     // init preview button
    jQuery('.infomail__send').click(infomail_handler);
    jQuery('form#infomail_plugin').submit(infomail_handler);
    return false;
});
