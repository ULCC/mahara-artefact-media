    <tr title="{$artefact->hovertitle|escape}">
        <td style="width: 20px;">
            {$formcontrols|safe}
        </td>
        <td style="width: 22px;"><label for="{$elementname}_{$artefact->id}"><img src="{$artefact->icon|escape}" alt=""></label></td>
        <th><label for="{$elementname}_{$artefact->id}">{$artefact->title}</label></th>
        <td><label for="{$elementname}_{$artefact->id}">{$artefact->description|escape}</label></td>
        
    </tr>
