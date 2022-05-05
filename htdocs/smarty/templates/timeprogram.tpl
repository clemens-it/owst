{strip}
<div class="owtitle">
	One Wire Switch Timer Control
</div>
<div>
	{* <div style="height:81px;"></div> *} {* must have same height as buttonbar *}
	<div class="buttonbar" style="width:352px;">
		<a href="{$scriptname}?action=timeprogram&subaction=addnew&sid={$sid}">
			<div class="divbutton" style="width:7em; float:left; margin:0px;">Add new</div></a>
		<a href="{$scriptname}">
			<div class="divbutton" style="width:7em; float:right; margin:0px;">Back</div></a>
		<br style="clear:both;"><br>
		<form action="{$scriptname}">
			Current Mode: {html_options name="switch_mode" options=$sw_modes selected=$data[0].mode}
			<input type="submit" name="submit" value="Set mode">
			<input type="hidden" name="sid" value="{$sid}">
			<input type="hidden" name="action" value="switch">
			<input type="hidden" name="subaction" value="setmode">
		</form>
		<br style="clear:both;">
		Current Status:
			{if $sw_status==1}
				<img src="img/ledgreen.png" width="24" height="24" alt="Current status: On" title="Current status: On">
			{elseif $sw_status==0}
				<img src="img/ledred.png" width="24" height="24" alt="Current status: Off" title="Current status: Off">
			{/if}
		<br style="clear:both;"><br>
		<div style="line-height:1.9em">
		Immediate:<br>
		<form action="{$scriptname}">
			&bullet; {html_options name="immaction" options=$immediate_opt}
			<input type="text" name="immtime" value="00:30" maxlength="5" style="width:3em;"> (hh:mm | dec)&nbsp;
			<input type="hidden" name="sid" value="{$sid}">
			<input type="hidden" name="action" value="timeprogram">
			<input type="hidden" name="subaction" value="immediate">
			<input type="submit" name="submit" value="Go">
			<br>
		</form>
		<form action="{$scriptname}">
			&bullet; switch <input type="text" name="immstr" value="on in 00:30 for 02:15" maxlength="25" style="width:10em;"> &nbsp;
			<input type="submit" name="submit" value="Go">
			<input type="hidden" name="sid" value="{$sid}">
			<input type="hidden" name="action" value="timeprogram">
			<input type="hidden" name="subaction" value="immediate_str">
		</form>
		</div>
	</div>

	<div style="margin-top:1em;">
		{if $tp_count == 0}
			No time programs defined yet. Click &quot;Add New&quot; to insert a new time program.
		{else}
			Time Programs for Switch "{$data[0].sname}"
			</div>
			<div class="timeprogramlist">
			{foreach $data as $v}
				<a href="{$scriptname}?action=timeprogram&subaction=edit&tpid={$v.tpid}">
				<div class="timeprogram" style="float:left;">
					<div style="float:left; width:80%">
						<strong>{$v.name}</strong> <br>
						{$v.switch_on_time} - {$v.switch_off_time}<br>
						{if $v.forever_valid_from && $v.forever_valid_until}
							always
						{elseif !$v.forever_valid_from && !$v.forever_valid_until}
							{$v.valid_from} until {$v.valid_until}
						{else}
							{if $v.forever_valid_from}
								until {$v.valid_until}
							{/if}
							{if $v.forever_valid_until}
								starting from {$v.valid_from}
							{/if}
						{/if}
						<br>
						<span class="{if $v.d1}dayselected{else}daynotselected{/if}">Mo</span>&nbsp;
						<span class="{if $v.d2}dayselected{else}daynotselected{/if}">Tu</span>&nbsp;
						<span class="{if $v.d3}dayselected{else}daynotselected{/if}">We</span>&nbsp;
						<span class="{if $v.d4}dayselected{else}daynotselected{/if}">Th</span>&nbsp;
						<span class="{if $v.d5}dayselected{else}daynotselected{/if}">Fr</span>&nbsp;
						<span class="{if $v.d6}dayselected{else}daynotselected{/if}">Sa</span>&nbsp;
						<span class="{if $v.d0}dayselected{else}daynotselected{/if}">Su</span>&nbsp;
					</div>
					<div style="width:15%; float:left; padding-top:2em;">
						{if $v.active}
							<img src="img/ledgreen.png" width="20" height="20" alt="On" title="On">
						{else}
							<img src="img/ledred.png" width="20" height="20" alt="Off" title="Off">
						{/if}
					</div>
				</div></a>
				<br style="clear:both;">
			{/foreach}{* $data as $v *}
		{/if}{* $tp_count == 0 *}
	</div>
</div>
{/strip}
