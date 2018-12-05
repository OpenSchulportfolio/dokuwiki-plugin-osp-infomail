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

        // FIXME make this is still not very good, it should use one of the search_* mechanisms instead
        $listdir = rtrim($conf['datadir'], '/') . '/wiki/infomail/';
        $lists = glob("$listdir/list_*.txt");
        $lists = array_map(function ($item) {
            return basename($item, '.txt');
        }, $lists);

        // output the available lists
        echo '<ul>';
        foreach ($lists as $listid) {
            $name = substr($listid, 5);
            echo '<li>' . html_wikilink("wiki:infomail:$listid", "$name") . '</li>';
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

