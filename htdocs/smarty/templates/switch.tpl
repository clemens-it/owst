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

<div class="switchlist">
	<div class="switch" style="background-color:black; margin-top:1em;">
		<div style="float:left; width:70%">Available Switches</div>
		<div style="width:14%; float:left;">Mode</div>
		<div style="width:14%; float:left;">Status</div>
		<br style="clear:left;"/>
	</div>
	{foreach $data as $v}
		<a href="{$scriptname}?action=timeprogram&subaction=list&sid={$v.id}">
		<div class="switch">
			<div style="float:left; width:70%">{$v.name} - Mode: {$v.mode}</div>
			<div style="width:14%; float:left;">
				{if $v.mode=='timer'}
					<img src="img/clock.png" width="24" height="24" alt="Mode: Timer" title="Mode: Timer" />
				{elseif $v.mode=='off'}
					<img src="img/ledred.png" width="24" height="24" alt="Mode: Constant Off" title="Mode: Constant Off" />
				{elseif $v.mode=='on'}
					<img src="img/ledgreen.png" width="24" height="24" alt="Mode: Constant On" title="Mode: Constant On" />
				{/if}
			</div>
			<div style="width:14%; float:left;">
				{if $v.status==1}
					<img src="img/ledgreen.png" width="24" height="24" alt="Current status: On" title="Current status: On" />
				{elseif $v.status==0}
					<img src="img/ledred.png" width="24" height="24" alt="Current status: Off" title="Current status: Off" />
				{/if}
			</div>
			<br style="clear:left;"/>
		</div>{*class=switch*}</a>
	{/foreach}
</div>

{/strip}
