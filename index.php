<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */


define('INTERNAL', 1);
define('MENUITEM', 'myportfolio/media');
define('SECTION_PLUGINTYPE', 'artefact');
define('SECTION_PLUGINNAME', 'media');
define('SECTION_PAGE', 'index');

require(dirname(dirname(dirname(__FILE__))) . '/init.php');
define('TITLE', get_string('mymedia', 'artefact.media'));
safe_require('artefact', 'media');

// get files that are already uploaded
$files = 'No files yet';

// get quota used so far - same as count($files);
$quotaused = PluginArtefactMedia::get_quota_used($USER->get('id'));

// get quota and source
$quota = PluginArtefactMedia::get_quota($USER->get('id'));

// check on the streaming server - is there space for the institution to add more?

// calculate used quota percentage
$quotapercentage = (empty($quota->value)) ? 0 : round(($quotaused/$quota->value)*100, 2);

// in case the quota has been lowered
if ($quotapercentage > 100) {
    $quotapercentage = 100;
}

// get data for the mediaquota block
$mediaquota_data = array(
        'quotamessage'    => get_string('quotausage', 'artefact.media', $quotaused, $quota->value),
        'quotapercentage' => $quotapercentage,
        'quotasource'     => $quota->source
);

$form = pieform(ArtefactTypeEpisode::upload_form(get_config('wwwroot') . 'artefact/media/index.php'));
$js = ArtefactTypeEpisode::media_js();


// need to flag whether or not episodes can be added - if there is no

$smarty = smarty(
    array(),
    array(),
    array(),
    array(
        'sideblocks' => array(
            array(
                'name'   => 'mediaquota',
                'weight' => -10,
                'data'   => $mediaquota_data,
            ),
        ),
    )
);
$smarty->assign('PAGEHEADING',  hsc(get_string('mymedia', 'artefact.media')));
//$smarty->assign('files', $files);
//$smarty->assign('user', $user);

$smarty->assign('form', $form);
$smarty->assign('INLINEJAVASCRIPT', $js);
$smarty->display('artefact:media:index.tpl');


?>
