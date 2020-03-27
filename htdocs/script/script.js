function onoff(element) {
	/* switches buttons on/off - day selection */
	buttonstate=element.value;
	buttonstate= 1 - buttonstate;
	var dclass, hf;
	if (buttonstate) {
		dclass="OOon";
	}
	else {
		dclass="OOoff";
	}
	element.className=dclass;
	element.value=buttonstate;
	hf=document.getElementById("day"+element.name);
	hf.value=buttonstate;
} //function onoff


function date_no_limit(element, inputfield) {
	//vars: input element, button element, forever value
	var inel, bel, fev;
	inel = document.getElementById(inputfield);
	bel  = document.getElementById('b'+inputfield);
	fev  = document.getElementById('cfg_forever_'+inputfield);
	if (element.checked) {
		inel.value = fev.value;
		inel.readOnly = true;
		bel.disabled = true;
	}
	else {
		inel.value = '';
		inel.readOnly = false;
		bel.disabled = false;
	}
} //function date_no_limit


function confirmLink(link, confirmMsg, alt_link) {
//link.... usual argument would be 'this'
//confirmMsg .... Message to be displayed
//alt_link ... alternative Link. Link to be set in case Cancel is clicked
	//var is_confirmed = confirm(confirmMsg + ' :\n' + theSqlQuery);
	var is_confirmed = confirm(confirmMsg);

	if (!is_confirmed)
		link.href = alt_link;

	return is_confirmed;
} //function confirmLink


function toggleDisplay(eID) {
	var ele;
	ele = document.getElementById(eID);
	if (ele.style.display == 'none')
		ele.style.display = 'block';
	else
		ele.style.display = 'none';
} //function showDiv
