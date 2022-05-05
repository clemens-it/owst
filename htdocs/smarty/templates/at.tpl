{strip}
<div class="owtitle">
	AT Queue
</div>
{include file="messages.tpl"}

{* <div style="height:38px;"></div> *} {* must have same height as buttonbar *}
<div class="buttonbar" style="width:352px; margin-bottom:1px;">
	<a href="{$scriptname}">
		<div class="divbutton" style="width:7em; float:right; margin:0px;">Back</div></a>
	<br style="clear:both;">
</div>

{/strip}
<pre>{foreach $data as $v}{$v}
{/foreach}</pre>
