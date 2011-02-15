<?php
/* 
 * This is the main class for the block that allows a media episode to be added to a view
 */

defined('INTERNAL') || die();

class PluginBlocktypeEpisode extends PluginBlocktype {

    public static function get_title() {
        return get_string('episodeblocktitle', 'artefact.media');
    }

    public static function get_description() {
        return get_string('episodeblockdescription', 'artefact.media');
    }

    public static function get_categories() {
        return array('fileimagevideo');
    }

    public static function get_viewtypes() {
        return array('portfolio');
    }

    public static function render_instance(BlockInstance $instance, $editing=false) {
        $configdata = $instance->get('configdata');

        if (empty($configdata['artefactid'])) {
            return '';
        }
        

        // get the artefact record
        safe_require('artefact', $instance->get('artefactplugin'));
        $episode = new ArtefactTypeEpisode($configdata['artefactid']);

        // Make a flash player with the embedded thingy
        $width  = (!empty($configdata['width'])) ? hsc($configdata['width']) : '330';
        $height = $width * (360/450);
        //$height = (!empty($configdata['height'])) ? hsc($configdata['height']) : '308';
        $account  = $episode->get('account');
        $owner    = $episode->get('owner');
        $filename = $episode->get('streamingfilename');
//        $result = "<embed  src='http://streaming.ulcc.ac.uk/mediaplayer.swf'  width='$width'  ".
//                  "allowscriptaccess='always'  allowfullscreen='true' ".
//                  "flashvars='".
//                      "autostart=false&".
//                      "image=http://streaming.ulcc.ac.uk/media/$account/$owner/$filename.jpg&amp;".
//                      "file=rtmp://flashstreaming.ulcc.ac.uk/vod/&amp;id=ulcc/$account/$owner/$filename".
//                  "'  />";

        // note: this embed code can be replaced by the javascript stuff from the flash streaming server if required.
        // see progress.php in ulccfs/docroot on the flash server
        $result = '<object id="null" width="'.$width.'" height="'.$height.'" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000">
                        <param value="true" name="allowfullscreen"/>
                        <param value="always" name="allowscriptaccess"/>
                        <param value="high" name="quality"/>
                        <param value="true" name="cachebusting"/>
                        <param value="#000000" name="bgcolor"/>
                        <param name="movie" value="http://streaming.ulcc.ac.uk/flowplayer/flowplayer-3.2.5.swf?0.6423688082267639" />
                        <param value="config=%7B%22clip%22%3A%7B%22url%22%3A%22http%3A//streaming.ulcc.ac.uk/media/'.$account.'/'.$owner.'/'.$filename.'%22%7D%2C%22playlist%22%3A%5B%7B%22url%22%3A%22http%3A//streaming.ulcc.ac.uk/media/'.$account.'/'.$owner.'/'.$filename.'.jpg%22%2C%22scaling%22%3A%22orig%22%7D%2C%7B%22scaling%22%3A%22fit%22%2C%22autoPlay%22%3Afalse%2C%22url%22%3A%22ulcc/'.$account.'/'.$owner.'/'.$filename.'%22%2C%22autoBuffering%22%3Afalse%2C%22provider%22%3A%22ulccfs%22%7D%5D%2C%22plugins%22%3A%7B%22ulccfs%22%3A%7B%22url%22%3A%22flowplayer.rtmp-3.2.3.swf%22%2C%22netConnectionUrl%22%3A%22rtmp%3A//flashstreaming.ulcc.ac.uk/vod/%22%7D%2C%22viral%22%3A%7B%22share%22%3A%7B%22description%22%3A%22'.$filename.'%22%2C%22shareUrl%22%3A%22http%3A//streaming.ulcc.ac.uk/progress.php/'.$account.'/'.$owner.'/'.$filename.'.mov%22%7D%2C%22url%22%3A%22http%3A//streaming.ulcc.ac.uk/flowplayer/flowplayer.viralvideos-3.2.3.swf%22%7D%2C%22controls%22%3A%7B%22url%22%3A%22http%3A//streaming.ulcc.ac.uk/flowplayer/flowplayer.controls-3.2.3.swf%22%7D%2C%22content%22%3A%7B%22bottom%22%3A5%2C%22backgroundColor%22%3A%22transparent%22%2C%22border%22%3A0%2C%22textDecoration%22%3A%22outline%22%2C%22height%22%3A40%2C%22backgroundGradient%22%3A%22none%22%2C%22url%22%3A%22http%3A//streaming.ulcc.ac.uk/flowplayer/flowplayer.content-3.2.0.swf%22%2C%22style%22%3A%7B%22body%22%3A%7B%22fontSize%22%3A14%2C%22fontFamily%22%3A%22Arial%22%2C%22textAlign%22%3A%22center%22%2C%22color%22%3A%22%23ffffff%22%7D%7D%7D%2C%22captions%22%3A%7B%22url%22%3A%22http%3A//streaming.ulcc.ac.uk/flowplayer/flowplayer.captions-3.2.2.swf%22%2C%22captionTarget%22%3A%22content%22%7D%7D%7D" name="flashvars"/>
                        <embed width="'.$width.'" height="'.$height.'" src="http://streaming.ulcc.ac.uk/flowplayer/flowplayer-3.2.5.swf?0.6423688082267639" type="application/x-shockwave-flash" width="450" height="360" allowfullscreen="true" allowscriptaccess="always" cachebusting="true" flashvars="config=%7B%22clip%22%3A%7B%22url%22%3A%22http%3A//streaming.ulcc.ac.uk/media/ulcc-marketing/FOTE10/10134_MilesBerry_Final%22%7D%2C%22playlist%22%3A%5B%7B%22url%22%3A%22http%3A//streaming.ulcc.ac.uk/media/'.$account.'/'.$owner.'/'.$filename.'.jpg%22%2C%22scaling%22%3A%22orig%22%7D%2C%7B%22scaling%22%3A%22fit%22%2C%22autoPlay%22%3Afalse%2C%22url%22%3A%22ulcc/'.$account.'/'.$owner.'/'.$filename.'%22%2C%22autoBuffering%22%3Afalse%2C%22provider%22%3A%22ulccfs%22%7D%5D%2C%22plugins%22%3A%7B%22ulccfs%22%3A%7B%22url%22%3A%22flowplayer.rtmp-3.2.3.swf%22%2C%22netConnectionUrl%22%3A%22rtmp%3A//flashstreaming.ulcc.ac.uk/vod/%22%7D%2C%22viral%22%3A%7B%22share%22%3A%7B%22description%22%3A%22'.$filename.'%22%2C%22shareUrl%22%3A%22http%3A//streaming.ulcc.ac.uk/progress.php/'.$account.'/'.$owner.'/'.$filename.'.mov%22%7D%2C%22url%22%3A%22http%3A//streaming.ulcc.ac.uk/flowplayer/flowplayer.viralvideos-3.2.3.swf%22%7D%2C%22controls%22%3A%7B%22url%22%3A%22http%3A//streaming.ulcc.ac.uk/flowplayer/flowplayer.controls-3.2.3.swf%22%7D%2C%22content%22%3A%7B%22bottom%22%3A5%2C%22backgroundColor%22%3A%22transparent%22%2C%22border%22%3A0%2C%22textDecoration%22%3A%22outline%22%2C%22height%22%3A40%2C%22backgroundGradient%22%3A%22none%22%2C%22url%22%3A%22http%3A//streaming.ulcc.ac.uk/flowplayer/flowplayer.content-3.2.0.swf%22%2C%22style%22%3A%7B%22body%22%3A%7B%22fontSize%22%3A14%2C%22fontFamily%22%3A%22Arial%22%2C%22textAlign%22%3A%22center%22%2C%22color%22%3A%22%23ffffff%22%7D%7D%7D%2C%22captions%22%3A%7B%22url%22%3A%22http%3A//streaming.ulcc.ac.uk/flowplayer/flowplayer.captions-3.2.2.swf%22%2C%22captionTarget%22%3A%22content%22%7D%7D%7D" bgcolor="#000000" quality="true">
                        </embed>
                    </object>';


        return $result;

    }

