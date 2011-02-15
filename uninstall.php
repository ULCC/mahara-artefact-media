<?php
/* 
 * This will remove all traces of the media artefact from the database.
 * 
 */
define('INTERNAL', 1);
$file = dirname(dirname(dirname(__FILE__))) . '/init.php';
require($file);
require_once(get_config('docroot').'lib/ddl.php');

$pluginname = 'media';
$artefactname = 'episode';

// drop the media tables
$table = new XMLDBTable('artefact_media_episode');
drop_table($table);
$table = new XMLDBTable('artefact_media_student_quota');
drop_table($table);
$table = new XMLDBTable('artefact_media_ldap_quota');
drop_table($table);
$table = new XMLDBTable('artefact_media_quota_override');
drop_table($table);


// blocktype_installed_viewtype
delete_records('blocktype_installed_viewtype', 'blocktype', 'mediaquota');
delete_records('blocktype_installed_viewtype', 'blocktype', $artefactname);

// blocktype_installed_category
delete_records('blocktype_installed_category', 'blocktype', 'mediaquota');
delete_records('blocktype_installed_category', 'blocktype', $artefactname);

//view artefacts
$sql = "SELECT va.id FROM {view_artefact} va INNER JOIN {block_instance} bi WHERE va.block = bi.id AND bi.blocktype = ?";
$viewartefacts = get_records_sql_array($sql, array($artefactname));
if ($viewartefacts) {
    foreach ($viewartefacts as $record) {
        delete_records('view_artefact', 'id', $record->id);

    }
}

// block instance
delete_records('block_instance', 'blocktype', $artefactname);

// blocktype_installed
delete_records('blocktype_installed', 'artefactplugin', $pluginname);

// config variables
delete_records('artefact_config', 'plugin', $pluginname);

//view artefacts
$sql = "SELECT at.artefact FROM {artefact_tag} at INNER JOIN {artefact} a WHERE at.artefact = a.id AND a.artefacttype = ?";
$artefacttags = get_records_sql_array($sql, array($artefactname));

if ($artefacttags) {
    foreach ($artefacttags as $record) {
        delete_records('artefact_tag', 'artefact', $record->artefact);

    }
}

// artefact
delete_records('artefact', 'artefacttype', $artefactname);

// artefact_installed_type
delete_records('artefact_installed_type', 'plugin', $pluginname);



// artefact_installed
delete_records('artefact_installed', 'name', $pluginname);



echo 'Uninstalled OK';



?>
