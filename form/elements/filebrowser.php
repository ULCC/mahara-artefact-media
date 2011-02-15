<?php
/**
 * Mahara: Electronic portfolio, weblog, resume builder and social networking
 * Copyright (C) 2006-2009 Catalyst IT Ltd and others; see:
 *                         http://wiki.mahara.org/Contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    mahara
 * @subpackage artefact-media
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2009 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

defined('INTERNAL') || die();

include_once(get_config('docroot') . 'artefact/media/lib.php');

/**
 * Browser for files area.
 *
 * @param Pieform  $form    The form to render the element for
 * @param array    $element The element to render
 * @return string           The HTML for the element
 */
function pieform_element_filebrowser(Pieform $form, $element) {
    global $USER, $_PIEFORM_FILEBROWSERS;
    $smarty = smarty_core();

    $group = $form->get_property('group');
    $institution = $form->get_property('institution');

    if (!empty($element['tabs'])) {
        $tabdata = pieform_element_filebrowser_configure_tabs($element['tabs']);
        $smarty->assign('tabs', $tabdata);
        if (!$group && $tabdata['owner'] == 'group') {
            $group = $tabdata['ownerid'];
        } else if (!$institution) {
            if ($tabdata['owner'] == 'institution') {
                $institution = $tabdata['ownerid'];
            } else if ($tabdata['owner'] == 'site') {
                $institution = 'mahara';
            }
        }
    }

    $userid = ($group || $institution) ? null : $USER->get('id');

   // $folder = $element['folder'];
    //$path = pieform_element_filebrowser_get_path($folder);
    //$smarty->assign('folder', $folder);
    //$smarty->assign('foldername', $path[0]->title);
    //$smarty->assign('path', array_reverse($path));
    $smarty->assign('highlight', $element['highlight'][0]);
    $smarty->assign('edit', !empty($element['edit']) ? $element['edit'] : -1);
    if (isset($element['browse'])) {
        $smarty->assign('browse', (int) $element['browse']);
    }

    $config = array_map('intval', $element['config']);

    if ($group && $config['edit']) {
        $smarty->assign('groupinfo', pieform_element_filebrowser_get_groupinfo($group));
    }

    $formid = $form->get_name();
    $prefix = $formid . '_' . $element['name'];

    if ($config['select']) {
        if (function_exists($element['selectlistcallback'])) {
            if ($form->is_submitted() && $form->has_errors() && isset($_POST[$prefix . '_selected']) && is_array($_POST[$prefix . '_selected'])) {
                $value = array_keys($_POST[$prefix . '_selected']);
            }
            else if (isset($element['defaultvalue'])) {
                $value = $element['defaultvalue'];
            }
            else {
                $value = null;
            }
            $selected = $element['selectlistcallback']($value);
        }
        $smarty->assign('selectedlist', $selected);
        $selectedliststr = json_encode($selected);
    }

    if ($config['uploadagreement']) {
        if (get_config_plugin('artefact', 'media', 'usecustomagreement')) {
            $smarty->assign('agreementtext', get_field('site_content', 'content', 'name', 'mediauploadcopyright'));
        }
        else {
            $smarty->assign('agreementtext', get_string('uploadcopyrightdefaultcontent', 'install'));
        }
    }
    if ($config['upload']) {
        $maxuploadsize = display_size(min(get_real_size(ini_get('post_max_size')), get_real_size(ini_get('upload_max_filesize'))));
        $smarty->assign('maxuploadsize', $maxuploadsize);
    }

    if (!empty($element['browsehelp'])) {
        $config['plugintype'] = $form->get_property('plugintype');
        $config['pluginname'] = $form->get_property('pluginname');
        $config['browsehelp'] = $element['browsehelp'];
    }

    $config['showtags'] = !empty($config['tag']) ? (int) $userid : 0;
    $config['editmeta'] = (int) ($userid && !$config['edit'] && !empty($config['tag']));

    $smarty->assign('config', $config);

    $filters = isset($element['filters']) ? $element['filters'] : null;
    $filedata = ArtefactTypeEpisode::get_my_episodes_data($userid, $group, $institution, $filters);
    $smarty->assign('filelist', $filedata);

    $configstr = json_encode($config);
    $fileliststr = json_encode($filedata);

    $smarty->assign('prefix', $prefix);

    $initjs = "{$prefix} = new FileBrowser('{$prefix}', {$configstr}, config);
{$prefix}.filedata = {$fileliststr};";
    if ($config['select']) {
        $initjs .= "{$prefix}.selecteddata = {$selectedliststr};";
    }

    $_PIEFORM_FILEBROWSERS[$prefix]['views_js'] = $initjs;

    $initjs .= "addLoadEvent({$prefix}.init);";

    $smarty->assign('initjs', $initjs);
    $smarty->assign('querybase', $element['page'] . (strpos($element['page'], '?') === false ? '?' : '&'));

    return $smarty->fetch('artefact:media:form/filebrowser.tpl');
}


