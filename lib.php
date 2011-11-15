<?php
/**
 * Classes for the Media plugin, linking Mahara with the ULCC streaming server. Lots of this has been
 * lifted from the file artefact plugin and modified to strip out the irrelevant bits.
 */

// needed so that the ldap class below can use the auth interface for managing the
include_once(get_config('docroot').'auth/lib.php');

/*
 * Main class definition for the media plugin
 */
class PluginArtefactMedia extends PluginArtefact {

    /**
     * Plugins can provide a variety of artefact types, or just one, or none. Returns an array of
     * strings giving their names.
     *
     * @return array
     */
    public static function get_artefact_types() {
        return array(
                'episode'
        );
    }

    /**
     * Plugins can provide a variety of block types, or just one, or none. Returns an array of
     * strings giving their names.
     *
     * @return array
     */
    public static function get_block_types() {
        return array('episode');
    }

    /**
     * This returns the plugin's name, which should be a single word
     *
     * @return string
     */
    public static function get_plugin_name() {
        return 'media';
    }

    /**
     * What menus should be added to Mahara? This puts the 'My streaming media' item into the portfolio
     * bit.
     *
     * @return array of arrays
     */
    public static function menu_items() {
        return array(
            array(
                'path'   => 'myportfolio/media',
                'title'  => get_string('mymedia', 'artefact.media'),
                'url'    => 'artefact/media/',
                'weight' => 20,
            )
        );
    }

    /**
     * What to do after the plugin is installed. In this case, add config optiopns if this is the
     * first install and not an upgrade
     *
     * @param int $prevversion
     */
    public static function postinst($prevversion) {
        if ($prevversion == 0) {
            set_config_plugin('artefact', 'media', 'defaultquota', 1);
            set_config_plugin('artefact', 'media', 'uploadagreement', 1);
        }
    }

    /**
     * Provides strings for javascript to use.
     *
     * @staticvar array $jsstrings
     * @param string $type
     * @return array
     */
    public static function jsstrings($type) {
        static $jsstrings = array(
            'filebrowser' => array(
                'mahara' => array(
                    'remove',
                ),
                'artefact.media' => array(
                    'confirmdeletefile',
                    'confirmdeletefolder',
                    'confirmdeletefolderandcontents',
                    'editfile',
                    'editfolder',
                    'fileappearsinviews',
                    'fileattached',
                    'filewithnameexists',
                    'folderappearsinviews',
                    'foldernamerequired',
                    'foldernotempty',
                    'nametoolong',
                    'namefieldisrequired',
                    'uploadingfiletofolder',
                    'youmustagreetothecopyrightnotice',
                ),
            ),
        );
        return $jsstrings[$type];
    }

    /**
     * Gets the quota for a particular user. Checks LDAP if necessary, or provides the site default
     * if not.
     *
     * @global <type> $USER
     * @param int $userid
     * @return stdClass object with two properties: value for the number of uitems allowed, and source,
     *                  showing where it came from i.e. site default, or LDAP OU
     */
    public static function get_quota($userid) {

        // we may get more than one quota from the LDAP groups
        $quota = new stdClass;
        $defaultquota = get_config_plugin('artefact', 'media', 'defaultquota');

        // is there an override for a specific student?
        $override = get_field('artefact_media_quota_override', 'quota', 'userid', $userid);

        if ($override) {
            $quota->value = $override->quota;
            $quota->source = get_string('studentoverride', 'artefact.media');
            return $quota;
        }

        // if not, we look for a specific record for this LDAP OU at this institution
        $institutions = load_user_institutions($userid);

        // Find the correct auth instance, i.e. the ldap one for the user's institution
        // Note this only uses the first institution available.
        $institution = array_shift(array_keys($institutions));

        if ($institution) {
            $sql = "SELECT lq.quota, sq.timemodified, lq.ldapou
                      FROM {artefact_media_student_quota} sq
                INNER JOIN {artefact_media_ldap_quota} lq
                     WHERE sq.mediaquota = lq.id
                       AND sq.userid = ?
                       AND lq.institution = ?";
            $studentldapquota = get_record_sql($sql, array($userid, $institution));

            // not set yet, or out of date
            if (!$studentldapquota || ((time() - $studentldapquota->timemodified) > 86400)) {
                // need to get the ldap group
                $quota = self::set_student_ldap_quota($userid, $institution);

            } else {
                $quota->source = $studentldapquota->ldapou;
                // if null (in institution, but no
                $quota->value = ($studentldapquota->quota) ? $studentldapquota->quota : $defaultquota;
            }

        } else {

            $quota->value = $defaultquota;
            $quota->source = get_string('sitedefault', 'artefact.media');
        }

        return $quota;

    }

