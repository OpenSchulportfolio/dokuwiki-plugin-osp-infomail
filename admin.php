<?php

class admin_plugin_infomail extends DokuWiki_Admin_Plugin
{

    const TPL = 'wiki:infomail:template'; // FIXME move to helper

    /** @inheritdoc */
    public function handle()
    {
        global $INPUT;

        // redirect to list page
        if ($INPUT->filter('trim')->str('infomail_simple_new')) {
            $newlist = cleanID(":wiki:infomail:list_" . $INPUT->filter('trim')->str('infomail_simple_new'));
            send_redirect(wl($newlist, '', true, '&'));
        }

        // redirect to template page, create default when empty
        if ($INPUT->bool('infomail_edit_tpl')) {
            if (!page_exists(self::TPL)) {
                saveWikiText(self::TPL, io_readFile(__DIR__ . '/template.txt'), 'autocreated');
            }
            send_redirect(wl(self::TPL, '', true, '&'));
        }
    }

    /** @inheritdoc */
    public function html()
    {
        global $conf;

        echo $this->locale_xhtml('intro');

        // FIXME could be changed into new Form mechanism
        echo '<h2>' . $this->getLang('infomail_listoverview') . '</h2>';
        $form = new Doku_Form('infomail_plugin_admin');
        $form->addElement(form_makeTextField('infomail_simple_new', '', $this->getLang('newsimplelist')));
        $form->addElement(form_makeButton('submit', '', $this->getLang('createnewsimplelist')));
        $form->printForm();

        /** @var helper_plugin_infomail $helper */
        $helper = plugin_load('helper', 'infomail');

        // output the available lists
        $lists = $helper->getLists();
        echo '<ul>';
        foreach ($lists as $list) {
            echo '<li>' . html_wikilink("wiki:infomail:list_$list", $list) . '</li>';
        }
        echo '</ul>';


        // FIXME could be changed to use new Form mechanism
        echo '<h2>' . $this->getLang('infomail_tpl') . '</h2>';
        $form = new Doku_Form('infomail_plugin_admin_tpl');
        $form->addHidden('infomail_edit_tpl', "yes");
        $form->addElement(form_makeButton('submit', '', $this->getLang('infomail_edit_tpl')));
        $form->printForm();
    }
}

