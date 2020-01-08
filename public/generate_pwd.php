<?php

if(! defined('NOLOGIN')) define('NOLOGIN', 1);

require '../config.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/contact.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
dol_include_once('/contact/class/contact.class.php');
dol_include_once('/onlineaccount/lib/onlineaccount.lib.php');

$langs->load('onlineaccount@onlineaccount');

$token = GETPOST('token', 'aZ09');
if(empty($token)) exit;
$action = GETPOST('action', 'alpha', 2);    // Only POST method

$soc = new Societe($db);
$dolibarr_user = new User($db);
$extrafields = new ExtraFields($db);
$pwd = GETPOST('pwd');

$conf->dol_hide_leftmenu=1;
$conf->dol_hide_topmenu=1;
$conf->dol_optimize_smallscreen=1;
$conf->dol_no_mouse_hover=1;
$conf->dol_use_jmobile=1;

if(empty($action)) {
    $sql = 'SELECT fk_object as fk_user';
    $sql .= ' FROM '.MAIN_DB_PREFIX.'user_extrafields';
    $sql .= " WHERE token = '".$db->escape($token)."'";
    $sql .= ' AND date_token >= DATE_FORMAT(NOW(), \'%Y-%m-%d\')';

    $resql = $db->query($sql);
    if($resql) {
        if($obj = $db->fetch_object($resql)) $id = $obj->fk_user;
    }
    $db->free($resql);

    if(empty($id)) {
        print $langs->trans('InvalidToken');
        exit;
    }
}
else $id = GETPOST('id', 'int', 2);

// fetch optionals attributes and labels
$extralabels=$extrafields->fetch_name_optionals_label($dolibarr_user->table_element);

if(! empty($id)) $dolibarr_user->fetch($id);


/*
 * Actions
 */
$error = 0;
$errorMsg = '';
switch ($action) {
    case 'update_pwd':
        if(empty($pwd)) $errorMsg = $langs->trans('EmptyPassword');
        else if($pwd != GETPOST('pwd_confirm')) $errorMsg = $langs->trans('WrongPasswordConfirm');

        $db->begin();

        $ret = $dolibarr_user->setPassword($user, GETPOST('pwd'));
        if ($ret < 0) {
            $error++;
            setEventMessages($dolibarr_user->error, $dolibarr_user->errors, 'errors');
        }

        if(empty($errorMsg)) {
            setEventMessages($langs->trans("PasswordModified"), null, 'mesgs');
            $dolibarr_user->setValueFrom('datelastlogin', dol_now(), '', '', 'date');
            $db->commit();
        }
        else {
            $db->rollback();
            setEventMessage($errorMsg, 'errors');
            header('Location: '.$_SERVER['PHP_SELF'].'?token='.$token);
            exit;
        }
        break;
}


/**
 * View
 */
$form = new Form($db);

$title=$langs->trans("GeneratePassword");
include __DIR__ . '/header.tpl.php';

?>
<div class="container">

<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
<input type="hidden" name="action" value="update_pwd" />
<input type="hidden" name="id" value="<?php echo $dolibarr_user->id ?>" />
<input type="hidden" name="token" value="<?php echo $token ?>" />

    <div id="conteneur">
        <div class="title"><h2><?php if (empty($dolibarr_user->datelastlogin)) echo $langs->trans('GeneratePasswordTitleCreate'); else echo $langs->trans('GeneratePasswordTitle'); ?></h2></div>

        <div><?php echo $langs->trans('NewPassword'); ?></div>
        <div><input type="password" name="pwd" /></div>

        <div><?php echo $langs->trans('ConfirmNewPassword'); ?></div>
        <div><input type="password" name="pwd_confirm" /></div>

        <div>
            <input class="butAction" type="submit" value="<?php echo $langs->trans('Save'); ?>" />
            <?php
                if (!empty($conf->global->ONLINEACCOUNT_BACK_TO_URL) && !empty($dolibarr_user->datelastlogin))
                {
                    $label = (!empty($conf->global->ONLINEACCOUNT_BACK_TO_URL_LABEL)) ? $conf->global->ONLINEACCOUNT_BACK_TO_URL_LABEL : $langs->trans('onlineaccount_backtourl_label');
                    print '<a href="'.$conf->global->ONLINEACCOUNT_BACK_TO_URL.'" class="butAction" >'.$label.'</a>';
                }
            ?>
        </div>
    </div>
</form>

</div></div>

<?php

include __DIR__ . '/footer.tpl.php';
