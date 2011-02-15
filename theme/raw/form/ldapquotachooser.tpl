
        <table>
            <tr>
                <td style="padding-left:0">
                  <div id="{$prefix}quota">
                    <select name="quota" id="quota">
                        {foreach $quotaoptions key=key item=quotaoption}
                            <option value="{$key}" {if $quotaoption==$defaultvalue}selected="selected"{/if}>{$quotaoption}</option>
                        {/foreach}
                    </select>
                  </div>
                </td>
                <td >
                  <div>
                    <input type="submit" class="submit" name="save" value="Add" />
                  </div>
               </td>
            </tr>
        </table>
