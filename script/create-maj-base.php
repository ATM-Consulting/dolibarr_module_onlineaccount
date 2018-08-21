<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 */

if(!defined('INC_FROM_DOLIBARR')) {
	define('INC_FROM_CRON_SCRIPT', true);

	require('../config.php');
} else {
	global $db;
}


/* uncomment


dol_include_once('/onlineaccount/class/onlineaccount.class.php');

$o=new onlineaccount($db);
$o->init_db_by_vars();
*/

dol_include_once('/onlineaccount/script/insert_model.php');