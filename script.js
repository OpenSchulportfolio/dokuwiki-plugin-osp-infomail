/* Lib */

var infomail_ajax_call = 'plugin_infomail';

function sack_form(form, fnc) {
    var ajax = new sack(DOKU_BASE + 'lib/exe/ajax.php');
    ajax.setVar('call', infomail_ajax_call);
    function serializeByTag(tag) {
        var inps = form.getElementsByTagName(tag);
        for (var inp in inps) {
            if (inps[inp].name) {
                ajax.setVar(inps[inp].name, inps[inp].value);
            }
        }
    }
    serializeByTag('input');
    serializeByTag('textarea');
    serializeByTag('select');
    ajax.onCompletion = fnc;
    ajax.runAJAX();
    return false;
}

/* commented out for compatibility reasons
function bind(fnc, val) {
    return function () {
        return fnc(val);
    };
}
*/

function change_form_handler(forms, handler) {
    if (!forms) return;
    for (var formid in forms) {
        var form = forms[formid];
        form.onsubmit = bind(handler, form);
    }
}

/* Recommend */

function infomail_box(content) {
    var div = $('infomail_box');
    if (!div) {
        div = document.createElement('div');
        div.id = 'infomail_box';
    } else if (content === '') {
        div.parentNode.removeChild(div);
        return;
    }
    div.innerHTML = content;
    document.body.appendChild(div);
    return div;
}

function infomail_handle() {

    if (this.response === "AJAX call '" + infomail_ajax_call + "' unknown!\n") {
        /* No user logged in. */
        return;
    }
    if (this.responseStatus[0] === 204) {
        var box = infomail_box('<form id="infomail_plugin" accept-charset="utf-8" method="post" action="?do=infomail"><div class="no"><fieldset class="infomailok"> <legend>Mail versandt...</legend<p>Ihre Nachricht wurde verschickt.</p><input type="submit" class="button" value="Schliessen" name="do[cancel]"/></fieldset></div></form>');
    } else {

        var box = infomail_box(this.response);
        box.getElementsByTagName('label')[0].focus();
        change_form_handler(box.getElementsByTagName('form'),
                            function (form) {return sack_form(form, infomail_handle); });
    }
    var inputs = box.getElementsByTagName('input');
    inputs[inputs.length - 1].onclick = function() {infomail_box(''); return false;};
}

addInitEvent(function () {
                change_form_handler(getElementsByClass('btn_infomail', document, 'form'),
                                    function (form) {return sack_form(form, infomail_handle); });
             });
