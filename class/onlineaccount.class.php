<?php

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
if(! class_exists('User')) require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
if(! class_exists('Contact')) require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
if(! class_exists('Societe')) require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

class TOnlineAccount {

    function __construct(&$db) {
        $this->db = &$db;
    }

    static function createUser(Contact $object, User &$dolibarr_user) {
        global $conf, $user;

        $login = dol_buildlogin($object->lastname.'.'.$object->id, $object->firstname);
        $pwd = getRandomPassword(false);

        if(! empty($conf->global->USER_MAIL_REQUIRED) && empty($object->email)) {
            $dolibarr_user->email = 'email@example.com';
            $object->email = $dolibarr_user->email;     // Just for tmp uses, in create function it will takes the contact email as user email
        }

        $res = $dolibarr_user->create_from_contact($object, $login);
        if($res <= 0) return -1;

        $res = $dolibarr_user->setPassword($user, $pwd, 0, 0, 1);

        if($res!=$pwd && $res <= 0) {
       		return -2;
        }

        // Si la conf n'est pas utilisée, l'utilisateur créé ne sera dans aucun groupes et ne pourra donc pas se connecter
        if(! empty($conf->global->ONLINE_ACCOUNT_DEFAULT_USER_GROUP)){

			$entityForGroup = $dolibarr_user->entity;
			if(!empty($conf->multicompany->enabled) && !empty($conf->global->ONLINE_ACCOUNT_DEFAULT_ENTITY) && intval($conf->global->ONLINE_ACCOUNT_DEFAULT_ENTITY) > 0) {
				$entityForGroup = intval($conf->global->ONLINE_ACCOUNT_DEFAULT_ENTITY);
			}

			$dolibarr_user->SetInGroup($conf->global->ONLINE_ACCOUNT_DEFAULT_USER_GROUP, $entityForGroup);

			// conf cachée, pour forcer l'affectation à l'entité 1, car l'authentification via API en as besoin à l'heure actuelle
			if (!empty($conf->global->ONLINE_ACCOUNT_DEFAULT_USER_GROUP_FORCE_INTO_ENTITY_ONE)) $dolibarr_user->SetInGroup($conf->global->ONLINE_ACCOUNT_DEFAULT_USER_GROUP, 1);
		}

		if(empty($dolibarr_user->api_key) && !empty($conf->global->ONLINE_GENERATE_USER_API_KEY)){
			require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
			$generatedApiKey=getRandomPassword(true);
			$dolibarr_user->api_key = $generatedApiKey;
			$res = $dolibarr_user->update($user);
		}

        $res = self::generateToken($dolibarr_user);
        if(! is_object($res) && $res <= 0) return -3;

        return 1;
    }

    /**
     *
     * @param User $dol_user
     * @param type $fk_user     if not empty, used to fetch User
     * @return \User
     */
    static function generateToken(&$dol_user, $fk_user = '') {
        global $db, $conf;

        if(empty($dol_user->id)) {
            if(empty($fk_user)) return -1;

            $dol_user = new User($db);
            $dol_user->fetch($fk_user);
        }

        if(empty($dol_user->email)) return -2;

        $dol_user->array_options['options_token'] = hash('sha256', $dol_user->email.time());
        $dol_user->array_options['options_date_token'] = dol_time_plus_duree(dol_now(), empty($conf->global->ONLINE_ACCOUNT_TOKEN_DURATION_DAY) ? 0 : $conf->global->ONLINE_ACCOUNT_TOKEN_DURATION_DAY, 'd');
        $dol_user->insertExtraFields();
        return $dol_user;
    }

    function getEMailTemplate($type_template, $user, $outputlangs, $id = 0, $active = 1) {
        $ret = array();

        $sql = "SELECT rowid, label, topic, content, lang";
        $sql .= " FROM ".MAIN_DB_PREFIX.'c_email_templates';
        $sql .= " WHERE type_template='".$this->db->escape($type_template)."'";
        $sql .= " AND entity IN (".getEntity("c_email_templates").")";
        $sql .= " AND (fk_user is NULL or fk_user = 0 or fk_user = ".$user->id.")";
        if($active >= 0) $sql .= " AND active = ".$active;
        if(is_object($outputlangs)) $sql .= " AND (lang = '".$outputlangs->defaultlang."' OR lang IS NULL OR lang = '')";
        if(!empty($id)) $sql .= " AND rowid=".$id;
        $sql .= $this->db->order("lang,label", "ASC");

        $resql = $this->db->query($sql);
        if($resql) {
            $obj = $this->db->fetch_object($resql); // Get first found
            if($obj) {
                $ret['fk_model'] = $obj->rowid;
                $ret['label'] = $obj->label;
                $ret['topic'] = $obj->topic;
                $ret['content'] = $obj->content;
                $ret['content_lines'] = $obj->content_lines;
                $ret['lang'] = $obj->lang;
            }
            else {
                $defaultmessage = '';
                if($type_template == 'facture_send') {
                    $defaultmessage = $outputlangs->transnoentities("PredefinedMailContentSendInvoice");
                }
                elseif($type_template == 'facture_relance') {
                    $defaultmessage = $outputlangs->transnoentities("PredefinedMailContentSendInvoiceReminder");
                }
                elseif($type_template == 'propal_send') {
                    $defaultmessage = $outputlangs->transnoentities("PredefinedMailContentSendProposal");
                }
                elseif($type_template == 'supplier_proposal_send') {
                    $defaultmessage = $outputlangs->transnoentities("PredefinedMailContentSendSupplierProposal");
                }
                elseif($type_template == 'order_send') {
                    $defaultmessage = $outputlangs->transnoentities("PredefinedMailContentSendOrder");
                }
                elseif($type_template == 'order_supplier_send') {
                    $defaultmessage = $outputlangs->transnoentities("PredefinedMailContentSendSupplierOrder");
                }
                elseif($type_template == 'invoice_supplier_send') {
                    $defaultmessage = $outputlangs->transnoentities("PredefinedMailContentSendSupplierInvoice");
                }
                elseif($type_template == 'shipping_send') {
                    $defaultmessage = $outputlangs->transnoentities("PredefinedMailContentSendShipping");
                }
                elseif($type_template == 'fichinter_send') {
                    $defaultmessage = $outputlangs->transnoentities("PredefinedMailContentSendFichInter");
                }
                elseif($type_template == 'thirdparty') {
                    $defaultmessage = $outputlangs->transnoentities("PredefinedMailContentThirdparty");
                }

                $ret['label'] = 'default';
                $ret['topic'] = '';
                $ret['content'] = $defaultmessage;
                $ret['content_lines'] = '';
                $ret['lang'] = $outputlangs->defaultlang;
            }

            $this->db->free($resql);
            return $ret;
        }
        else {
            dol_print_error($this->db);
            return -1;
        }
    }

