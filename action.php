<?php

class action_plugin_infomail extends DokuWiki_Action_Plugin
{
    /** @inheritdoc */
    public function register(Doku_Event_Handler $controller)
    {
        // FIXME it's probably not cool to have all three events go to the same handler
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, '_handle');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, '_handle');
        $controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE', $this, '_handle');
    }

    /**
     * Send the email
     *
     * @param Doku_Event $event
     */
    public function _handle(Doku_Event $event)
    {
        // the basic handling is the same for all events
        // we either show the form or handle the post data

        // FIXME change this into one string
        if (!in_array($event->data, array('infomail', 'plugin_infomail'))) {
            return;
        }
        $event->preventDefault();
        // for this event we only signal, that we will handle this mode
        if ($event->name === 'ACTION_ACT_PREPROCESS') {
            return;
        }
        $event->stopPropagation();

        global $INPUT;

        /*
        if ($INPUT->server->str('REQUEST_METHOD') === 'POST') {
            isset($_POST['sectok']) &&
            !($err = $this->_handle_post())) {
            if ($event->name === 'AJAX_CALL_UNKNOWN') {
                // To signal success to AJAX. 
                $this->_show_success();
                return;
            }
            echo 'Thanks for recommending our site.';
            return;
        }
        */

        /* To display msgs even via AJAX. */
        echo ' ';
        if (isset($err)) {
            msg($err, -1);
        }
        echo $this->getForm();
    }

    /**
     * Builds the Mail Form
     */
    protected function getForm()
    {
        global $INPUT;
        $id = getID(); // we may run in AJAX context
        if ($id === '') throw new \RuntimeException('No ID given');

        $form = new \dokuwiki\Form\Form([
            'action' => wl($id, ['do' => 'infomail']),
            'id' => 'infomail_plugin', #FIXME bad ID
        ]);
        $form->setHiddenField('id', $id); // we need it for the ajax call

        if ($INPUT->server->has('REMOTE_USER')) {
            global $USERINFO;
            $form->setHiddenField('s_name', $USERINFO['name']);
            $form->setHiddenField('s_email', $USERINFO['mail']);
        } else {
            $form->addTextInput('s_name', $this->getLang('yourname'))->addClass('edit');
            $form->addTextInput('s_email', $this->getLang('youremailaddress'))->addClass('edit');
        }

        //get default emails from config
        $lists = explode('|', $this->getConf('default_recipient'));
        $lists = array_filter($lists, 'mail_isvalid');
        // get simple listfiles from pages and add them
        $lists = array_merge($lists, []); //FIXME this needs to come from a central function (in admin)

        if ($lists) {
            array_unshift($lists, ''); // add empty option
            $form->addDropdown('r_predef', $lists, $this->getLang('bookmarks'));
        }

        $form->addTextInput('r_email', $this->getLang('recipients'))->addClass('edit');
        $form->addTextInput('subject', $this->getLang('subject'))->addClass('edit');
        $form->addTextarea('comment', $this->getLang('message'))->attr('rows', '8')->attr('cols', '10')->addClass('edit');

        /** @var helper_plugin_captcha $captcha */
        $captcha = plugin_load('helper', 'captcha');
        if ($captcha) $form->addHTML($captcha->getHTML());

        $form->addCheckbox('archiveopt', $this->getLang('archive'))->addClass('edit');

        $form->addTagOpen('div')->addClass('buttons');
        $form->addButton('submit', $this->getLang('send_infomail'))->attr('type', 'submit')->attr('id', 'infomail__sendmail');
        $form->addButton('cancel', $this->getLang('cancel_infomail'))->attr('type', 'cancel')->attr('id', 'infomail__cancel');
        $form->addTagClose('div');

        return $form->toHTML();
    }

    /**
     *  Validate input and send mail if everything is ok
     */
    protected function _handle_post()
    {
        global $conf;

        $helper = null;
        if (@is_dir(DOKU_PLUGIN . 'captcha')) $helper = plugin_load('helper', 'captcha');
        if (!is_null($helper) && $helper->isEnabled() && !$helper->check()) {
            return 'Wrong captcha';
        }
        /* Get recipients */
        $all_recipients = array();
        if (isset($_POST['r_email'])) {
            $all_recipients = explode(" ", $_POST['r_email']);
        }
        foreach ($all_recipients as $addr) {
            $addr = trim($addr);
            if (mail_isvalid($addr)) {
                $all_recipients_valid[] = $addr;
            }

        }
        if (isset($_POST['r_predef']) && mail_isvalid($_POST['r_predef'])) {
            $all_recipients_valid[] = $_POST['r_predef'];
        } elseif (isset($_POST['r_predef']) && file_exists(rtrim($conf['datadir'], "/") . "/wiki/infomail/list_" . $_POST['r_predef'] . ".txt")) {
            $listfile_content = file_get_contents(rtrim($conf['datadir'], "/") . "/wiki/infomail/list_" . $_POST['r_predef'] . ".txt");
            preg_match_all("/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i", $listfile_content, $simple_mails);
            foreach ($simple_mails[0] as $addr) {
                if (mail_isvalid($addr)) {
                    $all_recipients_valid[] = $addr;
                }
            }
        }

        /* Validate input. */
        if (count($all_recipients_valid) == 0) {
            return $this->getLang('novalid_rec');
        }

        if (!isset($_POST['s_name']) || trim($_POST['s_name']) === '') {
            return 'Invalid sender name submitted';
        }
        $s_name = $_POST['s_name'];

        $all_recipients_valid = array_unique($all_recipients_valid);

        $default_sender = $this->getConf('default_sender');

        if (!isset($_POST['s_email']) || !mail_isvalid($_POST['s_email'])) {
            if (!isset($default_sender) || !mail_isvalid($default_sender)) {
                return 'Ungültige Sender-Mailadresse angegeben' . $_POST['s_email'];
            } else {
                if (trim($this->getConf('default_sender_displayname')) != "") {
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
        if (file_exists($conf['savedir'] . "/pages/wiki/infomail/template.txt")) {
            $mailtext = file_get_contents($conf['savedir'] . "/pages/wiki/infomail/template.txt");
        } else {
            $mailtext = file_get_contents(dirname(__FILE__) . '/template.txt');
        }
        $parts = explode("###template_begin###", $mailtext);
        $mailtext = $parts[1];

        // shorturl hook
        if (!plugin_isdisabled('shorturl')) {
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
                     'URL' => $pageurl,
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
        $all_recipients = "";
        foreach ($all_recipients_valid as $mail) {
            $recipient = '<' . $mail . '>';
            mail_send($recipient, $subject, $mailtext, $sender);
            if ($this->getConf('logmails')) {
                $this->mail_log($recipient, $subject, $mailtext, $sender);
            }
            $all_recipients .= $recipient;
        }
        if ($archiveon) {
            $this->mail_archive($all_recipients, $subject, $mailtext, $sender);
        }
        return false;
    }

    /**
     * show success message
     */
    protected function _show_success()
    {

        $html = '<form id="infomail_plugin" accept-charset="utf-8" method="post" action="?do=infomail">';
        $html .= '<div class="no">';
        $html .= ' <span class="ui-icon ui-icon-circle-check" style="float: left; margin: 0 7px 50px 0;"></span>';
        $html .= '<p>Ihre Nachricht wurde verschickt.</p><input type="submit" class="button" value="Schliessen" name="do[cancel]"/></div></form>';
        print $html;
    }

    /*
     * Logging infomails as Wikipages when configured so
     */
    protected function mail_archive($recipient, $subject, $mailtext, $sender)
    {
        global $conf;
        $targetdir = $conf['cachedir'] . "/infomail-plugin/archive/";
        if (!is_dir($targetdir)) {
            mkdir($targetdir);
        }

        $t = time();
        $date = strftime("%d.%m.%Y, %H:%M", $t);
        $mailtext = "Von:   $sender\nAn:    $recipient\nDatum: $date\n\n" . $mailtext;

        $filename = strftime("%Y%m%d%H%M%S", $t) . "_infomail.txt";
        $archfile = $targetdir . $filename;
        io_saveFile($archfile, "$mailtext\n", true);

    }

    /*
    * Logging infomails as Wikipages when configured so
    */
    protected function mail_log($recipient, $subject, $mailtext, $sender)
    {
        global $conf;
        $targetdir = $conf['cachedir'] . "/infomail-plugin/log/";
        $logfile = $targetdir . "infomail.log";
        if (!is_dir($targetdir)) {
            mkdir($targetdir);
        }

        $t = time();
        $log = $t . "\t" . strftime($conf['dformat'], $t) . "\t" . $_SERVER['REMOTE_ADDR'] . "\t" . $sender . "\t" . $recipient;
        io_saveFile($logfile, "$log\n", true);

    }

}
