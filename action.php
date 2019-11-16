<?php

class action_plugin_infomail extends DokuWiki_Action_Plugin
{
    /** @inheritdoc */
    public function register(Doku_Event_Handler $controller)
    {
        // we handle AJAX and non AJAX requesat through the same handler
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleEvent');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handleEvent');
        $controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE', $this, 'handleEvent');
    }

    /**
     * @param Doku_Event $event
     */
    public function handleEvent(Doku_Event $event)
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

        // early output to trigger display msgs even via AJAX.
        echo ' ';
        tpl_flush();
        if ($INPUT->server->str('REQUEST_METHOD') === 'POST') {
            try {
                $this->sendMail();
                if ($event->name === 'AJAX_CALL_UNKNOWN') {
                    $this->ajaxSuccess(); // To signal success to AJAX.
                } else {
                    msg($this->getLang('thanks'), 1);
                }
                return; // we're done here
            } catch (\Exception $e) {
                msg($e->getMessage(), -1);
            }
        }

        echo $this->getForm();
    }

    /**
     * Builds the Mail Form
     */
    protected function getForm()
    {
        global $INPUT;

        /** @var helper_plugin_infomail $helper */
        $helper = plugin_load('helper', 'infomail');

        $id = getID(); // we may run in AJAX context
        if ($id === '') throw new \RuntimeException('No ID given');

        $form = new \dokuwiki\Form\Form([
            'action' => wl($id, ['do' => 'infomail'], false, '&'),
            'id' => 'infomail__plugin',
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
        $lists = array_merge($lists, $helper->getLists());

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

        //$form->addCheckbox('archiveopt', $this->getLang('archive'))->addClass('edit');

        $form->addTagOpen('div')->addClass('buttons');
        $form->addButton('submit', $this->getLang('send_infomail'))->attr('type', 'submit');
        $form->addButton('reset', $this->getLang('cancel_infomail'))->attr('type', 'reset');
        $form->addTagClose('div');

        return $form->toHTML();
    }

    /**
     * Validate input and send mail if everything is ok
     *
     * @throws Exception when something went wrong
     * @todo this method is still much too long
     */
    protected function sendMail()
    {
        global $conf;
        global $INPUT;

        /** @var helper_plugin_captcha $captcha */
        $captcha = plugin_load('helper', 'captcha');
        if ($captcha && !$captcha->check()) {
            throw new \Exception('Wrong Captcha');
        }

        if (!checkSecurityToken()) {
            throw new \Exception('Security token did not match');
        }

        /** @var helper_plugin_infomail $helper */
        $helper = plugin_load('helper', 'infomail');

        // Get recipients
        $all_recipients = explode(' ', $INPUT->str('r_email'));
        $bookmark = $INPUT->filter('trim')->str('r_predef');
        if ($bookmark) {
            // a bookmark may be a single address or a list
            if (mail_isvalid($bookmark)) {
                $all_recipients[] = $bookmark;
            } else {
                $all_recipients = array_merge($all_recipients, $helper->loadList($bookmark));
            }
        }
        // clean up recipients
        $all_recipients = array_map('trim', $all_recipients);
        $all_recipients = array_map('strtolower', $all_recipients);
        $all_recipients = array_filter($all_recipients, 'mail_isvalid');
        $all_recipients = array_unique($all_recipients);
        if (!$all_recipients) throw new \Exception($this->getLang('novalid_rec'));

        // Sender name
        $s_name = $INPUT->filter('trim')->str('s_name', $this->getConf('default_sender_displayname'));
        if ($s_name === '') throw new \Exception($this->getLang('err_sendername'));

        // Sender email
        $s_email = $INPUT->filter('trim')->str('s_email', $this->getConf('default_sender'), true);
        if (!mail_isvalid($s_email)) throw new \Exception($this->getLang('err_sendermail'));

        // named Sender
        $sender = "$s_name <$s_email>";

        // the page ID
        $id = $INPUT->filter('cleanID')->str('id');
        if ($id === '' || !page_exists($id)) throw new \Exception($this->getLang('err_page'));

        // comment
        $comment = $INPUT->str('comment');

        // shorturl hook
        /** @var helper_plugin_shorturl $shorturl */
        $shorturl = plugin_load('helper', 'shorturl');
        if ($shorturl) {
            $shortID = $shorturl->autoGenerateShortUrl($id);
            $pageurl = wl($shortID, '', true, '&');
        } else {
            $pageurl = wl($id, '', true, '&');
        }

        // subject
        $subject = $this->getConf('subjectprefix') . ' ' . $INPUT->str('subject');

        // prepare replacements
        $data = [
            'NAME' => $INPUT->str('r_name'),
            'PAGE' => $id,
            'SITE' => $conf['title'],
            'SUBJECT' => $subject,
            'URL' => $pageurl,
            'COMMENT' => $comment,
            'AUTHOR' => $s_name,
        ];

        // get the text
        $mailtext = $helper->loadTemplate();

        // Send mail
        $mailer = new Mailer();
        $mailer->bcc($all_recipients);
        $mailer->from($sender);
        $mailer->subject($subject);
        $mailer->setBody($mailtext, $data);
        $mailer->send();

        /* FIXME currently not implemented
        if ($this->getConf('logmails')) {
            $this->mail_log($recipient, $subject, $mailtext, $sender);
        }

        if ($archiveon) {
            $this->mail_archive($all_recipients, $subject, $mailtext, $sender);
        }
        */
    }

    /**
     * show success message in ajax mode
     */
    protected function ajaxSuccess()
    {
        echo '<form id="infomail_plugin" accept-charset="utf-8" method="post" action="?do=infomail">';
        echo '<div class="no">';
        echo '<span class="ui-icon ui-icon-circle-check" style="float: left; margin: 0 7px 50px 0;"></span>';
        echo '<p>' . $this->getLang('thanks') . '</p>';
        echo '<button type="reset" class="button">' . $this->getLang('close') . '</button>';
        echo '</div>';
        echo '</form>';
    }

    /*
     * Logging infomails as Wikipages when configured so
     *
     * @todo currently not used, needs adjustment
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
     *
     * @todo currently not used, needs adjustment
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
