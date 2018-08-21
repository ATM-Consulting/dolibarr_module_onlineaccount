<?php

require ('../config.php');
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

$action = GETPOST('action','alpha');

switch($action) {
    case 'generate_token':
        print json_encode(_generate_token($db, GETPOST('fk_user')));
        break;
}

function _generate_token(&$db, $fk_user) {
    $dolibarr_user = new User($db);
    $dolibarr_user->fetch($fk_user);

    if(! empty($dolibarr_user->email)) {
        $dolibarr_user->array_options['options_token'] = hash('sha256', $dolibarr_user->email.time());
    }
    $dolibarr_user->insertExtraFields();
    return $dolibarr_user->array_options['options_token'];
}