function pieform_element_filebrowser_get_groupinfo($group) {
    require_once('group.php');
    $groupinfo = array(
        'roles' => group_get_role_info($group),
        'perms' => group_get_default_artefact_permissions($group),
        'perm'  => array(),
    );
    foreach (current($groupinfo['perms']) as $k => $v) {
        $groupinfo['perm'][$k] = get_string($k);
    }
    return $groupinfo;
}


//function pieform_element_filebrowser_get_path($folder) {
//    $path = array();
//    if ($folder) {
//        $folders = ArtefactTypeFileBase::artefactchooser_folder_data(artefact_instance_from_id($folder))->data;
//        $f = $folder;
//        while ($f) {
//            $path[] = (object) array('title' => $folders[$f]->title, 'id' => $f);
//            $f = $folders[$f]->parent;
//        }
//    }
//
//    $path[] = (object) array('title' => get_string('home'), 'id' => 0);
//    return $path;
//}


//function pieform_element_filebrowser_build_path($form, $element, $folder, $owner=null, $ownerid=null) {
//    if (!$form->submitted_by_js()) {
//        return;
//    }
//    $querybase = $element['page'] . (strpos($element['page'], '?') === false ? '?' : '&');
//
//    $path = pieform_element_filebrowser_get_path($folder);
//    $foldername = $path[0]->title;
//
//    $smarty = smarty_core();
//    $smarty->assign('path', array_reverse($path));
//    $smarty->assign('owner', $owner);
//    $smarty->assign('ownerid', $ownerid);
//    $smarty->assign('querybase', $querybase);
//    return array('html' => $smarty->fetch('artefact:media:form/folderpath.tpl'), 'foldername' => $foldername);
//}


function pieform_element_filebrowser_build_filelist($form, $element, $highlight=null, $user=null, $group=null, $institution=null) {
    if (!$form->submitted_by_js()) {
        // We're going to rebuild the page from scratch anyway.
        return;
    }

    global $USER;

    $smarty = smarty_core();

    if (is_null($group) && is_null($user)) {
        $group = $form->get_property('group');
    }
    else {
        $smarty->assign('owner', 'group');
        $smarty->assign('ownerid', $group);
    }
    if (is_null($institution)) {
        $institution = $form->get_property('institution');
    }
    else {
        $smarty->assign('owner', 'institution');
        $smarty->assign('ownerid', $institution);
    }
    $userid = ($group || $institution) ? null : $USER->get('id');
    $editable = (int) $element['config']['edit'];
    $selectable = (int) $element['config']['select'];
    //$selectfolders = (int) !empty($element['config']['selectfolders']);
    $publishing = (int) !empty($element['config']['publishing']);
    $showtags = !empty($element['config']['tag']) ? (int) $userid : 0;
    $editmeta = (int) ($userid && !$editable && !empty($element['config']['tag']));
    $querybase = $element['page'] . (strpos($element['page'], '?') === false ? '?' : '&');
    $prefix = $form->get_name() . '_' . $element['name'];

    $filters = isset($element['filters']) ? $element['filters'] : null;
    $filedata = ArtefactTypeEpisode::get_my_episodes_data($userid, $group, $institution, $filters);

    $smarty->assign('edit', -1);
    $smarty->assign('highlight', $highlight);
    $smarty->assign('editable', $editable);
    $smarty->assign('selectable', $selectable);
    //$smarty->assign('selectfolders', $selectfolders);
    $smarty->assign('publishing', $publishing);
    $smarty->assign('showtags', $showtags);
    $smarty->assign('editmeta', $editmeta);
    $smarty->assign('filelist', $filedata);
    $smarty->assign('querybase', $querybase);
    $smarty->assign('prefix', $prefix);

    return array(
        'data' => $filedata,
        'html' => $smarty->fetch('artefact:media:form/filelist.tpl'),
    );
}


