<div class="mediaquota">
    <h3><?php echo get_string('mediaquota', 'artefact.media'); ?></h3>
    <div class="sidebar-content">
        <p id="mediaquota_message">
            {$sbdata.quotamessage|safe}
            {contextualhelp plugintype='artefact' pluginname='media' section='mediaquota_message'}
        </p>
        <div id="quotawrap">
        {if $sbdata.quotapercentage < 100}
            <div id="mediaquota_fill" style="width: {$sbdata.quotapercentage*2}px;">&nbsp;</div>
            <p id="mediaquota_bar">
                <span id="mediaquota_percentage">{$sbdata.quotapercentage}%</span>
            </p>
        {else}
            <div id="mediaquota_fill" style="display: none; width: {$sbdata.quotapercentage*2}px;">&nbsp;</div>
            <p id="mediaquota_bar_100">
                <span id="mediaquota_percentage">{$sbdata.quotapercentage}%</span>
            </p>
        {/if}
        </div>
        <div id="mediaquotasource">
            <p>{str tag=quotasource section=artefact.media} {$sbdata.quotasource}</p>
        </div>
    </div>
</div>