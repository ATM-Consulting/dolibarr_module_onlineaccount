<?php

/**
 * Ce script permet d'insérer un modèle de mail pour la réinitialisation des mots de passe seulement s'il en existe pas déjà un.
 */

global $db, $conf;

$type_template = 'password_reinit';

$sql = 'SELECT rowid';
$sql .= ' FROM '.MAIN_DB_PREFIX.'c_email_templates';
$sql .= " WHERE type_template='".$type_template."'";
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