    /**
     * This is called when there is no ldap record, or the record has expired. It needs to make a
     * record even if no OU is found in order to prevent LDAP lookups on every page load
     *
     * TODO proper exceptions?
     *
     * @param int $userid
     * @param string $institution
     * @return stdClass quota object with value and source string
     */
    public static function set_student_ldap_quota($userid, $institution) {

        $ldapquotaid = '';

        // get user details
        $user = get_user($userid);
        $defaultquota = get_config_plugin('artefact', 'media', 'defaultquota');

        $quota = new stdClass;
        $quota->value = $defaultquota;
        $quota->source = get_string('sitedefault', 'artefact.media');

        $authid = get_field('auth_instance', 'id', 'institution', $institution, 'authname', 'ldap');

        // possibly no ldap stuff set up yet
        if (!$authid) {
            return $quota;
        }

        // get the ldap user info
        $ldapobject = new media_ldap_auth($authid);
        $userou = $ldapobject->get_user_ou($user->username);

        // Any ldap connection problem will set the quota
        // back to site default for 24 hours
        $ldapquota = false;

        if ($userou) {
            // is there a mediaquota record for this ldap group at this institution in the config settings?
            $ldapquota = get_record('artefact_media_ldap_quota', 'institution', $institution, 'ldapou', $userou);
        } else {
            // look for the empty one
            $ldapquota = get_record('artefact_media_ldap_quota', 'institution', $institution, 'ldapou', '');
        }

        if (!$ldapquota) {
            // must make an empty record for the institution
            $institution->ldapou = '';
            $ldapquotaid = self::set_ldap_quota($institution);
        } else {
            $ldapquotaid = $ldapquota->id;
            $quota->value = $ldapquota->quota;
            $quota->source = $ldapquota->ldapou;
        }

        self::set_student_quota($userid, $ldapquotaid, time());

        // return the stored quota and its source, or the default if there wasn't one
        return $quota;

    }

    private static function set_student_quota($userid, $mediaquota, $timemodified) {

        $studentdataobject = new stdClass;

        $studentdataobject->userid       = $userid;
        $studentdataobject->mediaquota   = $mediaquota;
        $studentdataobject->timemodified = $timemodified;

        // commit to the db
	$existing = get_record('artefact_media_student_quota', 'userid', $userid, 'mediaquota', $mediaquota);
        if ($existing) {
            $studentdataobject->id = $existing->id;
            return update_record('artefact_media_student_quota', $studentdataobject);
        } else {
            return insert_record('artefact_media_student_quota', $studentdataobject, false, true);
        }
    }

    private static function set_ldap_quota($institution, $mediaquota='', $quota='') {

        $data = new StdClass;
        $data->institution    = $institution;
        // TODO - strip tags here?
        $data->ldapou         = $mediaquota;
        $data->quota          = $quota;

        return insert_record('artefact_media_ldap_quota', $data, false, true);

    }

    /**
     * Counts how many episodes already exist as artefacts
     *
     * @param int $userid
     * @return int
     */
    public static function get_quota_used($userid) {

        $select = "SELECT COUNT(id)
                     FROM {artefact} a
               INNER JOIN {artefact_media_episode} e
                       ON e.artefact = a.id
                    WHERE a.owner = ?
                      AND a.artefacttype = ? ";

        $values = array($userid, 'episode');
        $count = count_records_sql($select, $values);

        return (int)$count;
    }

    /**
     * Sets a quota override for a particular user. This will be used in preference to the institution-wide
     * default and the default for their group if there is one
     *
     * @param int $userid user id
     * @param int $episodes the number of episodes to allocate to them
     * @return void
     */
    public static function set_quota_override($userid, $episodes) {

        // add a record to the override table
        if (record_exists('artefact_media_quota_override', 'userid', $userid)) {
            set_field('artefact_media_quota_override', 'quota', $episodes, 'userid', $userid);
        } else {
            try {
                $data = new StdClass;
                $data->userid = $userid;
                $data->episodes = $episodes;
                insert_record('artefact_media_quota_override', $data);
            }
            catch (Exception $e) {
                throw new InvalidArgumentException("Failed to insert media quota override "
                    ." $episodes for user $userid");
            }
        }
    }

    /**
     * Checks whether there is enough room in a user's quota for an upload to be allowed
     *
     * @param int $userid
     * @return bool
     */
    public static function upload_allowed($userid) {

        $quota = self::get_quota($userid);

        $usedquota = self::get_quota_used($userid);

        if (($quota->value - $usedquota) >= 1) {
            return true;
        }

        return false;
    }

}

/**
 * This class defines the episode artefact type, including all the stuff for uploading and managing
 * episodes.
 */
class ArtefactTypeEpisode extends ArtefactType {

    // File size of the episode
    protected $size;

    // duration in seconds
    protected $duration;

    // the filename on the flash streaming server, with underscores substituted for some characters
    protected $streamingfilename;

    // the type of file we are dealing with
    protected $filetype;

    // so we can stick it back on if needed for the streaming url.
    protected $originalextension;

    protected $account;

    protected $originalfilename;

    protected $localfilelocation;

    // holds the filename that will be sent to the streaming server. Essential to avoid collisions.
    protected $filename;

    // The institution that owns the episodes and feeds - the one that pays for the episodes to be hosted
    protected $institution;

    // the web address of the flash streaming server
    protected $uploadurl;

    public function __construct($id = 0, $data = null) {
        parent::__construct($id, $data);

        if ($this->id && ($filedata = get_record('artefact_media_episode', 'artefact', $this->id))) {
            foreach($filedata as $name => $value) {
                if (property_exists($this, $name)) {
                    $this->{$name} = $value;
                }
            }
        }

        if (empty($this->id)) {
            $this->container = 0;
        }

        $this->uploadurl = 'http://w01.ulccfs.wf.ulcc.ac.uk/cgi-bin/upload';
    }

    /**
     * Whether there should be a config link in the plugin admin screens. Needs the get_config_options()
     * function to make the form
     * @return <type>
     */
    public static function has_config() {
        return true;
    }

