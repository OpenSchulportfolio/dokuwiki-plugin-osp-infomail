<?php
require_once DOKU_PLUGIN . 'admin.php';

class admin_plugin_infomail extends DokuWiki_Admin_Plugin {
    function getInfo(){
        return confToHash(dirname(__FILE__).'/plugin.info.txt');
    }

    function getMenuText() {
        return $this->getLang('infomail_admin_menu_text');
    }

    function handle() {
        global $conf;
        if (isset($_REQUEST['infomail_simple_new']) && $_REQUEST['infomail_simple_new'] != "" ) {
                $newlist = ":wiki:infomail:list_" . $_REQUEST['infomail_simple_new'];
                send_redirect(wl($newlist,'',true));
        }
        if (isset($_REQUEST['infomail_edit_tpl']) && $_REQUEST['infomail_edit_tpl'] == "yes" ) {
                if (file_exists(rtrim($conf['datadir'],"/")."/wiki/infomail/template.txt" )) {
                    $tplid = ":wiki:infomail:template";
                    send_redirect(wl($tplid,'',true));
                } else {
                    $tplsrc = dirname(__FILE__).'/template.txt';
                    $tpldstdir = rtrim($conf['datadir'],"/")."/wiki/infomail/";
                    $tpldst = rtrim($conf['datadir'],"/")."/wiki/infomail/template.txt";
                    if (!is_dir($tpldstdir)) {
                        @mkdir($tpldstdir);
                    }
                    copy($tplsrc, $tpldst);
                    $tplid = ":wiki:infomail:template";
                    send_redirect(wl($tplid,'',true));
                }
        }
    }

    function html() {
        global $ID;
        global $conf;

        $html = "<h1>" .$this->getLang('admin_title') ."</h1>";
        $html .= $this->getLang('admin_desc') ;
        $html .= "<h2>" . $this->getLang('infomail_listoverview') . "</h2>";
        print $html;
        $html = "";

        $form = new Doku_Form('infomail_plugin_admin');
        $form->addElement(form_makeTextField('infomail_simple_new', $s_name, $this->getLang('newsimplelist')));
        $form->addElement(form_makeButton('submit', '', $this->getLang('createnewsimplelist')));
        $form->printForm();

        $listdir = rtrim($conf['datadir'],"/")."/wiki/infomail/";

        $simple_lists = array();
        if ($handle = @opendir($listdir)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != ".." ) {
                    if (substr($file, 0, 5) == "list_" ) {
                        $list_name = substr($file, 5, -4);
                        $simple_lists["$list_name"] =substr($file,0,-4);
                    }
                }
            }
            closedir($handle);
        }

        $html .= "<ul>\n";
        foreach ($simple_lists as $name => $listid) {
            $html .= "<li>". html_wikilink("wiki:infomail:$listid", "$name") . "</li>\n";
        }
        $html .= "</ul>\n";

        print $html;

        print "<h2>" .$this->getLang('infomail_tpl') . "</h2>";

        $form = new Doku_Form('infomail_plugin_admin_tpl');
        $form->addHidden('infomail_edit_tpl', "yes");
        $form->addElement(form_makeButton('submit', '', $this->getLang('infomail_edit_tpl')));
        $form->printForm();

    }
}

