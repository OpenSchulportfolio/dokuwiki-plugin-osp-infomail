<?php 
/*
 * plugin should use this method to register its handlers 
 * with the dokuwiki's event controller
 */

require_once DOKU_PLUGIN . 'action.php';
require_once DOKU_INC . 'inc/form.php';

class action_plugin_infomail extends DokuWiki_Action_Plugin {

function register(Doku_Event_Handler $controller) {
    foreach (array('ACTION_ACT_PREPROCESS', 'AJAX_CALL_UNKNOWN', 'TPL_ACT_UNKNOWN') as $event) {
            $controller->register_hook($event, 'BEFORE', $this, '_handle');
    }
}
    
/**
 * handle ajax requests
 */
function _handle(&$event, $param) {

    if (!in_array($event->data, array('infomail', 'plugin_infomail'))) {
            return;
    }

    if ($event->name === 'ACTION_ACT_PREPROCESS') {
            return;
    }

    //no other ajax call handlers needed
    $event->stopPropagation();
    $event->preventDefault();
    
    //data
    $data = array();
    $data[]= "Huhu";
    $data[]= "Huhu";

    //json library of DokuWiki
    require_once DOKU_INC . 'inc/JSON.php';
    $json = new JSON();
    
    //set content type
    header('Content-Type: application/json');
    echo $json->encode($data);
}

}
