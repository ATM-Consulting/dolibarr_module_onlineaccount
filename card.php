<?php

require 'config.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/contact.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
dol_include_once('/contact/class/contact.class.php');
dol_include_once('/onlineaccount/lib/onlineaccount.lib.php');

$TArrayOfCss = array();

if((float) DOL_VERSION == 6.0) {
    $TArrayOfCss[] = '/theme/common/fontawesome/css/font-awesome.css';
}

$langs->load('onlineaccount@onlineaccount');
$langs->load('users');

$action = GETPOST('action');
$id = GETPOST('id', 'int');

if(empty($action)) $action = 'view';
//if (empty($user->rights->onlineaccount->write)) $mode = 'view'; // Force 'view' mode if can't edit object

$object = new Contact($db);
$soc = new Societe($db);
$dolibarr_user=new User($db);
$extrafields = new ExtraFields($db);

// fetch optionals attributes and labels
$extralabels=$extrafields->fetch_name_optionals_label($dolibarr_user->table_element);

if(! empty($id)) $object->fetch($id);
if(! empty($object->user_id)) $dolibarr_user->fetch($object->user_id);

$hookmanager->initHooks(array('onlineaccountcard', 'globalcard'));

/*
 * Actions
 */
$parameters = array('id' => $id);
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
        case 'save':
            $error = 0;
            $db->begin();

            $active = GETPOST('active');
            $dolibarr_user->statut = empty($active) ? 0 : 1;
            $dolibarr_user->login = GETPOST('login', 'alpha');
            $dolibarr_user->pass = GETPOST('password');
            $dolibarr_user->email = GETPOST('email', 'alpha');
            $dolibarr_user->user_mobile = GETPOST('phone');

            $date_token = dol_mktime(12, 0, 0, GETPOST('date_tokenmonth'), GETPOST('date_tokenday'), GETPOST('date_tokenyear'));
            $dolibarr_user->array_options['options_date_token'] = $date_token;

            $ret = $dolibarr_user->update($user);
            if ($ret < 0) {
                $error++;
                if ($db->errno() == 'DB_ERROR_RECORD_ALREADY_EXISTS') {
                    $langs->load("errors");
                    setEventMessages($langs->trans("ErrorLoginAlreadyExists", $object->login), null, 'errors');
                }
                else
                {
                    setEventMessages($object->error, $object->errors, 'errors');
                }
            }

            if(empty($error)) {
                setEventMessages($langs->trans("UserModified"), null, 'mesgs');
                $db->commit();
            }
            else $db->rollback();

            header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
            exit;
            break;
	}
}


/**
 * View
 */
$form = new Form($db);

$title=$langs->trans("onlineaccount");
llxHeader('', $title, '', '', 0, 0, array(), $TArrayOfCss);

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

if(! empty($dolibarr_user->id)) {
    print '<div class="underbanner clearboth"></div>';

    if($action == 'edit') {
        print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
        print '<input type="hidden" name="id" value="'.$id.'"/>';
        print '<input type="hidden" name="action" value="save"/>';

        print '<table class="border tableforfield" width="100%">';

        // Actif
        print '<tr>';
        print '<td class="titlefield">'.$langs->trans("Enabled").'</td>';
        print '<td colspan="2">';
        print '<input type="checkbox" name="active" '.(! empty($dolibarr_user->statut) ? 'checked="checked"' : '').'/>';
        print '</td></tr>';

        // Login
        print '<tr>';
        print '<td class="titlefield">'.$langs->trans("Login").'</td><td width="300" colspan="2">';
        print '<input type="text" name="login" value="'.$dolibarr_user->login.'" />';
        print '</td>';
        print '</tr>';

        // Pwd
        print '<tr><td>'.$langs->trans("Password").'</td><td width="300" colspan="2">';
        print '<input size="12" maxlength="32" type="password" class="flat" name="password" value="'.$object->pass.'" autocomplete="new-password">';
        print '</td></tr>';

        // Token
        print '<tr><td>'.$langs->trans("Token").'</td>';
        print '<td id="token" width="300">';
        print $dolibarr_user->array_options['options_token'];
        print '</td><td style="padding-left: 15px;"><i class="fa fa-refresh fa-lg" aria-hidden="true"></i></td></tr>';

        // Date Token
        print '<tr><td>'.$langs->trans("DateToken").'</td>';
        print '<td colspan="2">';
        print $form->select_date(empty($dolibarr_user->array_options['options_date_token']) ? -1 : $dolibarr_user->array_options['options_date_token'], 'date_token');
        print '</td></tr>';

        // Email
        print '<tr><td>'.$langs->trans("Email").'</td><td colspan="2">';
        print '<input type="text" name="email" value="'.$dolibarr_user->email.'" />';
        print '</td></tr>';

        // Telephone
        print '<tr><td>'.$langs->trans("Phone").'</td><td colspan="2">';
        print '<input type="text" name="phone" value="'.$dolibarr_user->user_mobile.'" />';
        print '</td></tr>';

        print '</table>';
        ?>

        <script type="text/javascript">
            $(document).ready(function() {
                $('i.fa.fa-refresh').click(function() {
                        $.ajax({
                            url: "./script/interface.php"
                            ,data: {
                                json: 1
                                ,action: 'generate_token'
                                ,fk_user: <?php echo $dolibarr_user->id; ?>
                            }
                            ,dataType: 'json'
                            ,type: 'POST'
                            ,async: true
                        }).done(function(token) {
                            $('#token').text(token);
                        });
                });
            });
        </script>
        <?php
    }
    else {  // View
        print '<table class="border tableforfield" width="100%">';

        // Actif
        print '<tr>';
        print '<td width="250">'.$langs->trans("Enabled").'</td>';
        print '<td colspan="2">';
        print '<input type="checkbox" name="active" disabled="disabled" '.(! empty($dolibarr_user->statut) ? 'checked="checked"' : '').'/>';
        print '</td></tr>';

        // Login
        print '<tr>';
        print '<td>'.$langs->trans("Login").'</td><td width="300" colspan="2">';
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
        print '<td width="300">'.$dolibarr_user->array_options['options_token'].'</td>';
        print '<td style="padding-left: 15px;">'.(empty($dolibarr_user->array_options['options_token']) ? '' : dol_buildpath('/onlineaccount/public/generate_pwd.php', 2).'?token='.$dolibarr_user->array_options['options_token']).'</td></tr>';

        // Date Token
        print '<tr><td>'.$langs->trans("DateToken").'</td><td colspan="2">'.date('d/m/Y', strtotime($dolibarr_user->array_options['options_date_token'])).'</td></tr>';

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
}

print '</div></div>';
print '<div class="tabsAction">';

if($action != 'edit') {
    if(empty($object->user_id)) print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=create_user">'.$langs->trans('CreateUser').'</a>';
    else {
        print '<a class="butActionRefused" title="Comming soon !" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=genPwd">'.$langs->trans('GeneratePassword').'</a>';
        print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=edit">'.$langs->trans('Modify').'</a>';
    }
}
else {
    print '<input class="butAction" type="submit" value="'.$langs->trans('Save').'"/>';
    print '</form>';
}
    
print '</div>';

llxFooter();