function pieform_element_filebrowser_configure_tabs($viewowner) {
    if ($viewowner['type'] == 'institution' && $viewowner['id'] == 'mahara') {
        // No filebrowser tabs for site views
        return null;
    }

    $tabs = array();
    $subtabs = array();

    $upload = null;
    $selectedsubtab = null;
    if ($viewowner['type'] == 'institution') {
        $selectedtab = param_variable('owner', 'institution');
        $upload = $selectedtab == 'institution';
        $tabs['institution'] = get_string('institutionfiles', 'admin');
    }
    else if ($viewowner['type'] == 'group') {
        $selectedtab = param_variable('owner', 'group');
        $upload = $selectedtab == 'group';
        $tabs['user'] = get_string('myfiles', 'artefact.media');
        $tabs['group'] = get_string('groupfiles', 'artefact.media');
    }
    else { // $viewowner['type'] == 'user'
        global $USER;
        $selectedtab = param_variable('owner', 'user');
        $upload = $selectedtab == 'user';
        $tabs['user'] = get_string('myfiles', 'artefact.media');
        if ($groups = $USER->get('grouproles')) {
            $tabs['group'] = get_string('groupfiles', 'artefact.media');
            require_once(get_config('libroot') . 'group.php');
            $groups = group_get_user_groups($USER->get('id'));
            if ($selectedtab == 'group') {
                if (!$selectedsubtab = (int) param_variable('ownerid', 0)) {
                    $selectedsubtab = $groups[0]->id;
                }
                foreach ($groups as &$g) {
                    $subtabs[$g->id] = $g->name;
                }
            }
        }
        if ($institutions = $USER->get('institutions')) {
            $tabs['institution'] = get_string('institutionfiles', 'admin');
            $institutions = get_records_select_array('institution', 'name IN (' 
                . join(',', array_map('db_quote', array_keys($institutions))) . ')');
            if ($selectedtab == 'institution') {
                if (!$selectedsubtab = param_variable('ownerid', '')) {
                    $selectedsubtab = $institutions[0]->name;
                }
                $selectedsubtab = hsc($selectedsubtab);
                foreach ($institutions as &$i) {
                    $subtabs[$i->name] = $i->displayname;
                }
            }
        }
    }
    $tabs['site'] = get_string('sitefiles', 'admin');
    return array('tabs' => $tabs, 'subtabs' => $subtabs, 'owner' => $selectedtab, 'ownerid' => $selectedsubtab, 'upload' => $upload);
}


function pieform_element_filebrowser_get_value(Pieform $form, $element) {
    $prefix = $form->get_name() . '_' . $element['name'];


    // The value of this element is the list of selected artefact ids
    $selected = param_variable($prefix . '_selected', null);
    if (is_array($selected)) {
        $selected = array_keys($selected);
    }


    // Process actions that must occur before form validation and
    // which can safely occur without affecting the element's value
    $result = pieform_element_filebrowser_doupdate($form, $element);

    if (is_array($result)) {
        // We did something.  If js, replace the filebrowser now and
        // don't continue form submission.
//        if (!isset($result['folder'])) {
//            $result['folder'] = $element['folder'];
//        }
        if ($form->submitted_by_js()) {
            $replacehtml = false; // Don't replace the entire form when replying with json data.
            $result['formelement'] = $prefix;
            if (!empty($result['error'])) {
                $result['formelementerror'] = $prefix . '.success';
            }
            else {
                $result['formelementsuccess'] = $prefix . '.success';
            }
            $form->json_reply(empty($result['error']) ? PIEFORM_OK : PIEFORM_ERR, $result, $replacehtml);
        }

        // Not js. Add some params & redirect back to the page
        $params = array();
//        if (!empty($result['folder'])) {
//            $params[] = 'folder=' . $result['folder'];
//        }
        if (!empty($result['edit'])) {
            $params[] = 'edit=' . $result['edit'];
        }
        if (!empty($result['highlight'])) {
            $params[] = 'file=' . $result['highlight'];
        }
        if (!empty($result['browse'])) {
            $params[] = 'browse=1';
        }

        $result['goto'] = $element['page'];
        if (!empty($params)) { 
            $result['goto'] .= (strpos($element['page'], '?') === false ? '?' : '&') . join('&', $params);
        }

        if (empty($result['select']) && empty($result['unselect'])) {
            $form->reply(empty($result['error']) ? PIEFORM_OK : PIEFORM_ERR, $result);
        }

        // If we got to this point, the doupdate function couldn't select or unselect a file,
        // so we need to let it go through to the form's submit function to deal with.
        if (!empty($result['select'])) {
            if ($element['config']['selectone']) {
                $selected = array($result['select']);
            }
            else {
                $selected = is_array($selected) ? $selected : array();
                if (!in_array($result['select'], $selected)) {
                    $selected[] = $result['select'];
                }
            }
        }
        else if (!empty($result['unselect'])) {
            $selected = is_array($selected) ? array_diff($selected, array($result['unselect'])) : array();
        }
    }

    if (is_array($selected) && !empty($selected)) {
        if (!empty($element['config']['selectone'])) {
            return $selected[0];
        }
        return $selected;
    }
    return null;
}


