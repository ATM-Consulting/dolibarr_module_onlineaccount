<?php

class OnlineAccount {

    function __construct(&$db) {
        $this->db = &$db;
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
        global $user, $langs, $conf;

        $trackid = 'inv'.$object->id;
        $subject = '';
        $actionmsg = '';
        $actionmsg2 = '';
        $modelmail = 'password_reinit';
        $action = 'send';

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

        if(empty($To)) {
            if(!empty($object->email)) $To[] = $object->email;
        }

        $sendto = implode(',', $To);

        if(dol_strlen($sendto)) {
            require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

            // Create form object
            $langs->load("commercial");


            $from = $user->getFullName($langs).' <'.$user->email.'>';
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
            $mailfile = new CMailFile($subject, $sendto, $from, $message, '', '', '', $sendtocc, $sendtobcc, '', -1, '', '', $trackid);
            if($mailfile->error) {
                echo 'ERR '.$mailfile->error.'<br />';
                dol_syslog($mailfile->error, LOG_ERR);
                $action = 'presend';
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
                }
            }
        }
        else {
            $langs->load("errors");
            setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv("MailTo")), null, 'warnings');
            dol_syslog('Try to send email with no recipiend defined', LOG_WARNING);
            $action = 'presend';
        }
    }

}
