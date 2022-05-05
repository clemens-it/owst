{strip}
{if !empty($errormsg)}
	<div class="errormsg">
		<strong>Error:</strong><br>
		{$errormsg|nl2br}
	</div>
{/if}

{if !empty($msg)}
	<div class="msg">
		{$msg|nl2br}
	</div>
{/if}
{/strip}
