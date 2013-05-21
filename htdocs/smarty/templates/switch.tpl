{strip}
<div class="owtitle">
One Wire Switch Timer Control
</div>

<div class="buttonbar" style="width:333px; margin-top:1em;">
	<a href="{$scriptname}?action=log&subaction=show">
		<div class="divbutton" style="width:7em; float:left; margin:0px;">Log</div></a>
	<a href="{$scriptname}?action=at&subaction=showqueue">
		<div class="divbutton" style="width:7em; float:right; margin:0px;">AT Queue</div></a>
	<br style="clear:both;" />
</div> {* buttonbar *}

<div style="margin-top:1em;">
	Available Switches
</div>
<div class="switchlist">
	{foreach $data as $v}
		<a href="{$scriptname}?action=timeprogram&subaction=list&sid={$v.id}">
		<div class="switch">
			<div style="float:left; width:80%">{$v.name} - Mode: {$v.mode}</div>
			<div style="width:15%; float:left;">
				{if $v.mode=='timer'}
					<img src="img/clock.png" width="24" height="24" alt="Timer" title="Timer" />
				{elseif $v.mode=='off'}
					<img src="img/ledred.png" width="24" height="24" alt="Off" title="Off" />
				{elseif $v.mode=='on'}
					<img src="img/ledgreen.png" width="24" height="24" alt="On" title="On" />
				{/if}
			</div>
			<br style="clear:left;"/>
		</div></a>
	{/foreach}
</div>

{/strip}
