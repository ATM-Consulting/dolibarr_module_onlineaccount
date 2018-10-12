<?php

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

    return array('token' => $dol_user->array_options['options_token']
                ,'date_token' => date('d/m/Y', $dol_user->array_options['options_date_token']));
}