    /**
     * This function sends the form definition, which is found on
     * site administration -> plugins administration -> atrefacttype episode
     * @return <type>
     */
    public static function get_config_options() {
        $elements = array();
        $defaultquota = get_config_plugin('artefact', 'media', 'defaultquota');
        if (empty($defaultquota)) {
            $defaultquota = 4;
        }
        $options = range(0, 10, 1);
        $options[9999] = 'unlimited';
       // unset($options[0]);
        $elements['quotafieldset'] = array(
            'type' => 'fieldset',
            'legend' => get_string('defaultquota', 'artefact.media'),
            'elements' => array(
                'defaultquotadescription' => array(
                    'value' => '<tr><td colspan="2">' . get_string('defaultquotadescription', 'artefact.media') . '</td></tr>'
                ),
                'defaultquota' => array(
                    'title'        => get_string('defaultquota', 'artefact.media'),
                    'type'         => 'select',
                    'defaultvalue' => $defaultquota,
                    'options'      => $options
                )
            ),
            'collapsible' => true
        );

        // Make new LDAP config record

        // Might be multiple institutions - make a select if there is.
        $ldapelements = array();
        $institutions = get_records_array('auth_instance', 'authname', 'ldap', '', 'institution');
//        $sql = "SELECT institution FROM {auth_instance} ai INNER JOIN {institutions} i WHERE ai.authname = 'ldap' AND i.suspended = 0";
//        $institutions = get_records_sql_array($sql, array());

        if ($institutions) {

            $institution = $institutions[0]->institution;

            // unfinished code for if we have two institutions
//            if (count($institutions) > 1) {
//
//                $ldapelements['institutiondescription'] = array(
//                        'value' => '<tr><td colspan="2">' . get_string('institutiondescription', 'artefact.media') . '</td></tr>'
//                );
//
//                $ldapelements['institution'] = array(
//                        'title' => get_string('institution'),
//                        'type' => 'select',
//                        'options' => $institutions
//                );
//            } else {
                // no need for a select, just use a hidden value
                $elements['institution'] = array(
                        'type' => 'hidden',
                        'value' => $institution
                );
//            }

            $ldapelements = array_merge($ldapelements, array(
                'ldapquotadescription' => array(
                    'value' => '<tr><td colspan="2">' . get_string('ldapquotadescription', 'artefact.media') . '</td></tr>'
                ),
                'ldapou' => array(
                    'title'        => get_string('ldapou', 'artefact.media'),
                    'type'         => 'blanktext',
                    'defaultvalue' => '',
                    'size'         => '50'
                ),
                'quota' => array(
                    'title'        => get_string('quota'),
                    'type'         => 'ldapquotachooser',
                    'defaultvalue' => $defaultquota,
                    'options'      => $options

                )
            ));

            // View existing LDAP config records
            $ldapelements['quotaslist'] = array(
                    'title'        => get_string('existingquotas', 'artefact.media'),
                    'type'         => 'quotaslist',
                    'institution'  => $institution,
                    'ldapou'       => '',
                    'quotaoptions' => $options
            );

        } else {
            // warning message - no institutions configured
            $ldapelements = array(
                'ldapquotadescription' => array(
                    'value' => '<tr><td colspan="2">' . get_string('noinstitutionsconfigured', 'artefact.media') . '</td></tr>'
                )
            );
        }

        // put it all into a fieldset
        $elements['ldapfieldset'] = array(
            'type' => 'fieldset',
            'legend' => get_string('ldapquota', 'artefact.media'),
            'elements' => $ldapelements
        );


        // Require user agreement before uploading files
        // Rework this when/if we provide translatable agreements
        $uploadagreement = get_config_plugin('artefact', 'media', 'uploadagreement');
        $usecustomagreement = get_config_plugin('artefact', 'media', 'usecustomagreement');
        $elements['uploadagreementfieldset'] = array(
            'type' => 'fieldset',
            'legend' => get_string('uploadagreement', 'artefact.media'),
            'elements' => array(
                'uploadagreementdescription' => array(
                    'value' => '<tr><td colspan="2">' . get_string('uploadagreementdescription', 'artefact.media') . '</td></tr>'
                ),
                'uploadagreement' => array(
                    'title'        => get_string('requireagreement', 'artefact.media'),
                    'type'         => 'checkbox',
                    'defaultvalue' => $uploadagreement,
                ),
                'defaultagreement' => array(
                    'type'         => 'html',
                    'title'        => get_string('defaultagreement', 'artefact.media'),
                    'value'        => get_string('uploadcopyrightdefaultcontent', 'install'),
                ),
                'usecustomagreement' => array(
                    'title'        => get_string('usecustomagreement', 'artefact.media'),
                    'type'         => 'checkbox',
                    'defaultvalue' => $usecustomagreement,
                ),
                'customagreement' => array(
                    'name'         => 'customagreement',
                    'title'        => get_string('customagreement', 'artefact.media'),
                    'type'         => 'wysiwyg',
                    'rows'         => 10,
                    'cols'         => 80,
                    'defaultvalue' => get_field('site_content', 'content', 'name', 'mediauploadcopyright'),
                ),
            ),
            'collapsible' => true
        );

        return array(
            'elements'   => $elements,
            'renderer'   => 'table',
            'configdirs' => array(get_config('libroot') . 'form/', get_config('docroot') . 'artefact/media/form/')
        );
    }

