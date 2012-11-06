<?php
require_once DOKU_PLUGIN . 'action.php';
require_once DOKU_INC . 'inc/form.php';

class action_plugin_infomail extends DokuWiki_Action_Plugin {
    function getInfo(){
        return confToHash(dirname(__FILE__).'/plugin.info.txt');
    }

    function register(&$controller) {
        foreach (array('ACTION_ACT_PREPROCESS', 'AJAX_CALL_UNKNOWN',
                       'TPL_ACT_UNKNOWN') as $event) {
            $controller->register_hook($event, 'BEFORE', $this, '_handle');
        }
    }

    function _handle(&$event, $param) {
        if (!in_array($event->data, array('infomail', 'plugin_infomail'))) {
            return;
        }

        $event->preventDefault();

        if ($event->name === 'ACTION_ACT_PREPROCESS') {
            return;
        }

        $event->stopPropagation();

        if ($_SERVER['REQUEST_METHOD'] == 'POST' &&
            isset($_POST['sectok']) &&
            !($err = $this->_handle_post())) {
            if ($event->name === 'AJAX_CALL_UNKNOWN') {
                /* To signal success to AJAX. */
                header('HTTP/1.1 204 No Content');
                return;
            }
            echo 'Thanks for recommending our site.';
            return;
        }
        /* To display msgs even via AJAX. */
        echo ' ';
        if (isset($err)) {
            msg($err, -1);
        }
        $this->_show_form();
    }