function pieform_element_filebrowser_doupdate(Pieform $form, $element) {
    $result = null;

    $prefix = $form->get_name() . '_' . $element['name'];

    $delete = param_variable($prefix . '_delete', null);
    if (is_array($delete)) {
        $keys = array_keys($delete);
        return pieform_element_filebrowser_delete($form, $element, (int) ($keys[0]));
    }

    $update = param_variable($prefix . '_update', null);
    if (is_array($update)) {
        $edit_title = param_variable($prefix . '_edit_title');
        $namelength = strlen($edit_title);
        if (!$namelength) {
            return array(
                'error'   => true,
                'message' => get_string('filenamefieldisrequired', 'artefact.media')
            );
        }
        else if ($namelength > 1024) {
            return array(
                'error'   => true,
                'message' => get_string('nametoolong', 'artefact.media'),
            );
        }
        $keys = array_keys($update);
        $data = array(
            'artefact'    => (int) ($keys[0]),
            'title'       => $edit_title,
            'description' => param_variable($prefix . '_edit_description'),
            'tags'        => param_variable($prefix . '_edit_tags'),
           // 'folder'      => $element['folder'],
        );
        if ($form->get_property('group')) {
            $data['permissions']  = array('admin' => (object) array('view' => true, 'edit' => true, 'republish' => true));
            foreach ($_POST as $k => $v) {
                if (preg_match('/^' . $prefix . '_permission:([a-z]+):([a-z]+)$/', $k, $m)) {
                    $data['permissions'][$m[1]]->{$m[2]} = (bool) $v;
                }
            }
        }
        return pieform_element_filebrowser_update($form, $element, $data);
    }


    // {$prefix}_upload is set in all browsers except safari when javascript is
    // on (and set in all browsers when it's not)
    $upload = param_variable($prefix . '_upload', null);
    if (!empty($upload)) {
        if (empty($_FILES['userfile']['name'])) {
            return array(
                'error'   => true,
                'message' => get_string('filenamefieldisrequired', 'artefact.media'),
                'browse'  => 1,
            );
        }
    }

    if (isset($_FILES['userfile']['error']) && $_FILES['userfile']['error'] == 0) {
        if (strlen($_FILES['userfile']['name']) > 1024) {
            return array(
                'error'   => true,
                'message' => get_string('nametoolong', 'artefact.media'),
            );
        }
        else if ($element['config']['uploadagreement'] && !param_boolean($prefix . '_notice', false)) {
            return array(
                'error'   => true,
                'message' => get_string('youmustagreetothecopyrightnotice', 'artefact.media'),
                'browse'  => 1,
            );
        }

        $result = pieform_element_filebrowser_upload($form, $element, array(
            'userfile'         => $_FILES['userfile'],
            'uploadnumber'     => param_integer($prefix . '_uploadnumber'),
            'title'            => param_variable($prefix . '_episodetitle'),
            'description'      => param_variable($prefix . '_episodedescription')
        ));
        // If it's a non-js upload, automatically select the newly uploaded file.
        $result['browse'] = 1;
        if (!$form->submitted_by_js() && !$result['error'] && !empty($element['config']['select'])) {
            if (isset($element['selectcallback']) && is_callable($element['selectcallback'])) {
                $element['selectcallback']($result['highlight']);
            }
            else {
                $result['select'] = $result['highlight'];
            }
        }
        return $result;
    }

    if (!$form->submitted_by_js()) {

        $select = param_variable($prefix . '_select', null);
        if (is_array($select)) {
            $keys = array_keys($select);
            $add = (int) $keys[0];
            if (isset($element['selectcallback']) && is_callable($element['selectcallback'])) {
                $element['selectcallback']($add);
            }
            else {
                $result['select'] = $add;
            }
            $result['message'] = get_string('fileadded', 'artefact.media');
            $result['browse'] = 1;
            return $result;
        }

        $unselect = param_variable($prefix . '_unselect', null);
        if (is_array($unselect)) {
            $keys = array_keys($unselect);
            $del = (int) $keys[0];
            if (isset($element['unselectcallback']) && is_callable($element['unselectcallback'])) {
                $element['unselectcallback']($del);
            }
            else {
                $result['unselect'] = $del;
            }
            $result['message'] = get_string('fileremoved', 'artefact.media');
            return $result;
        }

        $edit = param_variable($prefix . '_edit', null);
        if (is_array($edit)) {
            $keys = array_keys($edit);
            $result['edit'] = (int) $keys[0];
            return $result;
        }

        if (param_variable('browse', 0) && !param_variable($prefix . '_cancelbrowse', 0)) {
            $result['browse'] = 1;
            return $result;
        }

    }

    $changeowner = param_variable($prefix . '_changeowner', null);
    if (!empty($changeowner)) {
        $result = pieform_element_filebrowser_changeowner($form, $element);
        $result['browse'] = 1;
        return $result;
    }



}