    /**
     * Callback for the pieforms library that takes the values from the form defined in get_config_options()
     * and saves them
     *
     * @global <type> $USER
     * @param <type> $values
     */
    public static function save_config_options($values) {
        global $USER;
        set_config_plugin('artefact', 'media', 'defaultquota', $values['defaultquota']);
        set_config_plugin('artefact', 'media', 'uploadagreement', $values['uploadagreement']);
        set_config_plugin('artefact', 'media', 'usecustomagreement', $values['usecustomagreement']);

        // Save the text for the custom agreement
        if ($values['customagreement']) {
            $data = new StdClass;
            $data->name    = 'mediauploadcopyright';
            // TODO - strip tags here?
            $data->content = $values['customagreement'];
            $data->mtime   = db_format_timestamp(time());
            $data->mauthor = $USER->get('id');
            if (record_exists('site_content', 'name', $data->name)) {
                update_record('site_content', $data, 'name');
            }
            else {
                insert_record('site_content', $data);
            }
        }

        // save the new LDAP quota if there is one
        if (isset($values['ldapou']) && !empty($values['ldapou'])) {

            if (empty($values['quota'])) {
                // TODO error here - you need to have a quota
                //throw new ;
            }

            $data = new StdClass;
            $data->institution    = $values['institution'];
            // TODO - strip tags here?
            $data->ldapou         = $values['ldapou'];
            $data->quota          = $values['quota'];
            if (record_exists('artefact_media_ldap_quota', 'ldapou', $data->ldapou)) {
                update_record('artefact_media_ldap_quota', $data, 'ldapou');
            }
            else {
                insert_record('artefact_media_ldap_quota', $data);
            }

        }

        if (!empty($values['quotaslist'])) {
            foreach ($values['quotaslist'] as $key => $quotatodelete) {
                // must delete associated student quota records, otherwise foreign key constraint fails
                delete_records('artefact_media_student_quota', 'mediaquota', $key);
                delete_records('artefact_media_ldap_quota', 'id', $key);
            }
        }
    }

    /**
     * Once the file has been put onto the streaming server, this function is called to delete it locally
     *
     * @return void
     */
    public function delete_local_file() {

        unlink($this->localfilelocation);

        $directorybits = explode('/', $this->localfilelocation);
        array_pop($directorybits);
        $directory = implode('/', $directorybits);

        // is the directory empty?
        if (($files = @scandir($directory)) && count($files) <= 2) {
            rmdir($directory);
        }

    }

    /**
     * The streming server makes a thumbnail for video it encodes, available at a standard url based
     * on account details and filename. This constructs it.
     *
     * @param array $options
     * @return string the url
     */
    public static function get_icon($options=null) {
        // TODO there will be no icon if the file is audio. Need a default one based on file type
        $icon = 'http://streaming.ulcc.ac.uk/media/'.$options['account'].'/'.$options['feed'].'/'.$options['streamingfilename'].'.jpg';
        return $icon;
    }

    /**
     * The standard streaming url for an episode is constructed here based on data supplied
     *
     * @param object $data
     * @return string the url
     */
    public static function get_streaming_url($data) {

        $url = 'http://streaming.ulcc.ac.uk/progress.php/';
        // Hopefully, all students will have an automatic membership of an intitution
        $url .= $data->account.'/';
        $url .= $data->feed.'/'.$data->streamingfilename.'.'.$data->originalextension;

        return $url;
    }

    /**
     * This function updates or inserts the artefact.  This involves putting
     * some data in the artefact table (handled by parent::commit()), and then
     * some data in the artefact_file_files table.
     */
    public function commit() {
        // Just forget the whole thing when we're clean.
        if (empty($this->dirty)) {
            return;
        }

        // We need to keep track of newness before and after.
        $new = empty($this->id);

        // Commit to the artefact table.
        parent::commit();

        $this->localfilelocation = get_config('dataroot').self::get_file_directory($this->get('id')).'/'.$this->originalfilename;

        // Reset dirtyness for the time being.
        $this->dirty = true;

        // TODO this should gather only the media episode details that don't fit in the artefacts
        // table
        $data = (object)array(
                'artefact'          => $this->get('id'),
                'size'              => $this->get('size'),
                'filetype'          => $this->get('filetype'),
                'streamingfilename' => $this->get('streamingfilename'),
                'account'           => $this->get('account'),
                'originalextension' => $this->get('originalextension')

        );

        if ($new) {
            insert_record('artefact_media_episode', $data);
        }
        else {
            update_record('artefact_media_episode', $data, 'artefact');
        }

        $this->dirty = false;
    }

    /**
     * Internal Mahara function - can we have mroe than one episode?
     *
     * @return bool
     */
    public static function is_singular () {
        return false;
    }

    /**
     * No idea what this does. We don't ned it and it just has to be here as it'sd abstract in the
     * parent class
     *
     * @param <type> $id
     * @retun void
     */
    public static function get_links($id) {

    }