    public static function single_only() {
        return false;
    }

    /**
     * what does this do?
     * @param <type> $default
     * @return <type>
     */
    public static function artefactchooser_element($default=null) {
        //$extraselect = 'filetype IN (' . join(',', array_map('db_quote', self::get_allowed_mimetypes())) . ')';
        $extrajoin   = ' JOIN {artefact_media_episode} ON {artefact_media_episode}.artefact = a.id ';

        return array(
            'name' => 'artefactid',
            'type'  => 'artefactchooser',
            'title' => get_string('episodeblocktitle', 'blocktype.media/episode'),
            'defaultvalue' => $default,
            'blocktype' => 'episode',
            'limit' => 5,
            'selectone' => true,
            'artefacttypes' => array('episode'),
            'extrajoin' => $extrajoin,
            'template' => 'artefact:media:artefactchooser-element.tpl',
            'search'    => false
        );
    }

    public static function has_instance_config() {
        return true;
    }

//    private static function get_js_source() {
//        if (defined('BLOCKTYPE_EPISODE_JS_INCLUDED')) {
//            return '';
//        }
//        define('BLOCKTYPE_EPISODE_JS_INCLUDED', true);
//        return '<script src="'.get_config('wwwroot').'lib/flowplayer/flowplayer-3.2.4.js"></script>
//             <script src="' . get_config('wwwroot') . 'artefact/file/blocktype/internalmedia/swfobject.js" type="text/javascript"></script>
//             <script defer="defer" src="' . get_config('wwwroot') . 'artefact/file/blocktype/internalmedia/eolas_fix.js" type="text/javascript"></script>';
//    }