function pieform_element_filebrowser_upload(Pieform $form, $element, $data) {
    global $USER;

    //$parentfolder     = $data['uploadfolder'] ? (int) $data['uploadfolder'] : null;
    $institution      = $form->get_property('institution');
    $title            = $data['title'];
    $description      = $data['description'];
    $account          = $form->get_property('account');
    $group            = $form->get_property('group');
    $uploadnumber     = (int) $data['uploadnumber'];
    $editable         = (int) $element['config']['edit'];
    $selectable       = (int) $element['config']['select'];
    $querybase        = $element['page'] . (strpos($element['page'], '?') === false ? '?' : '&');
    $prefix           = $form->get_name() . '_' . $element['name'];
    // $title            = $data['title'];

    $result = array('error' => false, 'uploadnumber' => $uploadnumber);


    $originalname = $_FILES['userfile']['name'];
    $originalname = $originalname ? $originalname : get_string('file', 'artefact.media');

    // Data object, analagous to one row in the artefact table
    $data              = new StdClass;
//    $data->parent      = $parentfolder;
    $data->owner       = null;
    $data->account     = str_replace(' ', '', $account);
    $data->description = $description;



    // ownership stuff
    if ($institution) {
        if (!$USER->can_edit_institution($institution)) {
            $result['error'] = true;
            $result['message'] = get_string('notadminforinstitution', 'admin');
            return $result;
        }
        $data->institution = $institution;
    } else if ($group) {
        require_once(get_config('libroot') . 'group.php');
//        if (!$parentfolder) {
            $role = group_user_access($group);
            if (!$role) {
                $result['error'] = true;
                $result['message'] = get_string('usernotingroup', 'mahara');
                return $result;
            }
            // Use default grouptype artefact permissions to check if the
            // user can upload a file to the group's root directory
            $permissions = group_get_default_artefact_permissions($group);
            if (!$permissions[$role]->edit) {
                $result['error'] = true;
                $result['message'] = get_string('cannoteditfolder', 'artefact.media');
                return $result;
            }
//        }
        $data->group = $group;
    } else {
        $data->owner = $USER->get('id');
    }

    $data->title       = !empty($title) ? $title : ArtefactTypeEpisode::get_new_file_title($originalname, $data->owner, $group, $institution);

    $data->container = 0;
    $data->locked = 0;


    $quota = PluginArtefactMedia::get_quota($USER->get('id'));
    $result['quota'] = $quota->value;
    $result['quotaused'] = PluginArtefactMedia::get_quota_used($USER->get('id'));


    try {
        $newid = ArtefactTypeEpisode::save_uploaded_file('userfile', $data);
    }
    catch (QuotaExceededException $e) {
        prepare_upload_failed_message($result, $e, $originalname, true);
        return $result;
    }
    catch (UploadException $e) {
        prepare_upload_failed_message($result, $e, $originalname, false);
        return $result;
    }

    // Upload succeeded

    if (isset($element['filters'])) {
        $artefacttypes = isset($element['filters']['artefacttype']) ? $element['filters']['artefacttype'] : null;
        $filetypes = isset($element['filters']['filetype']) ? $element['filters']['filetype'] : null;
        if (!empty($artefacttypes) || !empty($filetypes)) {
            // Need to check the artefacttype or filetype (mimetype) of the uploaded file.
            $file = artefact_instance_from_id($newid);
            if (is_array($artefacttypes) && !in_array($file->get('artefacttype'), $artefacttypes)
                || is_array($filetypes) && !in_array($file->get('filetype'), $filetypes)) {
                $result['error'] = true;
                $result['uploaded'] = true;
                $result['message'] = get_string('wrongfiletypeforblock', 'artefact.file');
                return $result;
            }
        }
    }


    else if ($data->title == $originalname) {
        $result['message'] = get_string('uploadoffilecomplete', 'artefact.media', $originalname);
    }
    else {
        $result['message'] = get_string('fileuploadedas', 'artefact.media', $originalname, $data->title);
    }

    $result['highlight'] = $newid;
    $result['uploaded'] = true;
    $result['quota'] = $quota->value;
    $result['quotaused'] = PluginArtefactMedia::get_quota_used($USER->get('id'));

    // TODO - needs to get files only from the media thingy i.e. database records, not disk use
    $result['newlist'] = pieform_element_filebrowser_build_filelist($form, $element, $newid);



    return $result;
}


