{if !$existingquotas}

<tr>
  <th></th>
  <td><p>{str tag=noquotasyet section=artefact.media}</p></td>
</tr>

{else}
<table id="{$prefix}quotaslist" class="tablerenderer filelist" style="width:400px;">

 <thead> 
  <tr>
   <th style="text-align:left;">{str tag=ldapou section=artefact.media}</th>
   <th style="text-align:left;">{str tag=quota}</th>
   <th></th>
  </tr>
</thead>

 <tbody> 


  {foreach from=$existingquotas item=quota}
    
  <tr id="quota:{$file->id}" class="{cycle values='r0,r1'}">

    <td class="">
      {$quota->ldapou}
    </td>
    <td>{$quota->quota|escape}</td>
    

    <td>
     <input type="submit" class="submit btn-del s" name="quotaslist[{$quota->id}]" value="{str tag=delete}" />
    </td>


  </tr>

  {/foreach}
 </tbody>
</table> 
{/if}