    /**
     * generates the form that is used to choose an episode from those previously uploaded
     */
//    public static function instance_config_form($instance) {
//        $configdata = $instance->get('configdata');
//        safe_require('artefact', 'media');
//        $instance->set('artefactplugin', 'media');
//        return array(
//            'artefactid' => self::filebrowser_element($instance, (isset($configdata['artefactid'])) ? array($configdata['artefactid']) : null),
//            'width' => array(
//                'type' => 'text',
//                'title' => get_string('width'),
//                'size' => 3,
//                'defaultvalue' => (isset($configdata['width'])) ? $configdata['width'] : '',
//            ),
//            'height' => array(
//                'type' => 'text',
//                'title' => get_string('height'),
//                'size' => 3,
//                'defaultvalue' => (isset($configdata['height'])) ? $configdata['height'] : '',
//            ),
//        );
//    }

    /**
     * Returns the pieforms definition for the form that pops up when you add a new block to a view.
     * It lets you choose which of the available artefacts you want using simple radio buttons
     * @param <type> $instance
     * @return <type>
     */
     public static function instance_config_form($instance) {
         $configdata = $instance->get('configdata');
         $instance->set('artefactplugin', 'media');
         return array(
             self::artefactchooser_element((isset($configdata['artefactid']))
                 ? $configdata['artefactid'] : null)
//             'width' => array(
//                 'type' => 'text',
//                 'title' => get_string('width'),
//                 'size' => 3,
//                 'defaultvalue' => (isset($configdata['width'])) ? $configdata['width'] : '',
//             ),
//             'height' => array(
//                 'type' => 'text',
//                 'title' => get_string('height'),
//                 'size' => 3,
//                 'defaultvalue' => (isset($configdata['height'])) ? $configdata['height'] : '',
//             ),
         );
     }

    /**
     * Gets hold of a filebrowser instance and sets some parameters
     *
     * @param <type> $instance
     * @param <type> $default
     * @return array
     */
    public static function filebrowser_element(&$instance, $default=array()) {
        $element = self::blockconfig_filebrowser_element($instance, $default);
        $element['title'] = get_string('episodeblocktitle', 'blocktype.media/episode');
        $element['name'] = 'artefactid';
        $element['config']['selectone'] = true;
        $element['filters'] = array(
            'artefacttype'    => array('episode'),
        );
        return $element;
    }

      /**
     * Provides configuration for a filebrowser element that the config form for a
     * blockinstance uses.
     *
     * @param <type> $instance
     * @param <type> $default
     * @return <type>
     */
    public static function blockconfig_filebrowser_element(&$instance, $default=array()) {
        return array(
            'name'         => 'filebrowser',
            'type'         => 'filebrowser',
            'title'        => get_string('mymedia', 'artefact.media'),
            'highlight'    => null,
            'browse'       => true,
            'page'         => '/view/blocks.php' . View::make_base_url(),
            'config'       => array(
                'upload'          => false,
                'uploadagreement' => get_config_plugin('artefact', 'media', 'uploadagreement'),
                'createfolder'    => false,
                'edit'            => false,
                'tag'             => true,
                'select'          => true,
                'alwaysopen'      => true,
                'publishing'      => true,
            ),
            'defaultvalue' => $default,
            'selectlistcallback' => 'artefact_get_records_by_id',
        );
    }

}

?>
