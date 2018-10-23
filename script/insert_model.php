<?php

/**
 * Ce script permet d'insérer un modèle de mail pour la réinitialisation des mots de passe,
 * ainsi que de première connexion seulement s'ils n'existent pas.
 */

global $db, $conf;

$sql = 'SELECT rowid';
$sql .= ' FROM '.MAIN_DB_PREFIX.'c_email_templates';
$sql .= " WHERE type_template='password_reinit'";
$sql .= ' AND active=1';
$sql .= ' AND entity='.$conf->entity;
$sql .= ' LIMIT 1';

$resql = $db->query($sql);
if($resql) {
    // If no email template found, we insert one
    if(! ($obj = $db->fetch_object($resql)) ) {
        $db->begin();

        $sql_insert = 'INSERT INTO '.MAIN_DB_PREFIX.'c_email_templates(`entity`, `module`, `type_template`, `lang`, `private`, `fk_user`, `datec`, `tms`, `label`, `position`, `active`, `topic`, `content`, `content_lines`)';
        $sql_insert .= "VALUES (".$conf->entity.", NULL, 'password_reinit', NULL, 0, NULL, NULL, '2018-08-22 12:38:18', 'Réinitialisation mot de passe', 55, 1, 'Compte en ligne - Réinitialisation du mot de passe', 'Bonjour,<br />\n<br />\nVeuillez cliquer sur le lien ci dessous pour r&eacute;initialiser le mot de passe de l&#39;utilisateur \"__ONLINE_ACCOUNT_USER__\"<br />\n__ONLINE_ACCOUNT_LINK__<br />\n<br />\nBien cordialement,<br />\n<br />\n__SIGNATURE__', NULL)";

        $res = $db->query($sql_insert);

        if($res) $db->commit();
        else $db->rollback();
        $db->free($res);
    }
}

$db->free($resql);

$sql = 'SELECT rowid';
$sql .= ' FROM '.MAIN_DB_PREFIX.'c_email_templates';
$sql .= " WHERE type_template='first_connection'";
$sql .= ' AND active=1';
$sql .= ' AND entity='.$conf->entity;
$sql .= ' LIMIT 1';

$resql = $db->query($sql);
if($resql) {
    // If no email template found, we insert one
    if(! ($obj = $db->fetch_object($resql)) ) {
        $db->begin();

        $sql_insert = 'INSERT INTO '.MAIN_DB_PREFIX.'c_email_templates(`entity`, `module`, `type_template`, `lang`, `private`, `fk_user`, `datec`, `tms`, `label`, `position`, `active`, `topic`, `content`, `content_lines`)';
        $sql_insert .= "VALUES (".$conf->entity.", NULL, 'first_connection', NULL, 0, NULL, NULL, '2018-10-23 06:49:56', 'Première Connexion', 56, 1, 'Compte en ligne - Première connexion', 'Bonjour,<br />\r\n<br />\r\nBienvenue au&nbsp;__MYCOMPANY_NAME__<br />\r\nMerci de d&eacute;finir votre mot de passe en suivant le lien ci-dessous :<br />\r\n__ONLINE_ACCOUNT_LINK__<br />\r\n<br />\r\nPuis vous pouvez vous connecter &agrave; notre portail...<br />\r\nIdentifiant :&nbsp;__ONLINE_ACCOUNT_USER__<br />\r\nCode Client : __COMPANY_CUSTOMER_CODE__<br />\r\n<br />\r\nBien cordialement,<br />\r\n<br />\r\n__SIGNATURE__', NULL)";

        $res = $db->query($sql_insert);

        if($res) $db->commit();
        else $db->rollback();
        $db->free($res);
    }
}

$db->free($resql);
