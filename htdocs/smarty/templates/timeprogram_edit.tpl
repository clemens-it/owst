{strip}
{if $form_mode == 'edit'}
Time Program '{$data.name}' on Switch <a href="{$scriptname}?action=timeprogram&subaction=list&sid={$data.sid}">'{$data.sname}'</a>
{else}
New Time Program
{/if}
{include file='messages.tpl'}
<form name="timeprogram" action="{$scriptname}" method="post">

	{*<div style="height:162px;"><br><br><br>.</div> {* must have same height as buttonbar *}
	<div class="buttonbar">
		<input type="hidden" name="action" value="timeprogram">
		{if $form_mode == "edit"}
			<input type="hidden" name="subaction" value="update">
			<input type="hidden" name="tp[id]" value="{$data.id}">
			<input type="hidden" name="tp[switch_id]" value="{$data.switch_id}">
		{else}
			<input type="hidden" name="subaction" value="insert">
			{* on insert there's no array data defined, sid is in a separate variable *}
			<input type="hidden" name="tp[switch_id]" value="{$sid}">
		{/if}
		<input type="submit" name="submit" value="{if $form_mode == "edit"}Save changes{else}Insert{/if}" style="width:8em;">
		<a href="{$scriptname}?action=timeprogram&subaction=list&sid={$sid}">
			<div class="divbutton" style="width:7em; float:right; margin:0px;">Back</div></a>
		{if $form_mode == "edit"}
			<br style="clear:both;"><br>
			<a href="{$scriptname}?action=timeprogram&subaction=delete&tpid={$data.tpid}&sid={$data.sid}" onclick="confirmLink(this,'Are you sure you want to delete this time program?','{$scriptname}?action=timeprogram&subaction=edit&tpid={$data.tpid}')">
				<div class="divbutton" style="width:7em; float:left; margin:0px;">Delete</div></a>
			<a href="{$scriptname}?action=timeprogram&subaction=clone&tpid={$data.tpid}">
				<div class="divbutton" style="width:7em; float:left; margin:0px 5px;">Clone</div></a>
			<a href="#intr" onclick="toggleDisplay('intrdiv');">
				<div class="divbutton" style="width:7em; float:right; margin:0px;">Interrupt</div></a>
			<br style="clear:both;">
		{/if}
	</div> {* buttonbar *}

	<div class="timeprogramdetail">
		Name:<br>
		<input type="text" name="tp[name]" value="{$data.name}">

		<hr>
		Time:<br>
		<input type="text" name="tp[switch_on_time]" value="{$data.switch_on_time}" maxlength="5" size="5"> -&nbsp;
		<input type="text" name="tp[switch_off_time]" value="{$data.switch_off_time}" maxlength="5" size="5">

		<hr>
		Valid from until:<br>
		<div style="float:left">
			<input type="text" id="valid_from" name="tp[valid_from]" value="{$data.valid_from}" maxlength="10" size="10"
				{if $data.forever_valid_from} readonly="readonly"{/if}>
			<button type="button" id="bvalid_from">...</button><br>
			<input type="checkbox" name="tp[forever_valid_from]" id="forever_valid_from"
				{if $data.forever_valid_from} checked="checked"{/if} onclick="date_no_limit(this,'valid_from');">
				<label for="forever_valid_from">no limitation</label>
		</div>
		<div style="float:left">
			&nbsp;-&nbsp;
		</div>
		<div>
			<input type="text" id="valid_until" name="tp[valid_until]" value="{$data.valid_until}" maxlength="10"
				size="10" {if $data.forever_valid_until}readonly="readonly"{/if}>
			<button type="button" id="bvalid_until">...</button><br>
			<input type="checkbox" name="tp[forever_valid_until]" id="forever_valid_until"
				{if $data.forever_valid_until} checked="checked"{/if} onclick="date_no_limit(this,'valid_until');">
				<label for="forever_valid_until">no limitation</label>
		</div>

		<hr style="clear:both">
		Days:<br>
		<button type="button" name="1" class="{if $data.d1}OOon{else}OOoff{/if}" onclick="onoff(this);" value="{$data.d1}">
			Mo</button>&nbsp;
		<button type="button" name="2" class="{if $data.d2}OOon{else}OOoff{/if}" onclick="onoff(this);" value="{$data.d2}">
			<div>Tu</div></button>&nbsp;
		<button type="button" name="3" class="{if $data.d3}OOon{else}OOoff{/if}" onclick="onoff(this);" value="{$data.d3}">
			<div>We</div></button>&nbsp;
		<button type="button" name="4" class="{if $data.d4}OOon{else}OOoff{/if}" onclick="onoff(this);" value="{$data.d4}">
			<div>Th</div></button>&nbsp;
		<button type="button" name="5" class="{if $data.d5}OOon{else}OOoff{/if}" onclick="onoff(this);" value="{$data.d5}">
			<div>Fr</div></button>&nbsp;
		<button type="button" name="6" class="{if $data.d6}OOon{else}OOoff{/if}" onclick="onoff(this);" value="{$data.d6}">
			<div>Sa</div></button>&nbsp;
		<button type="button" name="0" class="{if $data.d0}OOon{else}OOoff{/if}" onclick="onoff(this);" value="{$data.d0}">
			<div>Su</div></button>
		{* since buttons' values are not submitted, javascript code updates the following hidden fields *}
		<input type="hidden" name="tp[d0]" id="day0" value="{$data.d0}">
		<input type="hidden" name="tp[d1]" id="day1" value="{$data.d1}">
		<input type="hidden" name="tp[d2]" id="day2" value="{$data.d2}">
		<input type="hidden" name="tp[d3]" id="day3" value="{$data.d3}">
		<input type="hidden" name="tp[d4]" id="day4" value="{$data.d4}">
		<input type="hidden" name="tp[d5]" id="day5" value="{$data.d5}">
		<input type="hidden" name="tp[d6]" id="day6" value="{$data.d6}">

		<hr>
		<input type="checkbox" id="delete_after_becoming_invalid" name="tp[delete_after_becoming_invalid]"
			{if $data.delete_after_becoming_invalid} checked="checked"{/if}> <label for="delete_after_becoming_invalid">Delete after becoming invalid</label>

		<hr>
		<input type="checkbox" id="override_other_programs_when_turning_off" name="tp[override_other_programs_when_turning_off]"
			{if $data.override_other_programs_when_turning_off} checked="checked"{/if}> <label for="override_other_programs_when_turning_off">Override others when turning off</label>

		<hr>
		Switch off priority:<br>
		{html_options name="tp[switch_off_priority]" options=$so_priorities selected=$data.switch_off_priority}

		{if $form_mode == 'edit'}
			<hr>
			Current status:
			{if $data.active}
				<img src="img/ledgreen.png" width="20" height="20" alt="On" title="On"> active
			{else}
				<img src="img/ledred.png" width="20" height="20" alt="Off" title="Off"> not active
			{/if}<br>
			{if $data.active}Runtime: {$data.runtime}{/if}

			<hr>
			Last switch on: {$data.time_switched_on_f}
		{/if}
	</div>

</form>
<input type="hidden" id="cfg_forever_valid_from" value="{$cfg_forever_valid_from}">
<input type="hidden" id="cfg_forever_valid_until" value="{$cfg_forever_valid_until}">

	<div id="intrdiv" class="buttonbar" style="display:none; margin-top:5px; padding-bottom:9px;">
		<a name="intr"></a>
		<form action="{$scriptname}">
			Interrupt time program:<br>
			&nbsp; execute until - and restart on:<br>
			<input type="text" id="intr_from" name="intr_from" value="" maxlength="10" size="8">
			<button type="button" id="bintr_from">...</button> -&nbsp;
			<input type="text" id="intr_until" name="intr_until" value="" maxlength="10" size="8">
			<button type="button" id="bintr_until">...</button>&nbsp;
			<input type="submit" name="submit" value="Interrupt">
			<input type="hidden" name="tpid" value="{$data.tpid}">
			<input type="hidden" name="sid" value="{$data.switch_id}">
			<input type="hidden" name="action" value="timeprogram">
			<input type="hidden" name="subaction" value="interrupt">
		</form>
	</div>
{/strip}
{literal}
<script type="text/javascript">//<![CDATA[
	Calendar.setup({
		inputField : "valid_from",
		trigger    : "bvalid_from",
		animation  : false,
		dateFormat : "%Y-%m-%d",
		showTime   : false,
		onSelect   : function() { this.hide() }
	});
	Calendar.setup({
		inputField : "valid_until",
		trigger    : "bvalid_until",
		animation  : false,
		dateFormat : "%Y-%m-%d",
		showTime   : false,
		onSelect   : function() { this.hide() }
	});
	Calendar.setup({
		inputField : "intr_from",
		trigger    : "bintr_from",
		animation  : false,
		dateFormat : "%Y-%m-%d",
		showTime   : false,
		onSelect   : function() { this.hide() }
	});
	Calendar.setup({
		inputField : "intr_until",
		trigger    : "bintr_until",
		animation  : false,
		dateFormat : "%Y-%m-%d",
		showTime   : false,
		onSelect   : function() { this.hide() }
	});
//]]></script>
{/literal}
