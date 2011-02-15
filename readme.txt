This plugin links Mahara with the ULCC flash streaming server

Installation involves the following steps:

1. copy this directory to /artefact/medi
2. Make the changes to the core as detailed below (bug fixes - not needed if you're using 1.4.x)
3. visit the plugin administration page in the admin section of the site and install first the
   artefacttype media, then refresh the page and install the blocktype episode.

Configuraton:
- configuration is under the 'config' link by the episode blocktype on the plugin administration page.
  You can set the site default quota for all students here, and add specific quotas for LDAP groups
  too, although this will depend on having LDAP set up as described below.
- LDAP setup is as per normal for Mahara. Your institution will need LDAP added as an authentication
  method, which is covered by the main Mahara docs. The plugin depends on there being only one
  institution that a student can be part of, so LDAP quotas will not work well if you have two
  ('no institution' doesn't matter on the institutions list). If you need to have more than one,
  the code will need tweaking, so get in touch.
- The LDAP quotas use OUs NOT groups. If this doesn't match your Active Directory structure, get in
  touch and we can modify the code.
- The quotas are used in preference to the main site one, so you can either use them to raise or
  lower the number of episodes available to each group as required.
- The student's LDAP OU membership is not checked on every page load as this would be very slow,
  but is instead cached for 24 hours, so if you move a student in Active directory, they may not show
  a new quota for that length of time.

Use:
- Users see a new tab under 'My portfolio' called 'My streaming media', where episodes of any size
  can be added. The title and description are optional, defaulting to the filename and blank respectively,
  and tags cvan be added after upload by using the 'edit' button next to each episode, which also allows
  the name and description to be changed.
- Their quota is displayed in a block on the right of this page and is counted in number of episodes,
  rather than file size.
- Once an episode is uploaded, it can be previewed on the streaming server by clicking on its name,
  which will also provide embed code if it is needed on a non-mahara site.
- A new blocktype 'Streaming media episode' is available under 'Files, images and video' when editing
  a view, which allows any of the currently uploaded episodes to be added in an embedded player.



Core code changes (bug fixes):

In all Mahara versions < 1.4.0, you will need to alter part of the plugin_sanity_check() function in
artefact/lib.php, around line 1183 from

        $pluginclassname = generate_class_name('blocktype', 'image');

to

        $pluginclassname = generate_class_name('blocktype', $type);

Otherwise the plugin won't install.

Once that's done, copy the media folder to artefact/media, then go to site administration->Extensions
and look for 'media' under 'Plugin type: artefact'. Click install, then refresh the page and look for
'media/episode' under Plugin type: block' and do the same. You will now have the 'My streaming media'
tab under 'My portfolio' and the 'Streaming media episode' blocktype available in views.


You also (if you're hosting on windows) need to alter line 697 of /ib/web.php ( _get_path() )from

$plugindirectory = ($plugindirectory && substr($plugindirectory, -1) != DIRECTORY_SEPARATOR) ? $plugindirectory . DIRECTORY_SEPARATOR : $plugindirectory;

to

$plugindirectory = ($plugindirectory && substr($plugindirectory, -1) != '/') ? $plugindirectory . '/' : $plugindirectory;

Otherwise the css won't work properly