/**
 * Helper function used above to minimise code duplication
 */
function prepare_upload_failed_message(&$result, $exception, $title, $uploadedok=true) {
    $result['error'] = true;

    $result['message'] = get_string('uploadoffilefailed', 'artefact.media',  $title);
    
    $result['message'] .= ': ' . $exception->getMessage();

    $result['uploaded'] = ($uploadedok) ? true : false;
}




function pieform_element_filebrowser_update(Pieform $form, $element, $data) {
    global $USER;
    $collide = !empty($data['collide']) ? $data['collide'] : 'fail';

    $artefact = artefact_instance_from_id($data['artefact']);
    if (!$USER->can_edit_artefact($artefact)) {
        return array('error' => true, 'message' => get_string('noeditpermission', 'mahara'));
    }

    $existingid = ArtefactTypeEpisode::episode_exists($data['title'], $artefact->get('owner'),
                                                      $artefact->get('institution'), $artefact->get('group'));

    if ($existingid) {
        if ($existingid != $data['artefact']) {
            if ($collide == 'replace') {
                log_debug('deleting ' . $existingid);
                $copy = artefact_instance_from_id($existingid);
                $copy->delete();
            }
            else {
                return array('error' => true, 'message' => get_string('fileexists', 'artefact.media'));
            }
        }
    }

    $artefact->set('title', $data['title']);
    $artefact->set('description', $data['description']);

    $oldtags = $artefact->get('tags');
    $newtags = preg_split("/\s*,\s*/", trim($data['tags']));
    $updatetags = $oldtags != $newtags;
    if ($updatetags) {
        $artefact->set('tags', $newtags);
    }

    if ($form->get_property('group') && $data['permissions']) {
        $artefact->set('rolepermissions', $data['permissions']);
    }
    $artefact->commit();

    $returndata = array(
        'error' => false,
        'message' => get_string('changessaved', 'artefact.media'),
        'newlist' => pieform_element_filebrowser_build_filelist($form, $element, $artefact->get('parent')),
    );

    if ($updatetags && $form->submitted_by_js()) {
        $smarty = smarty_core();
        $tagdata = tags_sideblock();
        $smarty->assign('sbdata', $tagdata);
        $returndata['tagblockhtml'] = $smarty->fetch('sideblocks/tags.tpl');
    }

    return $returndata;
}


