{if $config.select}
{include file="artefact:media:form/selectedlist.tpl" selectedlist=$selectedlist prefix=$prefix highlight=$highlight}
{/if}

<script type="text/javascript">
{$initjs|safe}
</script>

<input type="hidden" name="folder" id="{$prefix}_folder" value="{$folder}" />
<input type="hidden" name="{$prefix}_changefolder" id="{$prefix}_changefolder" value="" />
<input type="hidden" name="{$prefix}_foldername" id="{$prefix}_foldername" value="{$foldername}" />

{if $config.select && !$browse}
<div id="{$prefix}_open_upload_browse_container">
<input type="submit" class="buttondk" id="{$prefix}_open_upload_browse" name="browse" value="{if $config.selectone}{str tag=selectafile section=artefact.file}{else}{str tag=addafile section=artefact.file}{/if}" />{if $config.browsehelp}{contextualhelp plugintype=$config.plugintype pluginname=$config.pluginname section=$config.browsehelp}{/if}
</div>
{/if}

<div id="{$prefix}_upload_browse" class="upload_browse{if $config.select} select{if !$browse} hidden{/if}{/if}">

{if $config.select && !$config.alwaysopen}
<input type="submit" class="buttondk" name="{$prefix}_cancelbrowse" id="{$prefix}_close_upload_browse" value="{str tag=Close}" />
{/if}



<table id="{$prefix}_upload_container" class="fileupload{if $tabs && !$tabs.upload} hidden{/if}">
 <tbody>



{if $config.upload}
  <input type="hidden" name="{$prefix}_uploadnumber" id="{$prefix}_uploadnumber" value="1" />
  <tr><td colspan=2 id="{$prefix}_upload_messages"></td></tr>


  <tr>
    <th><label>{str tag='episodetitle' section='artefact.media'}</label></th>
    <td>
      <div id="{$prefix}_episodetitle_container"><input type="text" class="" id="{$prefix}_episodetitle" name="{$prefix}_episodetitle" size="53" /></div>
    </td>
  </tr>
  <tr>
    <th><label>{str tag='episodedescription' section='artefact.media'}</label></th>
    <td>
      <div id="{$prefix}_episodedescription_container"><textarea class="" id="{$prefix}_episodedescription" name="{$prefix}_episodedescription" rows="3"  cols="40"></textarea></div>
    </td>
  </tr>



  {if $config.uploadagreement}
  <tr id="{$prefix}_agreement" class="uploadform">
    <th><!--<label>{str tag='' section='artefact.media'}</label>--></th>
    <td colspan=2>
      <input type="checkbox" name="{$prefix}_notice" id="{$prefix}_notice" />
      {$agreementtext|clean_html|safe}
    </td>
  </tr>
  <tr class="uploadform">
    <th><label>{str tag='uploadfile' section='artefact.media'}</label></th>
    <td>
      <div id="{$prefix}_userfile_container"><input type="file" class="file" id="{$prefix}_userfile" name="userfile" size="40" /> </div>
      <noscript><input type="submit" class="submit" name="{$prefix}_upload" id="{$prefix}_upload" value="{str tag=upload section=artefact.media}" /></noscript>
      <script>setNodeAttribute('{$prefix}_userfile', 'disabled', true);</script>
    </td>
  </tr>
  {else}
  <tr>
    <th><label>{str tag='uploadfile' section='artefact.media'}</label></th>
    <td>
      <div id="{$prefix}_userfile_container"><input type="file" class="file" id="{$prefix}_userfile" name="userfile" size="40" /> </div>
      <noscript><input type="submit" class="submit" name="{$prefix}_upload" id="{$prefix}_upload" value="{str tag=upload section=artefact.media}" /></noscript>
    </td>
  </tr>
  {/if}
{/if}


 </tbody>
</table>

{if $config.edit}
<input type="hidden" name="{$prefix}_move" id="{$prefix}_move" value="" />
<input type="hidden" name="{$prefix}_moveto" id="{$prefix}_moveto" value="" />
{/if}



<div id="{$prefix}_filelist_container">
{include file="artefact:media:form/filelist.tpl" prefix=$prefix filelist=$filelist editable=$config.edit selectable=$config.select highlight=$highlight edit=$edit querybase=$querybase groupinfo=$groupinfo owner=$tabs.owner ownerid=$tabs.ownerid selectfolders=$config.selectfolders showtags=$config.showtags editmeta=$config.editmeta}
</div>

{* Edit form used when js is available *}
{if $edit <= 0}
<table class="hidden">
  <tbody id="{$prefix}_edit_placeholder">
  {include file="artefact:media:form/editfile.tpl" prefix=$prefix groupinfo=$groupinfo}
  </tbody>
</table>
{/if}

{if $tabs}
</div>
{/if}

</div>