    /**
     * Creates pieforms definition for the streaming media page - particularly the main
     * filebrowser
     *
     * @global  $USER
     * @param <type> $page
     * @param <type> $group
     * @param <type> $account
     * @param <type> $folder
     * @param <type> $highlight
     * @param <type> $edit
     * @return boolean
     */
    public static function upload_form($page='', $group=null, $account=null, $folder=null, $highlight=null, $edit=null) {

        global $USER;

        $folder = param_integer('folder', 0);
        $edit = param_variable('edit', 0);
        if (is_array($edit)) {
            $edit = array_keys($edit);
            $edit = $edit[0];
        }
        $edit = (int) $edit;
        $highlight = null;
        if ($file = param_integer('file', 0)) {
            $highlight = array($file); // todo convert to file1=1&file2=2 etc
        }

        // Possibly, there will be more than one institution, so we might need to offer an option
        // $institutions = get_records_assoc('usr_institution', 'usr', $USER->id);
        $institutions = load_user_institutions($USER->get('id'));

        if (!empty($institutions)) {

            if (count($institutions) >= 1) {
                // need to show a dropdown
                // TODO does this work? - No if there are more than 1 - error on line 1067 where the data object gets
                // the stuff below instead of a single string
                // TODO should check that the institution has any quota available
//                $account = array (
//                        'type'  => 'dropdown',
//                        'title' => 'account',
//                        'multiple' => true,
//                        'values'   => $institutions
//
//                );
//
//            } else {
                // just the one
                $account = str_replace(' ', '', array_shift(array_keys($institutions)));
            }
        } else {

            throw new UserNotInInstitutionException();

        }

        $form = array(
            'name'               => 'media',
            'jsform'             => true,
            'newiframeonsubmit'  => true,
            'jssuccesscallback'  => 'media_success',
            'jserrorcallback'    => 'media_success',
            'renderer'           => 'oneline',
            'plugintype'         => 'artefact',
            'pluginname'         => 'media',
            'configdirs'         => array(get_config('libroot') . 'form/', get_config('docroot') . 'artefact/media/form/'),
            'group'              => $group,
            'account'            => $account,
            'elements'           => array(

                'filebrowser' => array(
                    'type'         => 'filebrowser',
                    'highlight'    => $highlight,
                    'edit'         => $edit,
                    'page'         => $page,
                    'config'       => array(
                        'upload'          => true,
                        'uploadagreement' => true,
                        'edit'            => true,
                        'select'          => false,
                    ),
                ),

            ),
        );

        return $form;
    }

    /**
     * Provides the function that acts as a callback for the ajax stuff on the main streaming media page
     *
     * @return string some javascript stuff saying what function to run
     */
    public static function media_js() {
        return "function media_success(form, data) { media_filebrowser.success(form, data); }";
    }

