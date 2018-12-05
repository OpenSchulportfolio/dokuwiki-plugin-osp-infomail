<?php

class helper_plugin_infomail extends DokuWiki_Plugin
{

    const TPL = 'wiki:infomail:template';

    /**
     * Return info to construct a link
     *
     * @todo an implementation as MenuItem would be nice too
     * @return array
     */
    public function getLink()
    {
        global $ID;

        $attr['href'] = wl($ID, ['do' => 'infomail'], false, '&');
        $attr['class'] = 'plugin_infomail';
        $attr['rel'] = 'no-follow';

        return array(
            'goto' => $ID,
            'text' => $this->getLang('name'),
            'attr' => $attr,
        );
    }

    /**
     * Loads the recipients from a given list
     *
     * Returns an empty list if the list can't be found
     *
     * @todo this uses a very simplistic regexp to get the mails from the page
     * @param string $list
     * @return string[]
     */
    public function loadList($list)
    {
        $lid = cleanID("wiki:infomail:list_$list");
        if (!page_exists($lid)) return [];

        $content = rawWiki($lid);
        preg_match_all("/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i", $content, $matches);
        return (array)$matches[0];
    }

    /**
     * Returns a list of all available lists
     *
     * @return string[] lists (without list prefix or .txt suffix)
     * @fixme make this is still not very good, it should use one of the search_* mechanisms instead
     */
    public function getLists()
    {
        global $conf;
        $listdir = rtrim($conf['datadir'], '/') . '/wiki/infomail/';
        $lists = glob("$listdir/list_*.txt");
        $lists = array_map(function ($item) {
            return substr(basename($item, '.txt'), 5);
        }, $lists);

        return $lists;
    }

    /**
     * Load the mail template
     *
     * @todo using a real code field and the parser would be better
     * @return string
     */
    public function loadTemplate()
    {
        if (page_exists(self::TPL)) {
            $file = wikiFN(self::TPL);
        } else {
            $file = __DIR__ . '/template.txt';
        }
        $mailtext = io_readFile($file);
        $parts = explode("###template_begin###", $mailtext);
        $mailtext = $parts[1];

        return $mailtext;
    }

}
