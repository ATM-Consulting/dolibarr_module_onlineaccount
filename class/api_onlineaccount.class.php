<?php
/* Copyright (C) 2015   Jean-François Ferry     <jfefe@aternatik.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

 use Luracast\Restler\RestException;

 require_once DOL_DOCUMENT_ROOT.'/societe/class/client.class.php';
 require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
 require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
 require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
 
 dol_include_once('onlineaccount/class/onlineaccount.class.php');

/**
 * API class for Onlineaccount
 *
 * 
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class onlineaccount extends DolibarrApi
{
    /**
     * @var array   $FIELDS     Mandatory fields, checked when create and update object 
     */
    static $FIELDS = array(
//         exemple

//         'objecttypes' => array(
//             'mandatoryFields' => array('id', 'entity')
//             ,'fieldTypes' => array(
//                 'id' => 'int'
//                 ,'entity' => 'int'
//                 ,'label' => 'string'
//                 ,'price' => 'float'
//             )
//          )
    );


    /**
     * @var User $user {@type User}
     */
    public $user;

    /**
     * @var Contact $contact {@type Contact}
     */
    public $contact;

    /**
     * Constructor
     * 
     */
    function __construct()
    {
		global $db, $langs;
        $langs->load('onlineaccount@onlineaccount');

		$this->db = $db;
		$this->user = new User($this->db);  // User
        $this->contact = new Contact($this->db);    // Contact
    }

    /**
     * Create an Online Account
     * 
     * Send en email to reset Online Account password
     * 
     * @param   int $fk_contact Id of contact
     * @param   int $entity Entity of user and group to set
     * @return  array
     * 
     * @url     POST /create
     * @throws  RestException
     */
    function createOnlineAccount($fk_contact, $entity) {
        global $langs;

        $res = $this->contact->fetch($fk_contact);
        if($res <= 0) {
            throw new RestException(404, 'No user found');  // Not Found
        }

        // User already exists
        if(! empty($this->contact->user_id)) {
            return array('success' => array('code' => 200, 'message' => $langs->transnoentities('UserAlreadyExists') , 'result' => $this->get($this->contact->user_id)));
        }
        else {
        	$this->user->entity = $entity;
        	$res = TOnlineAccount::createUser($this->contact, $this->user);
            if($res <= 0) {
                throw new RestException(400, $langs->transnoentities('UserCreationProblem'));  // Bad Request
            }
            return array('success' => array('code' => 201, 'result' => $this->get($this->user->id)));
        }
    }

    /**
     * Generate Token and validity date
     * 
     * Allow to generate a new Token and validity date for an user
     * 
     * @param   int $id Id of user
     * @return  array
     * 
     * @url     POST /generate_token
     * @throws  RestException
     */
    function generateToken($id) {
        global $langs;

        $res = $this->user->fetch($id);
        if($res <= 0) {
            throw new RestException(404, 'No user found');  // Not Found
        }

        $res = TOnlineAccount::generateToken($this->user);
        if(! is_object($res) && $res <= 0) {
            throw new RestException(400, $langs->transnoentities('GenerateTokenProblem'));  // Bad Request
        }
        return array('success' => array('code' => 201, 'result' => $this->get($this->user->id)));
    }
    
    /**
     * Get Online Account info
     *
     * Retreive all informations from an online account
     *
     * @param int $id Id of user
     * @return array
     *
     * @url     GET /{id}
     * @throws RestException
     */
    function get($id) {
        $res = $this->user->fetch($id);

        if($res > 0) {
            $user = $this->_cleanObjectDatas($this->user);
            $TRes = array(
                'active' => empty($user->statut) ? 0 : 1
                ,'id' => $user->id
                ,'login' => $user->login
                ,'pass_crypted' => $user->pass_indatabase_crypted
                ,'token' => $user->array_options['options_token']
                ,'date_token' => $user->array_options['options_date_token']
                ,'email' => $user->email
                ,'phone' => $user->user_mobile
            );

            return $TRes;
        }
        else {
            throw new RestException(404, 'No user found');
        }
    }

    /**
     * Reset Online Account password
     *
     * Send en email to reset Online Account password
     *
     * @param int $id Id of user
     * @return array
     *
     * @url     POST /reinit
     * @throws RestException
     */
    function reinitPWD($id) {
        global $langs;

        $res = $this->user->fetch($id);
        if($res <= 0) {
            throw new RestException(404, 'No user found');  // Not Found
        }
        if(empty($this->user->array_options['options_token'])) {
            throw new RestException(204, $langs->transnoentities('EmptyToken'));  // No Content
        }

        $online_account = new TOnlineAccount($this->db);
        $TParams = array(
            'OnlineAccountLink' => '<a href="'.dol_buildpath('/onlineaccount/public/generate_pwd.php', 2).'?token='.$this->user->array_options['options_token'].'">'.$langs->transnoentities('GeneratePassword').'</a>'
            ,'user' => &DolibarrApiAccess::$user
        );

        $res = $online_account->sendMail($this->user, 0, $TParams);
        if($res > 0) {
            return array('success' => array('code' => 200, 'message' => $langs->transnoentities('onlineaccountResetPwdEmailSentTo', $this->user->email)));
        }

        throw new RestException(400, 'No mail sent.', array('return_code' => $res));  // Bad Request
    }

    /**
     * Set Online Account password
     *
     * Send en email to set an Online Account password
     *
     * @param int $id Id of user
     * @return array
     *
     * @url     POST /firstConnection
     * @throws RestException
     */
    function firstConnection($id) {
        global $langs;

        $res = $this->user->fetch($id);
        if($res <= 0) {
            throw new RestException(404, 'No user found');  // Not Found
        }
        if(empty($this->user->array_options['options_token'])) {
            throw new RestException(204, $langs->transnoentities('EmptyToken'));  // No Content
        }

        $online_account = new TOnlineAccount($this->db);
        $TParams = array(
            'OnlineAccountLink' => '<a href="'.dol_buildpath('/onlineaccount/public/generate_pwd.php', 2).'?token='.$this->user->array_options['options_token'].'">'.$langs->transnoentities('GeneratePassword').'</a>'
            ,'model' => 'first_connection'
            ,'user' => &DolibarrApiAccess::$user
        );

        $res = $online_account->sendMail($this->user, 0, $TParams);
        if($res > 0) {
            return array('success' => array('code' => 200, 'message' => $langs->transnoentities('onlineaccountResetPwdEmailSentTo', $this->user->email)));
        }

        throw new RestException(400, 'No mail sent.', array('return_code' => $res));  // Bad Request
    }

    /***************************************************************** Common Part *****************************************************************/
    /**
     * Clean sensible object datas
     *
     * @param   Categorie  $object    Object to clean
     * @return    array    Array of cleaned object properties
     */
    function _cleanObjectDatas($object) {

        $object = parent::_cleanObjectDatas($object);

        // Remove fields not relevent to categories
        unset($object->country);
        unset($object->country_id);
        unset($object->country_code);
        unset($object->total_ht);
        unset($object->total_ht);
        unset($object->total_localtax1);
        unset($object->total_localtax2);
        unset($object->total_ttc);
        unset($object->total_tva);
        unset($object->lines);
        unset($object->fk_incoterms);
        unset($object->libelle_incoterms);
        unset($object->location_incoterms);
        unset($object->civility_id);
        //unset($object->name);
        //unset($object->lastname);
        //unset($object->firstname);
        unset($object->shipping_method_id);
        unset($object->fk_delivery_address);
        unset($object->cond_reglement);
        unset($object->cond_reglement_id);
        unset($object->mode_reglement_id);
        unset($object->barcode_type_coder);
        unset($object->barcode_type_label);
        unset($object->barcode_type_code);
        unset($object->barcode_type);
        unset($object->canvas);
        unset($object->cats);
        unset($object->motherof);
        unset($object->context);
        unset($object->socid);
        unset($object->thirdparty);
        unset($object->contact);
        unset($object->contact_id);
        unset($object->user);
        unset($object->fk_account);
        unset($object->fk_project);
        unset($object->note);
        unset($object->statut);
        unset($object->labelstatut);
        unset($object->labelstatut_short);

        return $object;
    }

    /**
     * Validate fields before create or update object
     * 
     * @param array|null    $data           Data to validate
     * @param string        $objecttype     type of object
     * @return array
     * 
     * @throws RestException
     */
    function _validate($data, $objecttype)
    {
        if(empty($objecttype)) throw new RestException(503, "Can't guess what type of object to validate");
        if(!isset(self::$FIELDS[$objecttype])) throw new RestException(503, "Unknown object type to validate");

        $object = array();
        foreach (self::$FIELDS[$objecttype]['mandatoryFields'] as $field) {
            if (!isset($data[$field]) || empty($data[$field]) || $data[$field] == -1)
                throw new RestException(400, "$field field missing");
            $object[$field] = $data[$field];
        }
        return $object;
    }
}