    function _show_form() {
        global $conf;
        global $ID;
        $r_name  = isset($_REQUEST['r_name']) ? $_REQUEST['r_name'] : '';
        $r_email = isset($_REQUEST['r_email']) ? $_REQUEST['r_email'] : '';
        $s_name  = isset($_REQUEST['s_name']) ? $_REQUEST['s_name'] : '';
        $s_email = isset($_REQUEST['s_email']) ? $_REQUEST['s_email'] : '';
        if (isset($_REQUEST['id'])) {
            $id  = $_REQUEST['id'];
        } else {
            global $ID;
            if (!isset($ID)) {
                msg('Unknown page', -1);
                return;
            }
            $id  = $ID;
        }
        $form = new Doku_Form('infomail_plugin', '?do=infomail');
        $form->addHidden('id', $id);
        $form->startFieldset($this->getLang('formname') . " " . hsc($id) );
        if (isset($_SERVER['REMOTE_USER'])) {
            global $USERINFO;
            $form->addHidden('s_name', $USERINFO['name']);
            $form->addHidden('s_email', $USERINFO['mail']);
        } else {
            $form->addElement(form_makeTextField('s_name', $s_name, $this->getLang('yourname')));
            $form->addElement(form_makeTextField('s_email', $s_email, $this->getLang('youremailaddress')));
        }

        //get default emails from config
        $r_predef = array();
        $r_predef = explode('|', $this->getConf('default_recipient'));
        foreach ($r_predef as $addr ) {
            if (mail_isvalid($addr)) {
                $r_predef_valid[] = $addr;
            }
        }

        // get simple listfiles from pages
        $listdir = rtrim($conf['datadir'], "/")."/wiki/infomail/";
        $simple_lists = array();
        if ($handle = @opendir($listdir)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != ".." ) {
                    if (substr($file, 0, 5) == "list_" ) {
                        $simple_lists[] =substr($file,5,-4);
                    }
                }
            }
            closedir($handle);
        }

        // print selection
        $morerec = "";
        if(count($r_predef_valid)>0) {
            array_unshift($r_predef_valid, $this->getLang('noneselected'));
            $r_predef_valid = array_merge($r_predef_valid, $simple_lists);
            $form->addElement(form_makeListboxField('r_predef', $r_predef_valid, '', $this->getLang('bookmarks')));
            $morerec = $this->getLang('more_rec_fill');
        } elseif(count($simple_lists)>0) {
            array_unshift($simple_lists, $this->getLang('noneselected'));
            $form->addElement(form_makeListboxField('r_predef', $simple_lists, '', $this->getLang('bookmarks')));
            $morerec = $this->getLang('more_rec_fill');
        }

        $form->addElement(form_makeTextField('r_email', $r_email, $morerec . $this->getLang('recipients')));
        $form->addElement(form_makeTextField('subject', $subject, $this->getLang('subject')));
        $form->addElement('<label><span>'.$this->getLang('message').'</span>'.
                          '<textarea name="comment" rows="8" cols="10" ' .
                          'class="edit">' . $comment . '</textarea></label>');
        $helper = null;
        if(@is_dir(DOKU_PLUGIN.'captcha')) $helper = plugin_load('helper','captcha');
        if(!is_null($helper) && $helper->isEnabled()){
            $form->addElement($helper->getHTML());
        }

        $form->addElement(form_makeButton('submit', '', $this->getLang('send_infomail')));
        $form->addElement(form_makeButton('submit', 'cancel', $this->getLang('cancel_infomail')));
        $form->printForm();
    }

    function _handle_post() {
        global $conf;
        global $USERINFO;

        $helper = null;
        if(@is_dir(DOKU_PLUGIN.'captcha')) $helper = plugin_load('helper','captcha');
        if(!is_null($helper) && $helper->isEnabled() && !$helper->check()) {
            return 'Wrong captcha';
        }
        /* Get recipients */
        $all_recipients = array();
        if (isset($_POST['r_email'])) {
            $all_recipients = explode(" ", $_POST['r_email']);
        }
        foreach ($all_recipients as $addr ) {
            $addr = trim($addr);
            if (mail_isvalid($addr)) {
                $all_recipients_valid[] = $addr;
            }

        }
        if( isset($_POST['r_predef']) && mail_isvalid($_POST['r_predef']) )  {
            $all_recipients_valid[] =  $_POST['r_predef'];
        } elseif (isset($_POST['r_predef']) && file_exists(rtrim($conf['datadir'],"/")."/wiki/infomail/list_". $_POST['r_predef']. ".txt")) {
                $listfile_content = file_get_contents(rtrim($conf['datadir'],"/")."/wiki/infomail/list_". $_POST['r_predef'] .".txt");
                preg_match_all("/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i", $listfile_content, $simple_mails);
                foreach($simple_mails[0] as $addr) {
                    if (mail_isvalid($addr)) {
                        $all_recipients_valid[] = $addr;
                    }
                }
            }
        /* Validate input. */
        if ( count($all_recipients_valid) == 0 ) {
            return $this->getLang('novalid_rec');
        }

        if (!isset($_POST['s_name']) || trim($_POST['s_name']) === '') {
            return 'Invalid sender name submitted';
        }
        $s_name = $_POST['s_name'];

        $all_recipients_valid =  array_unique($all_recipients_valid);

        $default_sender = $this->getConf('default_sender');

        if (!isset($_POST['s_email']) || !mail_isvalid($_POST['s_email'])) {
            if (!isset($default_sender) || !mail_isvalid($default_sender)) {
                return 'Ungültige Sender-Mailadresse angegeben' . $_POST['s_email'];
            } else {
                if (trim($this->getConf('default_sender_displayname')) != "" ) {
                    $sender = $this->getConf('default_sender_displayname') . " " . ' <' . $this->getConf('default_sender') . '>';
                } else {
                    $sender = $s_name . " " . ' <' . $this->getConf('default_sender') . '>';
                }
            }
        } else {
            $sender = $s_name . ' <' . $_POST['s_email'] . '>';
        }

        if (!isset($_POST['id']) || !page_exists($_POST['id'])) {
            return 'Invalid page submitted';
        }
        $page = $_POST['id'];

        $comment = isset($_POST['comment']) ? $_POST['comment'] : null;

        /* Prepare mail text. */
        if (file_exists($conf['savedir']."/pages/wiki/infomail/template.txt")) {
            $mailtext = file_get_contents($conf['savedir']."/pages/wiki/infomail/template.txt");
        } else {
            $mailtext = file_get_contents(dirname(__FILE__).'/template.txt');
        }
        $parts = explode("###template_begin###", $mailtext);
        $mailtext = $parts[1];

        // shorturl hook
        if(!plugin_isdisabled('shorturl')) {
            $shorturl =& plugin_load('helper', 'shorturl');
            $shortID = $shorturl->autoGenerateShortUrl($page);
            $pageurl = wl($shortID, '', true);
        } else {
            $pageurl .= wl($page, '', true);
        }

        $subject = hsc($this->getConf('subjectprefix')) . " " . hsc($_POST['subject']);

        foreach (array('NAME' => $r_name,
                       'PAGE' => $page,
                       'SITE' => $conf['title'],
                       'SUBJECT' => $subject,
                       'URL'  => $pageurl,
                       'COMMENT' => $comment,
                       'AUTHOR' => $s_name) as $var => $val) {
            $mailtext = str_replace('@' . $var . '@', $val, $mailtext);
        }
        /* Limit to two empty lines. */
        $mailtext = preg_replace('/\n{4,}/', "\n\n\n", $mailtext);
        $mailtext = preg_replace('/\n\s{2}/', "\n", $mailtext);
        $mailtext = preg_replace('/^\s{2}/', "", $mailtext);
        /* Wrap mailtext at 78 chars */
        $mailtext = wordwrap($mailtext, 78);

        /* Perform stuff. */
        foreach ( $all_recipients_valid as $mail ) {
            $recipient = '<' . $mail . '>';
            mail_send($recipient, $subject, $mailtext, $sender);
        }
        return false;
    }
}