    /**
     * Adds a file to the streaming server. At the moment, it is possible that the streaming server
     * will return error text and this will be interpreted as OK. Failiure is only if it can't connect.
     *
     * @return bool|string Did it work? Return whatever the server says
     */
    protected function upload_to_streaming_server() {

        // open a curl handle to the server
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->uploadurl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // TODO - proper feedname, accountname etc
        $post = array(
                'accountname' => $this->account,
                'feedname'    => $this->owner,
                'filename'    => $this->filename,
                'file'        => '@'.$this->localfilelocation

        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

        $result = curl_exec($ch);
        // close the curl handle
        curl_close ($ch);

        // send it & keep the returned code
        return $result;

    }

    /**
     * Check if an episode exists in the db with a given title and owner.
     *
     * @param string $title
     * @param int $owner
     * @param string $institution
     * @param int $group
     * @return bool|int
     */
    public static function episode_exists($title, $owner, $institution=null, $group=null) {
        $filetypesql = "('" . join("','", array_diff(PluginArtefactMedia::get_artefact_types(), array('profileicon'))) . "')";
        $ownersql = artefact_owner_sql($owner, $group, $institution);
        return get_field_sql('SELECT a.id
                                FROM {artefact} a
                          INNER JOIN {artefact_media_episode} e
                                  ON e.artefact = a.id
                               WHERE a.title = ?
                                 AND a.' . $ownersql . '
                                 AND a.parent  IS NULL
                                 AND a.artefacttype IN ' . $filetypesql, array($title));
    }

    /**
     * Does what it says on the tin. Error handling is only up to 'can I connect to the server'
     * and will not catch errors from the streaming server should deletion fail
     *
     * @return bool|string
     */
    public function delete_from_streaming_server() {

        // open a curl handle to the server
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->uploadurl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        //$localfilelocation = get_config('dataroot').self::get_file_directory($this->get('id')).'/'.$this->originalfilename;

        // TODO - proper feedname, accountname etc
        $post = array(
                'accountname' => $this->account,
                'feedname'    => $this->owner,
                'filename'    => $this->streamingfilename.'.'.$this->originalextension,
                'delete'      => 1

        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

        $result = curl_exec($ch);
        // close the curl handle
        curl_close ($ch);

        // send it & keep the returned code
        return $result;
    }

    /**
     * Processes a newly uploaded file, copies it to disk, and creates a new artefact object.
     * Takes the name of a file input. Returns false for no errors, or a string describing the error.
     *
     * @global  $USER
     * @param string $inputname filename
     * @param object $data beginnings of an artefact object
     * @return bool|string
     */
    public static function save_uploaded_file($inputname, $data) {

        global $USER;

        require_once('uploadmanager.php');
        $um = new upload_manager($inputname);
        if ($error = $um->preprocess_file()) {
            throw new UploadException($error);
        }
        $data->size = $um->file['size'];
        if (!empty($data->owner)) {

            if ($data->owner == $USER->get('id')) {
                $owner = $USER;
            }
            else {
                $owner = new User;
                $owner->find_by_id($data->owner);
            }
            // Don't want to do this as we will delete the file again once it's on the streaming server
            if (!PluginArtefactMedia::upload_allowed($USER->get('id'))) {
                throw new QuotaExceededException(get_string('uploadexceedsquota', 'artefact.media'));
            }
        }

        if ($um->file['type'] == 'application/octet-stream') {
            // the browser wasn't sure, so use file_mime_type to guess
            require_once('file.php');
            $data->filetype = file_mime_type($um->file['tmp_name']);
        }
        else {
            $data->filetype = $um->file['type'];
        }

        $data->originalextension = $um->original_filename_extension();
        $data->originalfilename = $um->file['name'];

        // Avoid filename collisions
        $data->filename = ArtefactTypeEpisode::get_new_file_title($data->originalfilename, $data->owner, null, null);
        $data->streamingfilename = ArtefactTypeEpisode::get_streaming_filename($data->filename, false);

        $f = new ArtefactTypeEpisode(0, $data);

        // adds a record to the artefact table
        $f->commit();

        $id = $f->get('id');

        // Save the file using its id as the filename, and use its id modulo
        // the number of subdirectories as the directory name.
        if ($error = $um->save_file(self::get_file_directory($id) , $um->file['name'])) {
            $f->delete();
            throw new UploadException($error);
        }

        // Now that the file is saved locally, upload it to the streaming server
        $streaminguploadresult = $f->upload_to_streaming_server();

        // TODO This will not pick up verbose error messages from the streaming server, only a curl
        // failure e.g. a server outage
        if (!$streaminguploadresult) {

            $f->delete_local_file();
            $f->delete();

            throw new UploadException('failed to save to streaming server');
        }

        // Delete the file, now that we have a streaming version
        $f->delete_local_file();

        // Return the id of the media episode
        return $id;
    }

    /**
     * Gets the filename as it is on the streaming server - i.e. with underscores replacing some
     * characters.
     *
     * @TODO - might be other characters that need replacing
     *
     * @param bool $includeextension Do we want the original extension to the file to be retained?
     * @return string the filename
     */
    protected static function get_streaming_filename($filename, $includeextension=false) {

        $bits = explode('.', $filename);

        // dump the bit after the last dot
        $extension = array_pop($bits);

        // stick the rest back together - there may have been extra dots in the filename
        $bits = implode('.', $bits);

        $filename = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $bits);

        if ($includeextension) {
            $filename .= '.'.$extension;
        }

        return $filename;
    }

//    /**
//     *
//     * @param <type> $artefactid
//     * @return <type>
//     */
//    public function get_thumbnail_url($artefactid) {
//
//        return 'http://w01.ulccfs.wf.ulcc.ac.uk/media/'.$account.'/'.$feed.'/'.$streamingfilename;
//
//    }


    /**
     * Where to store the file temporarily once uploaded to Mahara. Doesn't really need to be this complex.
     *
     * @param int $id
     * @return string The directory to use
     */
    public static function get_file_directory($id) {
        return "artefact/media/originals/" . ($id % 256);
    }

    /**
     * Gets a list of files in one folder
     *
     * @param integer $userid            Id of the owner, if the owner is a user
     * @param integer $group             Id of the owner, if the owner is a group
     * @param string  $institution       Id of the owner, if the owner is a institution
     * @param array   $filters           Filters to apply to the results. An array with keys 'artefacttype', 'filetype',
     *                                   where array values are arrays of artefacttype or mimetype strings.
     * @return array  A list of artefacts
     */
    public static function get_my_episodes_data($userid, $group=null, $institution=null, $filters=null) {
        global $USER;

        // TODO - we need to find out the size of the media episode and/or it's duration (probably better)

        $select = '
            SELECT
                a.id, a.artefacttype, a.owner, a.mtime, a.title, a.description, p.account, p.streamingfilename, p.originalextension, p.size,
                COUNT(DISTINCT c.id) AS childcount, COUNT (DISTINCT aa.artefact) AS attachcount, COUNT(DISTINCT va.view) AS viewcount';
        $from = '
            FROM {artefact} a
                     INNER JOIN {artefact_media_episode} p ON p.artefact = a.id

                LEFT OUTER JOIN {artefact} c ON c.parent = a.id
                LEFT OUTER JOIN {view_artefact} va ON va.artefact = a.id
                LEFT OUTER JOIN {artefact_attachment} aa ON aa.attachment = a.id';

        $artefacttypes = array_diff(PluginArtefactMedia::get_artefact_types(), array('profileicon'));

        $where = "
            WHERE a.artefacttype IN (" . join(',',  array_map('db_quote', $artefacttypes)) . ")";


        $groupby = '
            GROUP BY
                a.id, a.artefacttype, a.mtime, a.title, a.description';

        $phvals = array();

        if ($institution) {

            $where .= '
            AND a.institution = ? AND a.owner IS NULL';
            $phvals[] = $institution;
        } else if ($group) {
            $select .= ',
                r.can_edit, r.can_view, r.can_republish';
            $from .= '
                LEFT OUTER JOIN (
                    SELECT ar.artefact, ar.can_edit, ar.can_view, ar.can_republish
                    FROM {artefact_access_role} ar
                    INNER JOIN {group_member} gm ON ar.role = gm.role
                    WHERE gm.group = ? AND gm.member = ?
                ) r ON r.artefact = a.id';
            $phvals[] = $group;
            $phvals[] = $USER->get('id');
            $where .= '
            AND a.group = ? AND a.owner IS NULL AND r.can_view = 1';
            $phvals[] = $group;
            $groupby .= ', r.can_edit, r.can_view, r.can_republish';
        } else {
            $where .= '
            AND a.institution IS NULL AND a.owner = ?';
            $phvals[] = $userid;
        }

        $where .= '
        AND a.parent IS NULL';

        $filedata = get_records_sql_assoc($select . $from . $where . $groupby, $phvals);

        if (!$filedata) {
            $filedata = array();
        } else {

            foreach ($filedata as $item) {
                $item->mtime = format_date(strtotime($item->mtime), 'strfdaymonthyearshort');
                $item->tags = array();
                $icondata = array(
                        //'id' => $item->id,
                        'account' => $item->account,
                        'feed' => $USER->get('id'),
                        'streamingfilename' => $item->streamingfilename
                );
                $item->icon = call_static_method(generate_artefact_class_name($item->artefacttype), 'get_icon', $icondata);
                $item->feed = $USER->get('id');

                $item->link = call_static_method(generate_artefact_class_name($item->artefacttype), 'get_streaming_url', $item);

                // TODO - fix this so it works for durations
                if ($item->size) { // Doing this here now for non-js users
                    $item->size = ArtefactTypeEpisode::short_size($item->size, true);
                }
            }
            $where = 'artefact IN (' . join(',', array_keys($filedata)) . ')';
            $tags = get_records_select_array('artefact_tag', $where);
            if ($tags) {
                foreach ($tags as $t) {
                    $filedata[$t->artefact]->tags[] = $t->tag;
                }
            }
            if ($group) {  // Fetch permissions for each artefact
                $perms = get_records_select_array('artefact_access_role', $where);
                if ($perms) {
                    foreach ($perms as $perm) {
                        $filedata[$perm->artefact]->permissions[$perm->role] = array(
                            'view' => $perm->can_view,
                            'edit' => $perm->can_edit,
                            'republish' => $perm->can_republish
                        );
                    }
                }
            }
        }

        return $filedata;
    }

    /**
     * Makes a nice human friendly file size for displaying the list of existing files
     *
     * @param int $bytes
     * @param bool $abbr do we use 'b', or look for a language string?
     * @return string
     */
    public static function short_size($bytes, $abbr=false) {
        if ($bytes < 1024) {
            return $bytes <= 0 ? '0' : ($bytes . ($abbr ? 'b' : (' ' . get_string('bytes', 'artefact.media'))));
        }
        if ($bytes < 1048576) {
            return floor(($bytes / 1024) * 10 + 0.5) / 10 . 'K';
        }
        return floor(($bytes / 1048576) * 10 + 0.5) / 10 . 'M';
    }

    /**
     * Deletes this episode from both the streaming server and Mahara
     *
     * @return void
     */
    public function delete() {

        if (empty($this->id)) {
            return;
        }

        // TODO - permissions check

        // Delete the streaming thing on the other server
        $result = $this->delete_from_streaming_server();

        if (!$result) {
            throw new UploadException('failed to delete from streaming server');
        }

        // TODO - this appears not to exists in Mahara 1.3.x. What happened to it?
        // Detach this episode from any view feedback (should be impossible for students to delete
        // if there is any of this)
        // set_field('view_feedback', 'attachment', null, 'attachment', $this->id);

        delete_records('artefact_media_episode', 'artefact', $this->id);
        parent::delete();
    }

    /**
     * Return a unique filename for a given owner & parent. The streaming server will not upload a
     * file with a duplicate name, so this stops the problem.
     *
     * Try to add digits before the filename extension: If the desired
     * title contains a ".", add "." plus digits before the final ".",
     * otherwise append "." and digits.
     *
     * @param string $desired
     * @param integer $owner
     * @param integer $group
     * @param string $institution
     * @return string
     */
    public static function get_new_file_title($desired, $owner=null, $group=null, $institution=null) {
        $bits = split('\.', $desired);
        if (count($bits) > 1 && preg_match('/[^0-9]/', end($bits))) {
            $start = join('.', array_slice($bits, 0, count($bits)-1));
            $end = '.' . end($bits);
        }
        else {
            $start = $desired;
            $end = '';
        }

        $nametotest = ArtefactTypeEpisode::get_streaming_filename($desired, false);

        $where = 'parent IS NULL';
        $where .=  ' AND ' . artefact_owner_sql($owner, $group, $institution);

        $taken = get_column_sql("
                SELECT e.streamingfilename
                  FROM {artefact} a
            INNER JOIN {artefact_media_episode} e
                    ON a.id = e.artefact
                 WHERE a.artefacttype IN ('" . join("','", array_diff(PluginArtefactMedia::get_artefact_types(), array('profileicon'))) . "')
                   AND e.streamingfilename LIKE ? || '%' AND " . $where, array($nametotest));
        $taken = array_flip($taken);

        $i = 0;
        $newname = $start . $end;
        while (isset($taken[ArtefactTypeEpisode::get_streaming_filename($newname, false)])) {
            $i++;
            $newname = $start . '.' . $i . $end;
        }
        return $newname;
    }

    /**
     * Fetches an artefact record with associated data from the artefact_media_episode table
     *
     * @param int $id
     * @return bool|array
     */
    public function get_episode_by_id($id) {

        $sql = "SELECT *
                  FROM {artefact} a
            INNER JOIN {artefact_media_episode} e
                    ON e.artefact = a.id
                 WHERE a.id = ?";

        return get_records_sql_assoc($sql, array($id));

    }


}


/**
 * Class which contains modified bits of the AuthLdap plugin. It's not possible to subclass it as
 * the methods of interest are declared private.
 */
class media_ldap_auth extends Auth {

     public function __construct($id = null) {
        $this->type = 'ldap';
        $this->has_instance_config = true;

        $this->config['host_url'] = '';
        $this->config['contexts'] = '';
        $this->config['user_type'] = 'default';
        $this->config['user_attribute'] = '';
        $this->config['search_sub'] = 'yes';
        $this->config['bind_dn'] = '';
        $this->config['bind_pw'] = '';
        $this->config['version'] = '2';
        $this->config['updateuserinfoonlogin'] = 0;
        $this->config['weautocreateusers'] = 1;
        $this->config['firstnamefield' ] = '';
        $this->config['surnamefield'] = '';
        $this->config['emailfield'] = '';

        if (!empty($id)) {
            return $this->init($id);
        }
        return true;
    }

    /**
     * Checks that everything is ready to go
     *
     * @param int $id The id of the auth instance
     * @return <type>
     */
    public function init($id = null) {
        $this->ready = parent::init($id);

        // Check that required fields are set
        if ( empty($this->config['host_url']) ||
             empty($this->config['contexts']) ||
             empty($this->config['user_attribute']) ||
             empty($this->config['version']) ||
             empty($this->config['search_sub']) ) {
            $this->ready = false;
        }

        return $this->ready;
    }

    public function get_user_ou($username) {

        $ldapconnection = $this->ldap_connect();

        // full DN comes back
        $userinfo = $this->ldap_find_userdn($ldapconnection, $username);

        if (!$userinfo) {
            return false;
        }

        // leaves us with 'OU=whatever'
        $userinfo = explode(',', $userinfo);
        $ou = $userinfo[1];

        // leaves us with the plain text name of the OU. Assumes that there is only 'CN=firstname lastname'
        // before the OU bit and that we want the first OU
        $ou = explode('=', $ou);
        $ou = trim($ou[1]);

        return $ou;
    }

    /**
     * Returns all OUs starting with a particular fragment for the admin form
     *
     * @param fragment $fragment whatever is already in the text input of the form
     * return arr
     */
    public function autocomplete($fragment) {

        // TODO cache all the LDAP stuff in SESSION to prevent jaming up AD as people type

//        $ldapconnection = $this->ldap_connect();
//
//        foreach ($ldapdns as $ldapdn) {
//
//            $this->
//
//        }
//
//

    }

    private function list_ous($connection, $root) {

    }

    /**
     * Connects to ldap server
     *
     * Tries to connect to specified ldap servers.
     * Returns connection result or error.
     *
     * @return connection result
     */
    private function ldap_connect($binddn='',$bindpwd='') {
        // Select bind password, With empty values use
        // ldap_bind_* variables or anonymous bind if ldap_bind_* are empty
        if ($binddn == '' and $bindpwd == '') {
            if (!empty($this->config['bind_dn'])) {
               $binddn = $this->config['bind_dn'];
            }
            if (!empty($this->config['bind_pw'])) {
               $bindpwd = $this->config['bind_pw'];
            }
        }

        $urls = explode(";", $this->config['host_url']);

        foreach ($urls as $server) {
            $server = trim($server);
            if (empty($server)) {
                continue;
            }

            $connresult = ldap_connect($server);
            // ldap_connect returns ALWAYS true

            if (!empty($this->config['version'])) {
                ldap_set_option($connresult, LDAP_OPT_PROTOCOL_VERSION, $this->config['version']);
            }

            if ($this->config['user_type'] == 'ad') {
                 ldap_set_option($connresult, LDAP_OPT_REFERRALS, 0);
            }

            if (!empty($binddn)) {
                // bind with search-user
                $bindresult = ldap_bind($connresult, $binddn,$bindpwd);
            }
            else {
                // bind anonymously
                $bindresult = @ldap_bind($connresult);
            }

            if (!empty($this->config->opt_deref)) {
                ldap_set_option($connresult, LDAP_OPT_DEREF, LDAP_DEREF_NEVER); // latter is an option in Moodle
            }

            if ($bindresult) {
                return $connresult;
            }

        }

        // If any of servers are alive we have already returned connection
        return false;
    }


    /**
     * retuns dn of username
     *
     * Search specified contexts for username and return user dn
     * like: cn=username,ou=suborg,o=org
     *
     * @param mixed $ldapconnection  $ldapconnection result
     * @param mixed $username username (external encoding no slashes)
     *
     */
    private function ldap_find_userdn($ldapconnection, $username) {
        // default return value
        $ldap_user_dn = FALSE;

        // get all contexts and look for first matching user
        $ldap_contexts = explode(";", $this->config['contexts']);

        foreach ($ldap_contexts as $context) {
            $context = trim($context);
            if (empty($context)) {
                continue;
            }

            if ($this->config['search_sub'] == 'yes') {
                // use ldap_search to find first user from subtree
                $ldap_result = ldap_search($ldapconnection, $context, '(' . $this->config['user_attribute']
                    . '=' . $this->filter_addslashes($username) . ')', array($this->config['user_attribute']));

            }
            else {
                // search only in this context
                $ldap_result = ldap_list($ldapconnection, $context, '(' . $this->config['user_attribute']
                    . '=' . $this->filter_addslashes($username) . ')', array($this->config['user_attribute']));
            }

            $entry = ldap_first_entry($ldapconnection,$ldap_result);

            if ($entry) {
                $ldap_user_dn = ldap_get_dn($ldapconnection, $entry);
                break ;
            }
        }
        return $ldap_user_dn;
    }

    /**
     * We can autocreate users if the admin has said we can
     * in weautocreateusers
     */
    public function can_auto_create_users() {
        return (bool)$this->config['weautocreateusers'];
    }

    /**
     * Quote control characters in texts used in ldap filters - see rfc2254.txt
     *
     * @param string
     */
    private function filter_addslashes($text) {
        $text = str_replace('\\', '\\5c', $text);
        $text = str_replace(array('*',    '(',    ')',    "\0"),
                            array('\\2a', '\\28', '\\29', '\\00'), $text);
        return $text;
    }


}

/**
 * Allows a proper error to be shown when a user is not part of an institution
 */
class UserNotInInstitutionException extends UserException {
    public function strings() {
        return array_merge(parent::strings(),
            array('message' => get_string('noinstitutionmessage', 'artefact.media')),
            array('title'   => get_string('noinstitution', 'artefact.media')));
    }
}


?>
