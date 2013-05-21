{strip}
<div class="owtitle">
	Log
</div>
{include file="messages.tpl"}

{*<div style="height:38px;"></div> {* must have same height as buttonbar *}
<div class="buttonbar" style="width:352px;">
	<form name="logfilter" action="{$scriptname}" method="get" style="float:left;">
		{html_options name="filter_loglevel" options=$log_levels selected=$filter_loglevel
			onChange="document.logfilter.submit();"}
		<input type="hidden" name="action" value="log" />
		<input type="hidden" name="subaction" value="show" />
		<input type="submit" name="bsubmit" value="Filter" /> {* button must not be named 'submit' in order for the onChange javascript code of the select element to be working *}
	</form>
	<a href="{$scriptname}">
		<div class="divbutton" style="width:7em; float:right; margin:0px;">Back</div></a>
	<br style="clear:both;" />
</div>
{/strip}
<pre>{foreach $logdata as $v}{$v}
{/foreach}</pre>
