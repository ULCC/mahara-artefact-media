<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
define('INTERNAL', 1);
$file = dirname(dirname(dirname(__FILE__))) . '/init.php';
require($file);
//echo 'require ok';

// instantiate the ldap auth object
include_once(get_config('docroot').'auth/ldap/lib.php');

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

    public function get_user_info($username) {
        // get the attribute field names
//        $attributes = array();
//        $attributes['firstname'] = $this->config['firstnamefield'];
//        $attributes['lastname']  = $this->config['surnamefield' ];
//        $attributes['email']     = $this->config['emailfield'];
//        $attributes['dn']        = 'dn';
//
        $ldapconnection = $this->ldap_connect();

        // full DN comes back
        $userinfo = $this->ldap_find_userdn($ldapconnection, $username);

        // leaves us with 'OU=whatever'
        $userinfo = explode(',', $userinfo);
        $ou = $userinfo[1];

        // leaves us with the plain text name of the OU
        $ou = explode('=', $ou);
        $ou = trim($ou[1]);


        //$userinfo = $this->get_userinfo_ldap($username, $attributes);

        return $ou;
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
 * This is called when there is no ldap record, or the record has expired. It either creates a student
 * record (and ldapou on e if needed), or it updates it to current
 */
function set_ldap_quota($userid) {

    // get user details
    $user = get_user($userid);
    $defaultquota = get_config_plugin('artefact', 'media', 'defaultquota');

    

    $institutions = load_user_institutions($userid);

    // Find the correct auth instance, i.e. the ldap one for the user's insttution
    // Note this only uses the first institution available.
    $institution = array_shift(array_keys($institutions));
    $authid = get_field('auth_instance', 'id', 'institution', $institution, 'authname', 'ldap');

    // get the ldap user info
    $ldapobject = new media_ldap_auth($authid);
    $userdata = $ldapobject->get_user_info($user->username);

    // ldap connection problem
    if (!$userdata) {
        return false;
    }
    
    //TODO - what are the ldap attributes that actually come back?

    // is there a mediaquota record for this ldap group at this institution?
    $mediaquota = get_record('artefact_media_ldap_quota', 'institution', $institution, 'ldapou', $userdata->ldapou);

    // if not, make one
    if (!$mediaquota) {

        // use default
        $mediadataobject = new stdClass;
        $mediadataobject->institution = $institution;
        $mediadataobject->ldapou = $userdata->ldapou;
        $mediadataobject->quota = $defaultquota;

        $mediaquota = new stdClass;
        $mediaquota->id = insert_record('artefact_media_ldap_quota', $mediadataobject);
    }

    // construct the student quota data object
    $studentdataobject = new stdClass;
    $studentdataobject->userid = $userid;
    $studentdataobject->mediaquota = $mediaquota->id;
    $studentdataobject->timemodified = time();

    // commit to the db
    insert_record('artefact_media_student_quota', $studentdataobject);

    // return the stored quota, or the default if there wasn't one
    return (isset($mediaquota->quota)) ? $mediaquota->quota : $defaultquota;


}

$result = set_ldap_quota(2);

echo 'done.';


?>