function pieform_element_filebrowser_delete(Pieform $form, $element, $artefact) {
    global $USER;
    $artefact = artefact_instance_from_id($artefact);
    if (!$USER->can_edit_artefact($artefact)) {
        return array('error' => true, get_string('nodeletepermission', 'mahara'));
    }
    //$parentfolder = $artefact->get('parent');
    $artefact->delete();
    $quota = PluginArtefactMedia::get_quota($USER->get('id'));
    return array(
        'error' => false, 
        'deleted' => true, 
        'message' => get_string('filethingdeleted', 'artefact.media',
                                get_string($artefact->get('artefacttype'), 'artefact.media')),
        'quotaused' => PluginArtefactMedia::get_quota_used($USER->get('id')),
        'quota' => $quota->value,
        'newlist' => pieform_element_filebrowser_build_filelist($form, $element),
    );
}


//function pieform_element_filebrowser_move(Pieform $form, $element, $data) {
//    global $USER;
//    $artefactid  = $data['artefact'];    // Artefact being moved
//    $newparentid = $data['newparent'];   // Folder to move it to
//
//    $artefact = artefact_instance_from_id($artefactid);
//
//    if (!$USER->can_edit_artefact($artefact)) {
//        return array('error' => true, 'message' => get_string('movefailednotowner', 'artefact.media'));
//    }
//    if (!in_array($artefact->get('artefacttype'), PluginArtefactFile::get_artefact_types())) {
//        return array('error' => true, 'message' => get_string('movefailednotfileartefact', 'artefact.media'));
//    }
//
//    if ($newparentid > 0) {
//        if ($newparentid == $artefactid) {
//            return array('error' => true, 'message' => get_string('movefaileddestinationinartefact', 'artefact.media'));
//        }
//        if ($newparentid == $artefact->get('parent')) {
//            return array('error' => false, 'message' => get_string('filealreadyindestination', 'artefact.media'));
//        }
//        $newparent = artefact_instance_from_id($newparentid);
//        if (!$USER->can_edit_artefact($newparent)) {
//            return array('error' => true, 'message' => get_string('movefailednotowner', 'artefact.media'));
//        }
//        $group = $artefact->get('group');
//        if ($group && $group !== $newparent->get('group')) {
//            return array('error' => true, 'message' => get_string('movefailednotowner', 'artefact.media'));
//        }
//        if ($newparent->get('artefacttype') != 'folder') {
//            return array('error' => true, 'message' => get_string('movefaileddestinationnotfolder', 'artefact.media'));
//        }
//        $nextparentid = $newparent->get('parent');
//        while (!empty($nextparentid)) {
//            if ($nextparentid != $artefactid) {
//                $ancestor = artefact_instance_from_id($nextparentid);
//                $nextparentid = $ancestor->get('parent');
//            } else {
//                return array('error' => true, 'message' => get_string('movefaileddestinationinartefact', 'artefact.media'));
//            }
//        }
//    } else { // $newparentid === 0
//        if ($artefact->get('parent') == null) {
//            return array('error' => false, 'message' => get_string('filealreadyindestination', 'artefact.media'));
//        }
//        $group = $artefact->get('group');
//        if ($group) {
//            // Use default grouptype artefact permissions to check if the
//            // user can move a file to the group's root directory
//            require_once(get_config('libroot') . 'group.php');
//            $permissions = group_get_default_artefact_permissions($group);
//            if (!$permissions[group_user_access($group)]->edit) {
//                return array('error' => true, 'message' => get_string('movefailednotowner', 'artefact.media'));
//            }
//        }
//        $newparentid = null;
//    }
//
//    if ($artefact->move($newparentid)) {
//        return array(
//            'error' => false,
//            'newlist' => pieform_element_filebrowser_build_filelist($form, $element, $data['folder']),
//        );
//    }
//    return array('error' => true, 'message' => get_string('movefailed', 'artefact.media'));
//}


