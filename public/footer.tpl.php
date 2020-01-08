<?php



// Show mesgs
if (isset($_SESSION['dol_events']['mesgs'])) {
	if (empty($disabledoutputofmessages)) onlineaccount_htmloutput_mesg($_SESSION['dol_events']['mesgs']);
	unset($_SESSION['dol_events']['mesgs']);
}

// Show errors
if (isset($_SESSION['dol_events']['errors'])) {
	if (empty($disabledoutputofmessages)) onlineaccount_htmloutput_mesg($_SESSION['dol_events']['errors'], 'error');
	unset($_SESSION['dol_events']['errors']);
}

// Show warnings
if (isset($_SESSION['dol_events']['warnings'])) {
	if (empty($disabledoutputofmessages)) onlineaccount_htmloutput_mesg($_SESSION['dol_events']['warnings'], 'warning');
	unset($_SESSION['dol_events']['warnings']);
}


function onlineaccount_htmloutput_mesg($eventMessages, $type = 'success')
{
	print '<script type="text/javascript">'."\n"; // 'mesgs', 'warnings', 'errors'

	$timeout = 3000;
	$type = 'success';

	if($type == 'warnings'){
		$timeout = 5000;
		$type = 'warnings';
	}

	if($type == 'errors') {
		$timeout = 7000;
		$type = 'errors';
	}

	if(!empty($eventMessages)){
		foreach ($eventMessages as $mesg)
		{
			print 'new Noty({
		    timeout: '.$timeout.',
		    type: "'.$type.'",
		    closeWith: [\'button\',\'click\'],
            theme: "metroui",
            text: "'.addslashes(preg_replace("/\r|\n/", "", nl2br($mesg))).'"
        }).show();';
		}
	}
	print '</script>'."\n";
}
?>
	</body>
</html>
