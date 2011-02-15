<?php
/* 
 * Provides the stuff you see once you click on the 'media' tab
 */


define('INTERNAL', 1);
define('MENUITEM', 'myportfolio/media');

$view = new View(param_integer('view'));
$ownerid = $view->get('owner');

?>