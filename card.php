<?php

require 'config.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/contact.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
dol_include_once('/onlineaccount/class/onlineaccount.class.php');
dol_include_once('/contact/class/contact.class.php');
dol_include_once('/onlineaccount/lib/onlineaccount.lib.php');

//if(empty($user->rights->onlineaccount->read)) accessforbidden();

$langs->load('onlineaccount@onlineaccount');

$action = GETPOST('action');
$id = GETPOST('id', 'int');
$ref = GETPOST('ref');

$mode = 'view';
if (empty($user->rights->onlineaccount->write)) $mode = 'view'; // Force 'view' mode if can't edit object
else if ($action == 'create' || $action == 'edit') $mode = 'edit';

$object = new Contact($db);
$soc = new Societe($db);
$dolibarr_user=new User($db);

if (!empty($id)) $object->fetch($id);

$hookmanager->initHooks(array('onlineaccountcard', 'globalcard'));

/*
 * Actions
 */
$parameters = array('id' => $id, 'ref' => $ref, 'mode' => $mode);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

// Si vide alors le comportement n'est pas remplacé
if (empty($reshook))
{
	$error = 0;
	switch ($action) {
		case 'create_user':
			$login = dol_buildlogin($object->lastname, $object->firstname);
            $pwd = getRandomPassword(false);

            $dolibarr_user->create_from_contact($object, $login);
            $dolibarr_user->setPassword($user, $pwd, 0, 0, 1);

            header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id.'&action=edit');
            exit;
            break;
	}
}


/**
 * View
 */
$title=$langs->trans("onlineaccount");
llxHeader('',$title);

$head = contact_prepare_head($object);
$title = (! empty($conf->global->SOCIETE_ADDRESSES_MANAGEMENT) ? $langs->trans("Contacts") : $langs->trans("ContactsAddresses"));

dol_fiche_head($head, 'onlineaccount', $title, 0, 'generic');

$linkback = '<a href="'.DOL_URL_ROOT.'/contact/list.php">'.$langs->trans("BackToList").'</a>';

$morehtmlref='<div class="refidno">';
if (empty($conf->global->SOCIETE_DISABLE_CONTACTS))
{
    $soc->fetch($object->socid);

    // Thirdparty
    $morehtmlref .= $langs->trans('ThirdParty') . ' : ';
    if ($soc->id > 0) $morehtmlref .= $soc->getNomUrl(1);
    else $morehtmlref .= $langs->trans("ContactNotLinkedToCompany");
}
$morehtmlref.='</div>';

dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'ref', $morehtmlref);

print '<div class="fichecenter">';

if(! empty($object->user_id)) {
    $dolibarr_user->fetch($object->user_id);
    if(empty($dolibarr_user->array_options)) $dolibarr_user->fetch_optionals();

    print '<div class="underbanner clearboth"></div>';
    print '<table class="border tableforfield" width="100%">';

    // Actif
    print '<tr>';
    print '<td class="titlefield">'.$langs->trans("Enabled").'</td>';
    print '<td colspan="2">';
    print '<input type="checkbox" name="active" disabled="disabled" '.(! empty($dolibarr_user->statut) ? 'checked="checked"' : '').'/>';
    print '</td></tr>';

    // Login
    print '<tr>';
    print '<td class="titlefield">'.$langs->trans("Login").'</td><td width="300" colspan="2">';
    print $dolibarr_user->login;
    print '</td>';
    print '</tr>';

    // Pwd
    print '<tr><td>'.$langs->trans("Password").'</td><td width="300" colspan="2">';
    if ($dolibarr_user->pass) print preg_replace('/./i','*',$dolibarr_user->pass);
    else
    {
        if ($user->admin) print $langs->trans("Crypted").': '.$dolibarr_user->pass_indatabase_crypted;
        else print $langs->trans("Hidden");
    }
    print '</td></tr>';

    // Token
    print '<tr><td>'.$langs->trans("Token").'</td>';
    print '<td width="300">'.$dolibarr_user->array_options['options_token'].'</td><td>url</td></tr>';

    // Date Token
    print '<tr><td>'.$langs->trans("DateToken").'</td><td colspan="2">'.$dolibarr_user->array_options['options_date_token'].'</td></tr>';

    // Email
    print '<tr><td>'.$langs->trans("Email").'</td><td colspan="2">';
    print $dolibarr_user->email;
    print '</td></tr>';

    // Telephone
    print '<tr><td>'.$langs->trans("Phone").'</td><td colspan="2">';
    print dol_print_phone($dolibarr_user->user_mobile);
    print '</td></tr>';

    print '</table>';
}

print '</div></div>';
print '<div class="tabsAction">';

if(empty($object->user_id)) print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=create_user">'.$langs->trans('CreateUser').'</a>';
else {
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=genPwd">'.$langs->trans('GeneratePassword').'</a>';
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=edit">'.$langs->trans('Modify').'</a>';
}
    
print '</div>';

llxFooter();