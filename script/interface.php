<?php
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1); // Disables token renewal
require ('../config.php');
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
dol_include_once('/onlineaccount/class/onlineaccount.class.php');

$action = GETPOST('action','alpha');

switch($action) {
    case 'generate_token':
        print json_encode(_generate_token($db, GETPOST('fk_user')));
        break;
}

function _generate_token(&$db, $fk_user) {
    $dol_user = TOnlineAccount::generateToken(new User($db), $fk_user);

    $dt = new DateTime('@'.$dol_user->array_options['options_date_token']);

    return array(
        'token' => $dol_user->array_options['options_token'],
        'date_token' => $dt->format('d/m/Y'),
        'date_tokenday' => $dt->format('d'),
        'date_tokenmonth' => $dt->format('m'),
        'date_tokenyear' => $dt->format('Y')
    );
}