    function sendMail(&$object, $fk_model = 0, $TParams = array()) {
        global $user, $langs, $conf, $mysoc;

        $trackid = 'inv'.$object->id;
        $subject = '';
        $actionmsg = '';
        $actionmsg2 = '';
        $modelmail = empty($TParams['model']) ? 'password_reinit' : $TParams['model'];
        $action = 'send';

        if(empty($user->id) && ! empty($TParams['user'])) {
            $user = $TParams['user'];
        }
        $email_from = $user->email;

        $arraydefaultmessage = $this->getEMailTemplate($modelmail, $user, $langs, $fk_model);

        $langs->load('mails');

        $sendto = '';
        $sendtocc = '';
        $sendtobcc = '';
        $sendtoid = array();

        include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
        $formmail = new FormMail($this->db);
        $formmail->setSubstitFromObject($object, $langs);
        $formmail->substit['__ONLINE_ACCOUNT_USER__'] = $object->login;
		$formmail->substit['__ONLINE_ACCOUNT_LINK__'] = $TParams['OnlineAccountLink'];
		$formmail->substit['__MYCOMPANY_NAME__'] = $mysoc->nom;
        $formmail->substit['__COMPANY_CUSTOMER_CODE__'] = '';
        if(! empty($object->socid)) {
            $soc = new Societe($object->db);
            $soc->fetch($object->socid);

            $formmail->substit['__COMPANY_CUSTOMER_CODE__'] = $soc->code_client;
        }

        if(empty($To)) {
            if(!empty($object->email)) $To[] = $object->email;
        }

        $sendto = implode(',', $To);

        if(dol_strlen($sendto)) {
            require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

            // Create form object
            $langs->load("commercial");


            $from = $user->getFullName($langs).' <'.$email_from.'>';
            $replyto = $from;

            $message = $arraydefaultmessage['content'];
            $subject = $arraydefaultmessage['topic'];

            $message = make_substitutions($message, $formmail->substit);
            $subject = make_substitutions($subject, $formmail->substit);

            $sendtobcc = (empty($conf->global->MAIN_MAIL_AUTOCOPY_INVOICE_TO) ? '' : (($sendtobcc ? ", " : "").$conf->global->MAIN_MAIL_AUTOCOPY_INVOICE_TO));

            if($action == 'send' || $action == 'relance') {
                $actionmsg2 = $langs->transnoentities('MailSentBy').' '.CMailFile::getValidAddress($from, 4, 0, 1).' '.$langs->transnoentities('To').' '.CMailFile::getValidAddress($sendto, 4, 0, 1);
                if($message) {
                    $actionmsg = $langs->transnoentities('MailFrom').': '.dol_escape_htmltag($from);
                    $actionmsg = dol_concatdesc($actionmsg, $langs->transnoentities('MailTo').': '.dol_escape_htmltag($sendto));
                    if($sendtocc) $actionmsg = dol_concatdesc($actionmsg, $langs->transnoentities('Bcc').": ".dol_escape_htmltag($sendtocc));
                    $actionmsg = dol_concatdesc($actionmsg, $langs->transnoentities('MailTopic').": ".$subject);
                    $actionmsg = dol_concatdesc($actionmsg, $langs->transnoentities('TextUsedInTheMessageBody').":");
                    $actionmsg = dol_concatdesc($actionmsg, $message);
                }
            }

            // Send mail
            $mailfile = new CMailFile($subject, $sendto, $from, $message, array(), '', '', $sendtocc, $sendtobcc, '', -1, '', '', $trackid);
            if($mailfile->error) {
                echo 'ERR '.$mailfile->error.'<br />';
                dol_syslog($mailfile->error, LOG_ERR);
                $action = 'presend';
                return -1;
            }
            else {
                $result = $mailfile->sendfile();
                if(! $result) {
                    $langs->load("other");
                    $mesg = '';
                    if($mailfile->error) {
                        $mesg .= $langs->trans('ErrorFailedToSendMail', $from, $sendto);
                        $mesg .= ' - '.$mailfile->error;
                    }
                    else {
                        $mesg .= ' - No mail sent. Feature is disabled by option MAIN_DISABLE_ALL_MAILS';
                    }

                    echo 'ERR2 '.$mesg.'<br />';
                    dol_syslog($mesg, LOG_ERR);
                    return -2;
                }
            }
        }
        else {
            $langs->load("errors");
            setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv("MailTo")), null, 'warnings');
            dol_syslog('Try to send email with no recipiend defined', LOG_WARNING);
            $action = 'presend';
            return -3;
        }
        return 1;
    }

}
