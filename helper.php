<?php

class helper_plugin_infomail extends DokuWiki_Plugin
{

    const TPL = 'wiki:infomail:template';

    /**
     * Loads the recipients from a given list
     *
     * Returns an empty list if the list can't be found
     *
     * @param string $list
     * @return string[]
     */
    public function loadList($list)
    {
        return [];
    }

    /**
     * Returns a list of all available lists
     *
     * @return string[]
     */
    public function getLists()
    {
        return [];
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
