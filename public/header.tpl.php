<?php

$publicUrl = dol_buildpath('onlineaccount/public/', 1);

?>
<!doctype html>
<html>
	<head>
		<meta charset="utf-8">
		<title><?php echo empty($title)?$title:''; ?> - <?php echo !empty($conf->global->MAIN_INFO_SOCIETE_NOM)?$conf->global->MAIN_INFO_SOCIETE_NOM:''; ?></title>
		<link rel="stylesheet" href="<?php print $publicUrl; ?>/css/bootstrap.css">
		<script src="<?php print $publicUrl; ?>js/jquery-3.4.1.min.js"></script>
		<script src="<?php print $publicUrl; ?>js/bootstrap.bundle.min.js"></script>


		<link rel="stylesheet" href="<?php print $publicUrl; ?>css/style.css">

		<!-- Fontawesome -->
		<link rel="stylesheet" href="<?php print $publicUrl; ?>vendor/fontawesome/css/all.min.css">

		<!-- Plugin Select -->
		<script src="<?php print $publicUrl; ?>vendor/bootstrap-select/dist/js/bootstrap-select.min.js"></script>
		<link rel="stylesheet" type="text/css" href="<?php print $publicUrl; ?>vendor/bootstrap-select/dist/css/bootstrap-select.min.css"/>

		<!-- Plugin Notify -->
		<script src="<?php print $publicUrl; ?>vendor/noty/noty.min.js"></script>
		<link rel="stylesheet" type="text/css" href="<?php print $publicUrl; ?>vendor/noty/noty.css"/>
		<link rel="stylesheet" type="text/css" href="<?php print $publicUrl; ?>vendor/noty/themes/metroui.css"/>

	</head>
	<body>