function pieform_element_filebrowser_changeowner(Pieform $form, $element) {
    $newtabdata = pieform_element_filebrowser_configure_tabs($element['tabs']);
    $smarty = smarty_core();
    $smarty->assign('prefix', $form->get_name() . '_' . $element['name']);
    $smarty->assign('querybase', $element['page'] . (strpos($element['page'], '?') === false ? '?' : '&'));
    $smarty->assign('tabs', $newtabdata);
   // $newtabhtml = $smarty->fetch('artefact:media:form/ownertabs.tpl');
    //$newsubtabhtml = $smarty->fetch('artefact:media:form/ownersubtabs.tpl');

    $group = null;
    $institution = null;
    $user = null;
   // $folder = 0;
    if ($newtabdata['owner'] == 'site') {
        global $USER;
//        if (!$USER->get('admin')) {
//            $folder = ArtefactTypeFolder::admin_public_folder_id();
//        }
        $institution = 'mahara';
    }
    else if ($newtabdata['owner'] == 'institution') {
        $institution = $newtabdata['ownerid'];
    }
    else if ($newtabdata['owner'] == 'group') {
        $group = $newtabdata['ownerid'];
    }
    else if ($newtabdata['owner'] == 'user') {
        $user = true;
    }

    return array(
        'error'         => false, 
        'changedowner'  => true,
        'changedfolder' => true,
        'tabupload'     => $newtabdata['upload'],
        //'folder'        => $folder,
        'newlist'       => pieform_element_filebrowser_build_filelist($form, $element, null, $user, $group, $institution),
       // 'newpath'       => pieform_element_filebrowser_build_path($form, $element, $newtabdata['owner'], $newtabdata['ownerid']),
      //  'newtabs'       => $newtabhtml,
       // 'newsubtabs'    => $newsubtabhtml,
    );
}


//function pieform_element_filebrowser_changefolder(Pieform $form, $element, $folder) {
//    $owner = $ownerid = $group = $institution = $user = null;
//
//    if (isset($element['tabs'])) {
//        if ($owner = param_variable('owner', null)) {
//            if ($owner == 'site') {
//                $owner = 'institution';
//                $institution = $ownerid = 'mahara';
//            } else if ($ownerid = param_variable('ownerid', null)) {
//                if ($owner == 'group') {
//                    $group = (int) $ownerid;
//                }
//                else if ($owner == 'institution') {
//                    $institution = $ownerid;
//                }
//                else if ($owner == 'user') {
//                    $user = true;
//                }
//            }
//        }
//    }
//
//    return array(
//        'error'         => false,
//        'changedfolder' => true,
//        'folder'        => $folder,
//        'newlist'       => pieform_element_filebrowser_build_filelist($form, $element, $folder, null, $user, $group, $institution),
//        'newpath'       => pieform_element_filebrowser_build_path($form, $element, $folder, $owner, $ownerid),
//    );
//}


function pieform_element_filebrowser_views_js(Pieform $form, $element) {
    global $_PIEFORM_FILEBROWSERS;
    $formname = $form->get_name();
    $prefix = $formname . '_' . $element['name'];
    return $_PIEFORM_FILEBROWSERS[$prefix]['views_js'] . " {$prefix}.init();";
}


/**
 * When the element exists in a form that's present when the page is
 * first generated the following function gets called and the js file
 * below will be inserted into the head data.  Unfortunately, when
 * this element is present in a form that gets called in an ajax
 * request (currently on the view layout page), the .js file is not
 * loaded and so it's added explicitly to the smarty() call.
 */
function pieform_element_filebrowser_get_headdata($element) {
    global $THEME;
    $headdata = array('<script type="text/javascript" src="' . get_config('wwwroot') . 'artefact/media/js/filebrowser.js"></script>');

    $strings = PluginArtefactMedia::jsstrings('filebrowser');
    $jsstrings = '';
    foreach ($strings as $section => $sectionstrings) {
        foreach ($sectionstrings as $s) {
            $jsstrings .= "strings.$s=" . json_encode(get_raw_string($s, $section)) . ';';
        }
    }
    $headdata[] = '<script type="text/javascript">' . $jsstrings . '</script>';

    $pluginsheets = $THEME->get_url('style/style.css', true, 'artefact/media');
    foreach (array_reverse($pluginsheets) as $sheet) {
        $headdata[] = '<link rel="stylesheet" type="text/css" href="' . $sheet . '">';
    }

    return $headdata;
}


function pieform_element_filebrowser_set_attributes($element) {/*{{{*/
    $element['needsmultipart'] = true;
    return $element;
}/*}}}*/

?>
