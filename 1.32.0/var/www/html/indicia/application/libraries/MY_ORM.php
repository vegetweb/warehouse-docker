<?php

/**
 * Indicia, the OPAL Online Recording Toolkit.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses/gpl.html.
 *
 * @package    Core
 * @subpackage Libraries
 * @author    Indicia Team
 * @license    http://www.gnu.org/licenses/gpl.html GPL
 * @link     http://code.google.com/p/indicia/
 */

/**
 * Override of the Kohana core ORM class which provides Indicia specific functionality for submission of data.
 * ORM objects are normally instantiated by calling ORM::Factory(modelname[, id]). For Indicia ORM objects,
 * there is an option to pass -1 as the ID indicating that the ORM object should not be initialised. This
 * allows access to variables such as the lookup table and search field without full instantiation of the ORM
 * object, saving hits on the database etc.
 */
class ORM extends ORM_Core {

  /**
   * Authorised website ID from the service authentication.
   * @var integer
   */
  public static $authorisedWebsiteId=0;
  /**
  * Should foreign key lookups be cached? Set to true during import for example.
  * @var bool
  */
  public static $cacheFkLookups = false;


  /**
   * Tracks list of all inserted, updated or deleted records in this transaction.
   * @var array
   */
  public static $changedRecords;

  public function last_query() {
    return $this->db->last_query();
  }

  public $submission = array();

  /**
   * @var array Describes the list of nested models that are present after a submission. E.g. the list of
   * occurrences in a sample.
   */
  private $nestedChildModelIds = array();
  private $nestedParentModelIds = array();

  /**
   * @var string The default field that is searchable is called title. Override this when a different field name is used.
   */
  public $search_field='title';

  protected $errors = array();

  /**
   * @var boolean Flag that gets set if a unique key violation has occurred on attempting a save.
   */
  public $uniqueKeyViolation = false;

  protected $identifiers = array('website_id'=>null,'survey_id'=>null);

  /**
   * @var array unvalidatedFields allows a list of fields which are not validated in anyway to be declared
   * by a model. If not declared then the model will not transfer them to the saved data when
   * posting a record.
   */
  protected $unvalidatedFields = array();

  /**
   * @var array An array which a model can populate to declare additional fields that can be submitted for csv upload.
   */
  protected $additional_csv_fields=array();

  /**
   * @var boolean Does the model have custom attributes? Defaults to false.
   */
  protected $has_attributes = false;

  /**
   * @var boolean If the model has custom attributes, are public ones always available across the warehouse, or
   * does it require a link to a website to include the attribute in the submissable data? Defaults to false.
   */
  public $include_public_attributes = false;

  /**
   * @var boolean Is this model for an existing record that is being saved over?
   */
  protected $existing = false;

  private $cache;

  /**
   * Default behaviour on save is to update metadata. If we detect no changes we can skip this.
   * @var boolean
   */
  public $wantToUpdateMetadata = true;

  private $attrValModels = array();

  /**
   * @var array If a submission contains submodels, then the array of submodels can be keyed. This
   * allows other foreign key fields in the submisson to refer to a model which does not exist yet.
   * Normally, super/sub-models can handle foreign keys, but this approach is needed for association
   * tables which join across 2 entities created by a submission.
   */
  private $dynamicRowIdReferences = array();

  /**
   * Constructor allows plugins to modify the data model.
   * @var int $id ID of the record to load. If null then creates a new record. If -1 then the ORM
   * object is not initialised, providing access to the variables only.
   */
  public function __construct($id = NULL)
  {
    if (is_object($id) || $id!=-1) {
      // use caching, so things don't slow down if there are lots of plugins. the object_name does not
      // exist yet as we haven't called the parent construct, so we build our own.
      $object_name = strtolower(substr(get_class($this), 0, -6));
      $cacheId = 'orm-'.$object_name;
      $this->cache = Cache::instance();
      $ormRelations = $this->cache->get($cacheId);
      if ($ormRelations === null) {
        // now look for modules which plugin to tweak the orm relationships.
        foreach (Kohana::config('config.modules') as $path) {
          $plugin = basename($path);
          if (file_exists("$path/plugins/$plugin.php")) {
            require_once("$path/plugins/$plugin.php");
            if (function_exists($plugin.'_extend_orm')) {
              $extends = call_user_func($plugin.'_extend_orm');
              if (isset($extends[$object_name])) {
                if (isset($extends[$object_name]['has_one']))
                  $this->has_one = array_merge($this->has_one, $extends[$object_name]['has_one']);
                if (isset($extends[$object_name]['has_many']))
                  $this->has_many = array_merge($this->has_many, $extends[$object_name]['has_many']);
                if (isset($extends[$object_name]['belongs_to']))
                  $this->belongs_to = array_merge($this->belongs_to, $extends[$object_name]['belongs_to']);
                if (isset($extends[$object_name]['has_and_belongs_to_many']))
                  $this->has_and_belongs_to_many = array_merge($this->has_and_belongs_to_many, $extends[$object_name]['has_and_belongs_to_many']);
              }
            }
          }
        }
        $cacheArray = array(
          'has_one' => $this->has_one,
          'has_many' => $this->has_many,
          'belongs_to' => $this->belongs_to,
          'has_and_belongs_to_many' => $this->has_and_belongs_to_many
        );
        $this->cache->set($cacheId, $cacheArray);
      } else {
        $this->has_one = $ormRelations['has_one'];
        $this->has_many = $ormRelations['has_many'];
        $this->belongs_to = $ormRelations['belongs_to'];
        $this->has_and_belongs_to_many = $ormRelations['has_and_belongs_to_many'];
      }
      parent::__construct($id);
    }
  }

  /**
   * Returns an array structure which describes this model, identifier and timestamp fields, plus the saved child models
   * that were created during a submission operation.
   */
  public function get_submission_response_metadata() {
    $r = array(
      'model' => $this->object_name,
      'id' => $this->id,
    );
    // Add the external key and timestamps if present
    if (!empty($this->external_key))
      $r['external_key'] = $this->external_key;
    if (!empty($this->created_on))
      $r['created_on'] = $this->created_on;
    if (!empty($this->updated_on))
      $r['updated_on'] = $this->updated_on;
    if (count($this->nestedChildModelIds))
      $r['children'] = $this->nestedChildModelIds;
    if (count($this->nestedParentModelIds))
      $r['parents'] = $this->nestedParentModelIds;
    return $r;
  }

  /**
   * Override load_values to add in a vague date field. Also strips out any custom attribute values which don't go into this model.
   * @param   array  values to load
   * @return  ORM
   */
  public function load_values(array $values)
  {
    // clear out any values which match this attribute field prefix
    if (isset($this->attrs_field_prefix)) {
      foreach ($values as $key=>$value) {
        if (substr($key, 0, strlen($this->attrs_field_prefix)+1)==$this->attrs_field_prefix.':') {
          unset($values[$key]);
        }
      }
    }
    parent::load_values($values);
    // Add in date field
    if (array_key_exists('date_type', $this->object) && !empty($this->object['date_type']))
    {
      $vd = vague_date::vague_date_to_string(array
      (
        $this->object['date_start'],
        $this->object['date_end'],
        $this->object['date_type']
      ));
      $this->object['date'] = $vd;
    }
    return $this;
  }

  /**
   * Override the reload_columns method to add the vague_date virtual field
   * @param bool $force Reload the columns from the db even if already loaded
   * @return $this|\ORM
   * @throws \Kohana_Database_Exception
   */
  public function reload_columns($force = FALSE)
  {
    if ($force === TRUE OR empty($this->table_columns))
    {
      // Load table columns
      $this->table_columns = postgreSQL::list_fields($this->table_name, $this->db);
      // Vague date
      if (array_key_exists('date_type', $this->table_columns))
      {
        $this->table_columns['date']['type'] = 'String';
      }
    }

    return $this;
  }

  /**
   * Provide an accessor so that the view helper can retrieve the error for the model by field name.
   * Will also retrieve errors from linked models (models that were posted in the same submission)
   * if the field name is of the form model:fieldname.
   *
   * @param string $fieldname Name of the field to retrieve errors for. The fieldname can either be
   * simple, or of the form model:fieldname in which linked models can also be checked for errors. If the
   * submission structure defines the fieldPrefix for the model then this is used instead of the model name.
   * @return string The error text.
   */
  public function getError($fieldname) {
    $r='';
    if (array_key_exists($fieldname, $this->errors)) {
      // model is unspecified, so load error from this model.
      $r=$this->errors[$fieldname];
    } elseif (strpos($fieldname, ':')!==false) {
      list($model, $field)=explode(':', $fieldname);
      // model is specified
      $struct=$this->get_submission_structure();
      $fieldPrefix = array_key_exists('fieldPrefix', $struct) ? $struct['fieldPrefix'] : $this->object_name;
      if ($model==$fieldPrefix) {
        // model is this model
        if (array_key_exists($field, $this->errors)) {
          $r=$this->errors[$field];
        }
      }
    }
    return $r;
  }

  /**
   * Retrieve an array containing all errors.
   * The array entries are of the form 'entity:field => value'.
   */
  public function getAllErrors()
  {
    // Get this model's errors, ensuring array keys have prefixes identifying the entity
    foreach ($this->errors as $key => $value) {
      if (strpos($key, ':')===false) {
        $this->errors[$this->object_name.':'.$key]=$value;
        unset($this->errors[$key]);
      }
    }
    return $this->errors;
  }

  /**
   * Retrieve an array containing all page level errors which are marked with the key general.
   */
  public function getPageErrors() {
    $r = array();
    if (array_key_exists('general', $this->errors)) {
      array_push($r, $this->errors['general']);
    }
    return $r;
  }

  /**
   * Override the ORM validate method to store the validation errors in an array, making
   * them accessible to the views.
   *
   * @param Validation $array Validation array object.
   * @param boolean $save Optional. True if this call also saves the data, false to just validate. Default is false.
   * @return boolean Returns TRUE on success or FALSE on fail.
   * @throws Exception Rethrows any exceptions occurring on validate.
   */
  public function validate(Validation $array, $save = FALSE) {
    // set the default created/updated information
    if ($this->wantToUpdateMetadata)
      $this->set_metadata();
    $modelFields=$array->as_array();
    $fields_to_copy=$this->unvalidatedFields;
    // the created_by_id and updated_by_id fields can be specified by web service calls if the
    // caller knows which Indicia user is making the post.
    if (!empty($modelFields['created_by_id']))
      $fields_to_copy[] = 'created_by_id';
    if (!empty($modelFields['updated_by_id']))
      $fields_to_copy[] = 'updated_by_id';
    foreach ($fields_to_copy as $a)
    {
      if (array_key_exists($a, $modelFields)) {
        // When a field allows nulls, convert empty values to null. Otherwise we end up trying to store '' in non-string
        // fields such as dates.
        if ($array[$a]==='' && isset($this->table_columns[$a]['null']) && $this->table_columns[$a]['null']==1) {
          $array[$a]=null;
        }
        $this->__set($a, $array[$a]);
      }
    }
    try {
      if (parent::validate($array, $save)) {
        return TRUE;
      }
      else {
        // put the trimmed and processed data back into the model
        $arr = $array->as_array();
        if (array_key_exists('created_on', $this->table_columns)) {
          $arr['created_on'] = $this->created_on;
        }
        if (array_key_exists('updated_on', $this->table_columns)) {
          $arr['updated_on'] = $this->updated_on;
        }
        $this->load_values($arr);
        $this->errors = $array->errors('form_error_messages');
        return FALSE;
      }
    } catch (Exception $e) {
      kohana::log('error', 'Error: '.$e->getMessage());
      if (strpos($e->getMessage(), '_unique')!==false) {
        // duplicate key violation
        $this->errors = array('general' => 'You cannot add the record as it would create a duplicate.');
        $this->uniqueKeyViolation=true;
        return FALSE;
      } else
        throw ($e);
    }
  }

  /**
   * For a model that is about to be saved, sets the metadata created and
   * updated field values.
   * @param object $obj The object which will have metadata set on it. Defaults to this model.
   */
  public function set_metadata($obj=null) {
    if ($obj==null) $obj=$this;
    $force=true;
    // At this point we determine the id of the logged in user,
    // and use this in preference to the default id if possible.
    if (isset($_SESSION['auth_user']))
      $userId = $_SESSION['auth_user']->id;
    else {
      global $remoteUserId;
      if (isset($remoteUserId))
        $userId = $remoteUserId;
      else {
        // Don't force overwrite of user IDs that already exist in the record, since
        // we are just using a default.
        $force=false;
        $defaultUserId = Kohana::config('indicia.defaultPersonId');
        $userId = ($defaultUserId ? $defaultUserId : 1);
      }
    }
    // Set up the created and updated metadata for the record
    if (!$obj->id && array_key_exists('created_on', $obj->table_columns)) {
      $obj->created_on = date("Ymd H:i:s");
      if ($force or !$obj->created_by_id) $obj->created_by_id = $userId;
    }
    // TODO: Check if updated metadata present in this entity,
    // and also use correct user.
    if (array_key_exists('updated_on', $obj->table_columns)) {
      $obj->updated_on = date("Ymd H:i:s");
      if ($force or !$obj->updated_by_id) {
        if ($obj->id)
          $obj->updated_by_id = $userId;
        else
          // creating a new record, so it must be the same updator as creator.
          $obj->updated_by_id = $obj->created_by_id;
      }
    }
  }

  /**
   * Do a default search for an item using the search_field setup for this model.
   * @param $search_text Text to look up
   * @return ORM The ORM object filtered to look up the text
   */
  public function lookup($search_text)
  {
    return $this->where($this->search_field, $search_text)->find();
  }

  /**
   * Return a displayable caption for the item, defined as the content of the field with the
   * same name as search_field.
   */
  public function caption()
  {
    if ($this->id) {
      return $this->__get($this->search_field);
    } else {
      return $this->getNewItemCaption();
    }
  }

  /**
   * Retrieve the caption of a new entry of this model type. Overrideable as required.
   * @return string Caption for a new entry.
   */
  protected function getNewItemCaption() {
    return ucwords(str_replace('_', ' ', $this->object_name));
  }

  /**
   * Indicates if this model type can create new instances from data supplied in its caption format.
   * Overrideable as required.
   * @return boolean, override to true if your model supports this.
   */
  protected function canCreateFromCaption() {
    return false;
  }

  /**
   * Puts each supplied caption in a submission and sends it to the supplied model.
   * @return array, an array of record id values for the created records.
   */
  private function createRecordsFromCaptions() {
    $r = array();

    // Establish the right model and check it supports create from captions,
    $modelname = $this->submission['fields']['insert_captions_to_create']['value'];
    $m = ORM::factory($modelname);
    if ($m->canCreateFromCaption()) {
      // get the array of captions
      $fieldname = $this->submission['fields']['insert_captions_use']['value'];
      if (empty($this->submission['fields'][$fieldname])
        || empty($this->submission['fields'][$fieldname]['value'])) {
        return $r;
      }
      $captions = $this->submission['fields'][$fieldname]['value'];
      // build a skeleton submission
      $sub = array(
        'id' => $modelname,
        'fields' => array(
          'caption' => array()
        )
      );
      // submit each caption to create a record, unless it exists
      $i=0;
      foreach ($captions as $value) {
        // sanitize caption
        $value = trim(preg_replace('/\s+/',' ', $value));
        $id = $m->findByCaption($value);
        if ($id > 0) { // record exists
          $r[$i] = $id;
        } else { // create new record
          $sub['fields']['caption']['value'] = $value;
          $m = ORM::factory($modelname);
          $m->submission = $sub;
          // copy down the website id and survey id
          $m->identifiers = array_merge($this->identifiers);
          $r[$i] = $m->inner_submit();
        }
        $i++;
      }
    }

    return $r;
  }

  /**
   * When using a sublist control (or any similar multi-value control), non-existing
   * values added  to the list are posted as captions, These need to be converted to
   * IDs in the table identified
   Puts each supplied record id into the submission to replace the captions
   * so we store IDs instead.
   * @param array $ids
   * @return boolean.
   */
  private function createIdsFromCaptions($ids) {
    $fieldname = $this->submission['fields']['insert_captions_use']['value'];
    if(empty($ids)){
	$this->submission['fields'][$fieldname] = array('value'=>array());
    }
    else{
    	$keys = array_fill(0, sizeof($ids), 'value');
    	$a = array_fill_keys($keys, $ids);
    	$this->submission['fields'][$fieldname] = $a;
    }
    return true;
  }

  /**
   * Overridden if this model type can create new instances from data supplied in its caption format.
   * @return integer The id of the first matching record with the supplied caption or 0 if no match.
   */
  protected function findByCaption($caption) {
    return 0;
  }

  /**
   * Overridden if this model type can create new instances from data supplied in its caption format.
   * Does nothing if not overridden.
   * @return boolean, override to true if your model supports this.
   */
  protected function handleCaptionSubmission() {
    return false;
  }

  /**
   * Ensures that the save array is validated before submission. Classes overriding
   * this method should call this parent method after their changes to perform necessary
   * checks unless they really want to skip them.
   */
  protected function preSubmit() {
    // Where fields are numeric, ensure that we don't try to submit strings to
    // them.
    foreach ($this->submission['fields'] as $field => $content) {
      if (isset($content['value']) && $content['value'] == '' && array_key_exists($field, $this->table_columns)) {
        $type = $this->table_columns[$field]['type'];
        switch ($type) {
          case 'int':
            $this->submission['fields'][$field]['value'] = null;
            break;
          }
      }
    }
    // if the current model supports attributes then
    // create records from captions if this has been requested.
    if ($this->has_attributes
      && !empty($this->submission['fields']['insert_captions_to_create'])
      && !empty($this->submission['fields']['insert_captions_to_create']['value'])
      && !empty($this->submission['fields']['insert_captions_use'])
      && !empty($this->submission['fields']['insert_captions_use']['value'])) {

      $ids = $this->createRecordsFromCaptions();
      $this->createIdsFromCaptions($ids);
      unset($this->submission['fields']['insert_captions_to_create']);
      unset($this->submission['fields']['insert_captions_use']);
    }
  }

  /**
   * Grab the survey id and website id if they are in the submission, as they are used to check
   * attributes that apply and other permissions.
   */
  protected function populateIdentifiers() {
    if (array_key_exists('website_id', $this->submission['fields'])) {
      if (is_array($this->submission['fields']['website_id']))
        $this->identifiers['website_id']=$this->submission['fields']['website_id']['value'];
      else
        $this->identifiers['website_id']=$this->submission['fields']['website_id'];
    }
    if (array_key_exists('survey_id', $this->submission['fields'])) {
      if (is_array($this->submission['fields']['survey_id']))
        $this->identifiers['survey_id']=$this->submission['fields']['survey_id']['value'];
      else
        $this->identifiers['survey_id']=$this->submission['fields']['survey_id'];
    }
  }

  /**
   * Wraps the process of submission in a transaction.
   * @return integer If successful, returns the id of the created/found record. If not, returns null - errors are embedded in the model.
   */
  public function submit() { 
    Kohana::log('debug', 'Commencing new transaction.');
    $this->db->query('BEGIN;');
    try {
      $this->errors = array();
      $this->preProcess();
      $res = $this->inner_submit();
      $this->postProcess();
    } catch (Exception $e) {
      $this->errors['general']='<strong>An error occurred</strong><br/>'.$e->getMessage();
      error_logger::log_error('Exception during inner_submit.', $e);
      $res = null;
    }
    if ($res) {
      Kohana::log('debug', 'Committing transaction.');
      $this->db->query('COMMIT;');
    } else {
      Kohana::log('debug', 'Rolling back transaction.');
      kohana::log('debug', var_export($this->getAllErrors(), true));
      $this->db->query('ROLLBACK;');
    }
    return $res;
  }
  
  /**
   * Run preprocessing required before submission.
   */
  private function preProcess() {
    // Initialise the variable which tracks the records we are about to submit.
    self::$changedRecords = array('update'=>array(),'insert'=>array(),'delete'=>array());
  }

  /**
   * Handles any index rebuild requirements as a result of new or updated records, e.g. in
   * samples or occurrences. Also handles joining of occurrence_associations to the
   * correct records
   */
  private function postProcess() {
    if (class_exists('cache_builder')) {
      if (!empty(self::$changedRecords['insert']['occurrence']))
        cache_builder::insert($this->db, 'occurrences', self::$changedRecords['insert']['occurrence']);
      if (!empty(self::$changedRecords['update']['occurrence']))
        cache_builder::update($this->db, 'occurrences', self::$changedRecords['update']['occurrence']);
      if (!empty(self::$changedRecords['delete']['occurrence']))
        cache_builder::delete($this->db, 'occurrences', self::$changedRecords['delete']['occurrence']);
      $samples=array();
      if (!empty(self::$changedRecords['insert']['sample'])) {
        $samples = self::$changedRecords['insert']['sample'];
        cache_builder::insert($this->db, 'samples', self::$changedRecords['insert']['sample']);
      }
      if (!empty(self::$changedRecords['update']['sample'])) {
        $samples += self::$changedRecords['update']['sample'];
        cache_builder::update($this->db, 'samples', self::$changedRecords['update']['sample']);
      }
      if (!empty(self::$changedRecords['delete']['sample']))
        cache_builder::delete($this->db, 'samples', self::$changedRecords['delete']['sample']);
      if (!empty($samples)) {
        postgreSQL::insertMapSquaresForSamples($samples, 1000, $this->db);
        postgreSQL::insertMapSquaresForSamples($samples, 2000, $this->db);
        postgreSQL::insertMapSquaresForSamples($samples, 10000, $this->db);
      } else {
        // might be directly inserting an occurrence. No need to do this if inserting a sample, as the above code does the
        // occurrences in bulk.
        $occurrences=array();
        if (!empty(self::$changedRecords['insert']['occurrence']))
          $occurrences = self::$changedRecords['insert']['occurrence'];
        if (!empty(self::$changedRecords['update']['occurrence']))
          $occurrences += self::$changedRecords['update']['occurrence'];
        if (!empty($occurrences)) {
          postgreSQL::insertMapSquaresForOccurrences($occurrences, 1000, $this->db);
          postgreSQL::insertMapSquaresForOccurrences($occurrences, 2000, $this->db);
          postgreSQL::insertMapSquaresForOccurrences($occurrences, 10000, $this->db);
        }
      }
    }
    if (!empty(self::$changedRecords['insert']['occurrence_association']) ||
        !empty(self::$changedRecords['update']['occurrence_association'])) {
      // We've got some associations between occurrences that could not have the to_occurrence_id
      // foreign key filled in yet, since the occurrence referred to did not exist at the time of
      // saving
      foreach(Occurrence_association_Model::$to_occurrence_id_pointers as $associationId => $pointer) {
        if (!empty($this->dynamicRowIdReferences["occurrence:$pointer"]))
          $this->db->from('occurrence_associations')
              ->set('to_occurrence_id', $this->dynamicRowIdReferences["occurrence:$pointer"])
              ->where('id', $associationId)
              ->update();
      }
    }
  }

  /**
   * Submits the data by:
   * - For each entry in the "supermodels" array, calling the submit function
   *   for that model and linking in the resultant object.
   * - Calling the preSubmit function to clean data.
   * - Linking in any foreign fields specified in the "fk-fields" array.
   * - Checking (by a where clause for all set fields) that an existing
   *   record does not exist. If it does, return that.
   * - Calling the validate method for the "fields" array.
   * If successful, returns the id of the created/found record.
   * If not, returns null - errors are embedded in the model.
   */
  public function inner_submit(){
    $this->wantToUpdateMetadata = true;
    $isInsert = $this->id===0
        && (!isset($this->submission['fields']['id']) || !$this->submission['fields']['id']);
    $this->handleCaptionSubmission();
    $return = $this->populateFkLookups();
    $this->populateIdentifiers();
    $return = $this->createParentRecords() && $return;
    // No point doing any more if the parent records did not post
    if ($return) {
      $this->preSubmit();
      $this->removeUnwantedFields();
      $return = $this->validateAndSubmit();
      $return = $this->checkRequiredAttributes() ? $return : null;
      if ($this->id) {
        // Make sure we got a record to save against before attempting to post children. Post attributes first
        // before child records because the parent (e.g. Sample) attribute values sometimes affect the cached data
        // (e.g. the recorders stored in cache_occurrences)
        $return = $this->createAttributes($isInsert) ? $return : null;
        $return = $this->createChildRecords() ? $return : null;
        $return = $this->createJoinRecords() ? $return : null;

        if ($isInsert)
          $addTo=&self::$changedRecords['insert'];
        elseif (isset($this->deleted) && $this->deleted==='t')
          $addTo=&self::$changedRecords['delete'];
        else
          $addTo=&self::$changedRecords['update'];
        if (!isset($addTo[$this->object_name]))
          $addTo[$this->object_name] = array();
        $addTo[$this->object_name][] = $this->id;
      }
      // Call postSubmit
      if ($return) {
        $ps = $this->postSubmit($isInsert);
        if ($ps == null) {
          $return = null;
        }
      }
      if (kohana::config('config.log_threshold')=='4') {
        kohana::log('debug', 'Done inner submit of model '.$this->object_name.' with result '.$return);
      }
    }
    if (!$return) kohana::log('debug', kohana::debug($this->getAllErrors()));
    return $return;
  }

  /**
   * Remove any fields from the submission that are not in the model and are not custom attributes of the model.
   */
  private function removeUnwantedFields() {
    foreach($this->submission['fields'] as $field => $content) {
      if ( !array_key_exists($field, $this->table_columns) && !(isset($this->attrs_field_prefix) && preg_match('/^'.$this->attrs_field_prefix.'\:/', $field)) )
        unset($this->submission['fields'][$field]);
    }
  }

  /**
   * Actually validate and submit the inner submission.
   *
   * @return int Id of the submitted record, or null if this failed.
   * @throws Exception On access denied to the website of an existing record.
   */
  protected function validateAndSubmit() {
    $return = null;
    $collapseVals = create_function('$arr',
        'if (is_array($arr)) {
           return $arr["value"];
         } else {
           return $arr;
         }');
    // Flatten the array to one that can be validated
    $vArray = array_map($collapseVals, $this->submission['fields']);
    if (!empty($vArray['website_id']) && !empty(self::$authorisedWebsiteId) && $vArray['website_id']!==self::$authorisedWebsiteId)
      throw new Exception('Access to write to this website denied.', 2001);
    // If we're editing an existing record, merge with the existing data.
    // NB id is 0, not null, when creating a new user
    if (array_key_exists('id', $vArray) && $vArray['id'] != null && $vArray['id'] != 0) {
      $this->find($vArray['id']);
      $thisValues = $this->as_array();
      unset($thisValues['updated_by_id']);
      unset($thisValues['updated_on']);
      // don't overwrite existing website_ids otherwise things like shared verification portals end up
      // grabbing records to their own website ID.
      if (!empty($thisValues['website_id']) && !empty($vArray['website_id']))
        unset($vArray['website_id']);
      // If there are no changed fields between the current and new record, skip the metadata update.
      $exactMatches = array_intersect_assoc($thisValues, $vArray);
      // Allow for different ways of submitting bool. Don't want to trigger metadata updates if submitting 'on' instead of true
      // for example.
      foreach ($vArray as $key=>$value) {
        if (isset($this->$key)
            && (($this->$key==='t' && ($value==='on' || $value===1))
            ||  ($this->$key==='f' && ($value==='off' || $value===0))))
          $exactMatches[$key] = $this->$key;
      }
      $fieldsWithValuesInSubmission = array_intersect_key($thisValues, $vArray);
      $this->wantToUpdateMetadata = count($exactMatches)!==count($fieldsWithValuesInSubmission);
      $vArray = array_merge($thisValues, $vArray);
      $this->existing=true;
    }
    Kohana::log("debug", "About to validate the following array in model ".$this->object_name);
    Kohana::log("debug", kohana::debug($this->sanitise($vArray)));
    try {
      if (array_key_exists('deleted', $vArray) && $vArray['deleted']=='t') {
        // For a record deletion, we don't want to validate and save anything. Just mark delete it.
        $this->deleted='t';
        $this->set_metadata();
        $v=$this->save();
      } else {
        // Create a new record by calling the validate method
        $v=$this->validate(new Validation($vArray), true);
      }
    } catch (Exception $e) {
      $v=false;
      $this->errors['general']=$e->getMessage();
      error_logger::log_error('Exception during validation', $e);
    }
    if ($v) {
      // Record has successfully validated so return the id.
      Kohana::log("debug", "Record ".$this->id." has validated successfully");
      $return = $this->id;
    } else {
      // Errors.
      Kohana::log("debug", "Record did not validate");
      // Log more detailed information on why
      foreach ($this->errors as $f => $e) {
        Kohana::log("debug", "Field ".$f.": ".$e);
      }
    }
    return $return;
  }


  /**
   * When a field is present in the model that is an fkField, this means it contains a lookup
   * caption that must be searched for in the fk entity. This method does the searching and
   * puts the fk id back into the main model so when it is saved, it links to the correct fk
   * record.
   * Respects the setting $cacheFkLookups to use the cache if possible.
   *
   * @return boolean True if all lookups populated successfully.
   */
  private function populateFkLookups() {
    $r=true;
    if (array_key_exists('fkFields', $this->submission)) {
      foreach ($this->submission['fkFields'] as $a => $b) {
        if (!empty($b['fkSearchValue'])) {
          // if doing a parent lookup in a list based entity (terms or taxa), then filter to lookup within the list.
          if (isset($this->list_id_field) && $b['fkIdField']==='parent_id' && !isset($b['fkSearchFilterField'])) {
            $b['fkSearchFilterField']=$this->list_id_field;
            $b['fkSearchFilterValue']=$this->submission['fields'][$this->list_id_field]['value'];
          }
          $fk = $this->fkLookup($b);
          if ($fk) {
            $this->submission['fields'][$b['fkIdField']] = array('value' => $fk);
          } else {
            // look for a translation of the field name
            $lookingIn = kohana::lang("default.dd:{$this->object_name}:$a");
            if ($lookingIn === "default.dd:$this->object_name:$a") {
              $fields = $this->getSubmittableFields(FALSE);
              $lookingIn = empty($fields[$this->object_name . ':' . $a]) ?
                $b['readableTableName'] . ' ' . ucwords($b['fkSearchField']) :
                $fields[$this->object_name . ':' . $a];
            }
            $this->errors[$a] = "Could not find \"$b[fkSearchValue]\" in $lookingIn";
            $r=false;
          }
        }
      }
    }
    return $r;
  }

  /**Function to return key of item defined in the fkArr parameter
   * @param array $fkArr Contains definition of item to look up. Contains the following fields
   *  fkTable => table in which to perform lookup
   *  fkSearchField => field in table to search
   *  fkSearchValue => value to find in search field
   *  fkSearchFilterField => field by which to filter search
   *  fkSearchFilterValue => filter value
   *
   * @return Foreign key value or false if not found
   */
  protected function fkLookup($fkArr) {
    $r = false;
    $key = '';
    if (isset($fkArr['fkSearchFilterValue'])) {
    	if(is_array($fkArr['fkSearchFilterValue']))
	    	$filterValue = $fkArr['fkSearchFilterValue']['value'];
    	else
	    	$filterValue = $fkArr['fkSearchFilterValue'];
    } else $filterValue = '';
    
    if (ORM::$cacheFkLookups) {
      $keyArr=array('lookup', $fkArr['fkTable'], $fkArr['fkSearchField'], $fkArr['fkSearchValue']);
      // cache must be unique per filtered value (e.g. when lookup up a taxa in a taxon list).
      if ($filterValue != '')
        $keyArr[] = $filterValue;
      $key = implode('-', $keyArr);
      $r = $this->cache->get($key);
    }

    if (!$r) {
      $where = array($fkArr['fkSearchField'] => $fkArr['fkSearchValue']);
      // does the lookup need to be filtered, e.g. to a taxon or term list?
      if (isset($fkArr['fkSearchFilterField']) && $fkArr['fkSearchFilterField']) {
        $where[$fkArr['fkSearchFilterField']] = $filterValue;
      }
      $matches = $this->db
          ->select('id')
          ->from(inflector::plural($fkArr['fkTable']))
          ->where($where)
          ->limit(1)
          ->get();
      if (count($matches)===0 && $fkArr['fkSearchField']!='id') {
        // try a slower case insensitive search before giving up, but don't bother if id specified as ints don't like ilike
        $this->db
          ->select('id')
          ->from(inflector::plural($fkArr['fkTable']))
          ->where("(".$fkArr['fkSearchField']." ilike '".strtolower(str_replace("'","''",$fkArr['fkSearchValue']))."')");
        if (isset($fkArr['fkSearchFilterField']) && $fkArr['fkSearchFilterField'])
          $this->db->where(array($fkArr['fkSearchFilterField']=>$filterValue));
        $matches = $this->db
          ->limit(1)
          ->get();
      }
      if (count($matches) > 0) {
        $r = $matches[0]->id;
        if (ORM::$cacheFkLookups) {
          $this->cache->set($key, $r, array('lookup'));
        }
      }
    }

    return $r;
  }



  /**
   * Generate any records that this model contains an FK reference to in the
   * Supermodels part of the submission.
   */
  private function createParentRecords() {
    // Iterate through supermodels, calling their submit methods with subarrays
    if (array_key_exists('superModels', $this->submission)) {
      foreach ($this->submission['superModels'] as &$a) {
        // Establish the right model - either an existing one or create a new one
        $id = array_key_exists('id', $a['model']['fields']) ? $a['model']['fields']['id']['value'] : null;
        if ($id) {
          $m = ORM::factory($a['model']['id'], $id);
        } else {
          $m = ORM::factory($a['model']['id']);
        }
        // Don't accidentally delete a parent when deleting a child
        unset($a['model']['fields']['deleted']);
        // Call the submit method for that model and
        // check whether it returns correctly
        $m->submission = $a['model'];
        // copy up the website id and survey id
        $m->identifiers = array_merge($this->identifiers);
        $result = $m->inner_submit();
        $this->nestedParentModelIds[] = $m->get_submission_response_metadata();
        // copy the submission back so we pick up updated foreign keys that have been looked up. E.g. if submitting a taxa taxon list, and the
        // taxon supermodel has an fk lookup, we need to keep it so that it gets copied into common names and synonyms
        $a['model'] = $m->submission;
        if ($result) {
          $this->submission['fields'][$a['fkId']]['value'] = $result;
        } else {
          $fieldPrefix = (array_key_exists('field_prefix',$a['model'])) ? $a['model']['field_prefix'].':' : '';
          foreach($m->errors as $key=>$value) {
            $this->errors[$fieldPrefix.$key]=$value;
          }
          return false;
        }
      }
    }
    return true;
  }

  /**
   * Generate any records that refer to this model in the subModela part of the
   * submission.
   */
  private function createChildRecords() {
    $r=true;
    if (array_key_exists('subModels', $this->submission)) {
      // Iterate through the subModel array, linking them to this model
      foreach ($this->submission['subModels'] as $key => $a) {
        Kohana::log("debug", "Submitting submodel ".$a['model']['id'].".");
        // Establish the right model
        $modelName = $a['model']['id'];
        // alias old images tables to new media tables
        $modelName=preg_replace('/^([a-z_]+)_image$/', '${1}_medium', $modelName);
        $m = ORM::factory($modelName);
        // Set the correct parent key in the subModel
        $fkId = $a['fkId'];
        $a['model']['fields'][$fkId]['value'] = $this->id;
        // copy any request fields
        if(isset($a['copyFields'])){
          foreach($a['copyFields'] as $from => $to){
            Kohana::log("debug", "Setting ".$to." field (from parent record ".$from." field) to value ".$this->$from);
            $a['model']['fields'][$to]['value'] = $this->$from;
          }
        }
        // Call the submit method for that model and
        // check whether it returns correctly
        $m->submission = $a['model'];
        // copy down the website id and survey id
        $m->identifiers = array_merge($this->identifiers);
        $result = $m->inner_submit();
        $this->nestedChildModelIds[] = $m->get_submission_response_metadata();
        if ($m->wantToUpdateMetadata && !$this->wantToUpdateMetadata && preg_match('/_(image|medium)$/', $m->object_name)) {
          // we didn't update the parent's metadata. But a child image has been changed, so we want to update the parent record metadata.
          // i.e. adding an image to a record causes the record to be edited and therefore to get its status reset.
          $this->wantToUpdateMetadata = true;
          $this->set_metadata();
          $this->validate(new Validation($this->as_array()), true);
        }

        if (!$result) {
          $fieldPrefix = (array_key_exists('field_prefix',$a['model'])) ? $a['model']['field_prefix'].':' : '';
          // Remember this model so that its errors can be reported
          foreach($m->errors as $key=>$value) {
            $this->errors[$fieldPrefix.$key]=$value;
          }
          $r=false;
        } elseif (!preg_match('/^\d+$/', $key)) {
          // sub-model list is an associative array. This means there might be references
          // to these keys elsewhere in the submission. Basically dynamic references to
          // rows which don't yet exist.
          $this->dynamicRowIdReferences["$modelName:$key"] = $m->id;
        }
      }
    }
    return $r;
  }

  /**
   * Generate any records that represent joins from this model to another.
   */
  private function createJoinRecords() {
    if (array_key_exists('joinsTo', $this->submission)) {
      foreach($this->submission['joinsTo'] as $model=>$ids) {
        // $ids is now a list of the related ids that should be linked to this model via
        // a join table.
        $table = inflector::plural($model);
        // Get the list of ids that are missing from the current state
        $to_add = array_diff($ids, $this->$table->as_array());
        // Get the list of ids that are currently joined but need to be disconnected
        $to_delete = array_diff($this->$table->as_array(), $ids);
        $joinModel = inflector::singular($this->join_table($table));
        // Remove any joins that are to records that should no longer be joined.
        foreach ($to_delete as $id) {
          // @todo: This could be optimised by not using ORM to do the deletion.
          $delModel = ORM::factory($joinModel,
            array($this->object_name.'_id' => $this->id, $model.'_id' => $id));
          $delModel->delete();
        }
        // And add any new joins
        foreach ($to_add as $id) {
          $addModel = ORM::factory($joinModel);
          $addModel->validate(new Validation(array(
              $this->object_name.'_id' => $this->id, $model.'_id' => $id
          )), true);
        }
      }
      $this->save();
    }
    return true;
  }

  /**
   * Function that iterates through the required attributes of the current model, and
   * ensures that each of them has a submodel in the submission.
   */
  private function checkRequiredAttributes() {
    $r = true;
    $typeFilter = null;
    // Test if this model has an attributes sub-table. Also to have required attributes, we must be posting into a
    // specified survey or website at least.
    if ($this->has_attributes) {
      $got_values=array();
      $empties = array();
      if (isset($this->submission['metaFields'][$this->attrs_submission_name]))
      {
        // Old way of submitting attribute values but still supported - attributes are stored in a metafield. Find the ones we actually have a value for
        // Provided for backwards compatibility only
        foreach ($this->submission['metaFields'][$this->attrs_submission_name]['value'] as $attr) {
          if ($attr['fields']['value']) {
            array_push($got_values, $attr['fields'][$this->object_name.'_attribute_id']);
          }
        }
        // check for location type or sample method which can be used to filter the attributes available
        foreach($this->submission['fields'] as $field => $content)
          // if we have a location type or sample method, we will use it as a filter on the attribute list
          if ($field=='location_type_id' || $field=='sample_method_id')
            $typeFilter = $content['value'];
      } else {
        // New way of submitting attributes embeds attr values direct in the main table submission values.
        foreach($this->submission['fields'] as $field => $content) {
          // look for pattern smpAttr:(fk_)nn (or occAttr, taxAttr, trmAttr, locAttr, srvAttr or psnAttr)
          $isAttribute = preg_match('/^'.$this->attrs_field_prefix.'\:(fk_)?[0-9]+/', $field, $baseAttrName);
          if ($isAttribute) {
            // extract the nn, this is the attribute id
            preg_match('/[0-9]+/', $baseAttrName[0], $attrId);
            if (isset($content['value']) && $content['value'] !== '')
              array_push($got_values, $attrId[0]);
            else {
              // keep track of the empty field names, so we can attach any required validation errors
              // directly to the exact field name
              $empties[$baseAttrName[0]] = $field;
            }
          }
          // if we have a location type or sample method, we will use it as a filter on the attribute list
          if ($field=='location_type_id' || $field=='sample_method_id')
            $typeFilter = $content['value'];
        }
      }
      $fieldPrefix = (array_key_exists('field_prefix',$this->submission)) ? $this->submission['field_prefix'].':' : '';
      // as the required fields list is relatively static, we use the cache. This cache entry gets cleared when
      // a custom attribute is saved so it should always be up to date.
      $key = $this->getRequiredFieldsCacheKey($typeFilter);
      $result = $this->cache->get($key);
      if ($result===null) {
        // setup basic query to get custom attrs.
        $result=$this->getAttributes(true, $typeFilter);
        $this->cache->set($key, $result, array('required-fields'));
      }

      foreach($result as $row) {
        if (!in_array($row->id, $got_values)) {
          // There is a required attr which we don't have a value for the submission for. But if posting an existing occurrence, the
          // value may already exist in the db, so only validate any submitted blank attributes which will be in the empties array and
          // skip any attributes that were not in the submission.
          $fieldname = $fieldPrefix.$this->attrs_field_prefix.':'.$row->id;
          if (empty($this->submission['fields']['id']['value']) || isset($empties[$fieldname])) {
            // map to the exact name of the field if it is available
            if (isset($empties[$fieldname])) $fieldname = $empties[$fieldname];
            $this->errors[$fieldname]='Please specify a value for the '.$row->caption .'.';
            kohana::log('debug', 'No value for '.$row->caption . ' in '.print_r($got_values, true));
            $r=false;
          }
        }
      }
    }
    return $r;
  }

  /**
   * Default implementation of a method which retrieves the cache key required to store the list
   * of required fields. Override when there are other values which define the required fields
   * in the cache, e.g. for people each combination of website IDs defines a cache entry.
   * @param type $typeFilter
   * @return string The cache key.
   */
  protected function getRequiredFieldsCacheKey($typeFilter) {
    $keyArr = array_merge(array('required', $this->object_name), $this->identifiers);
    if ($typeFilter) $keyArr[] = $typeFilter;
    return implode('-', $keyArr);
  }

  /**
   * Gets the list of custom attributes for this model.
   * This is just a default implementation for occurrence & sample attributes which can be
   * overridden if required.
   * @param boolean $required Optional. Set to true to only return required attributes (requires
   * the website and survey identifier to be set).
   * @param int @typeFilter Specify a location type meaning id or a sample method meaning id to
   * filter the returned attributes to those which apply to the given type or method.
   * @param boolean @hasSurveyRestriction true if this objects attributes can be restricted to
   * survey scope.
   */
  protected function getAttributes($required = false, $typeFilter = null, $hasSurveyRestriction = true) {
    if (empty($this->identifiers['website_id']))
      return array();
    $attr_entity = $this->object_name.'_attribute';
    $this->db->select($attr_entity.'s.id', $attr_entity.'s.caption', $attr_entity.'s.data_type');
    $this->db->from($attr_entity.'s');
    $this->db->where($attr_entity.'s.deleted', 'f');
    if (($this->identifiers['website_id'] || $this->identifiers['survey_id']) && $this->db->table_exists($attr_entity.'s_websites')) {
      $this->db->join($attr_entity.'s_websites', $attr_entity.'s_websites.'.$attr_entity.'_id', $attr_entity.'s.id');
      $this->db->where($attr_entity.'s_websites.deleted', 'f');
      if ($this->identifiers['website_id'])
        $this->db->where($attr_entity.'s_websites.website_id', $this->identifiers['website_id']);
      if ($this->identifiers['survey_id'] && $hasSurveyRestriction)
        $this->db->in($attr_entity.'s_websites.restrict_to_survey_id', array($this->identifiers['survey_id'], null));
      // note we concatenate the validation rules to check both global and website specific rules for requiredness.
      if ($required) {
        $this->db->where('('.$attr_entity."s_websites.validation_rules like '%required%' or ".$attr_entity."s.validation_rules like '%required%')");
      }
      // ensure that only attrs for the record's sample method or location type, or unrestricted attrs,
      // are returned
      if ($this->object_name=='location' || $this->object_name=='sample') {
        if ($this->object_name=='location')
          $this->db->join('termlists_terms as tlt', 'tlt.id',
              'location_attributes_websites.restrict_to_location_type_id', 'left');
        elseif ($this->object_name=='sample') {
          $this->db->join('termlists_terms as tlt', 'tlt.id',
              'sample_attributes_websites.restrict_to_sample_method_id', 'left');
        }
        $this->db->join('termlists_terms as tlt2', 'tlt2.meaning_id', 'tlt.meaning_id', 'left');
        $ttlIds = array(null);
        if ($typeFilter)
          $ttlIds[] = $typeFilter;
        $this->db->in('tlt2.id', $ttlIds);
      }
    } elseif ($required) {
      $this->db->like($attr_entity.'s.validation_rules', '%required%');
    }
    $this->db->orderby($attr_entity.'s.caption', 'ASC');
    return $this->db->get()->result_array(true);
  }

  /**
   * Returns an array of fields that this model will take when submitting.
   * By default, this will return the fields of the underlying table, but where
   * supermodels are involved this may be overridden to include those also.
   *
   * When called with true, this will also add fk_ columns for any _id columns
   * in the model unless the column refers to a model in the submission structure
   * supermodels list. For example, when adding an occurrence via import, you supply
   * the fields for the sample to create rather than a lookup value for the existing
   * samples.
   * @param boolean $fk
   * @param integer $website_id If set then custom attributes are limited to those for this website.
   * @param integer $survey_id If set then custom attributes are limited to those for this survey.
   * @param int @attrTypeFilter Specify a location type meaning id or a sample method meaning id to
   * filter the returned attributes to those which apply to the given type or method.
   * @return array The list of submittable field definitions.
   */
  public function getSubmittableFields($fk = false, $website_id=null, $survey_id=null, $attrTypeFilter=null, $use_associations = false) {
    if ($website_id!==null)
      $this->identifiers['website_id']=$website_id;
    if ($survey_id!==null)
      $this->identifiers['survey_id']=$survey_id;
    $fields = $this->getPrefixedColumnsArray($fk);
    $fields = array_merge($fields, $this->additional_csv_fields);
    ksort($fields);
    if ($this->has_attributes) {
      $result = $this->getAttributes(FALSE, $attrTypeFilter);
      foreach ($result as $row) {
        if ($row->data_type == 'L' && $fk) {
          // Lookup lists store a foreign key
          $fieldname = $this->attrs_field_prefix . ':fk_' . $row->id;
        }
        else {
          $fieldname = $this->attrs_field_prefix . ':' . $row->id;
        }
        $fields[$fieldname] = $row->caption;

      }
    }
    $struct = $this->get_submission_structure();
    if (array_key_exists('superModels', $struct)) {
      // currently can only have associations if a single superModel exists.
      if($use_associations && count($struct['superModels'])===1){
      	// duplicate all the existing fields, but rename adding a 2 to model end.
      	$newFields = array();
      	foreach($fields as $name=>$caption){
      		$parts=explode(':',$name);
      		if($parts[0]==$struct['model'] || $parts[0]==$struct['model'].'_image' || $parts[0]==$this->attrs_field_prefix) {
      			$parts[0] .= '_2';
      			$newFields[implode(':',$parts)] = ($caption != '' ? $caption.' (2)' : '');
      		}
      	}
      	$fields = array_merge($fields, ORM::factory($struct['model'].'_association')->getSubmittableFields($fk, $website_id, $survey_id, null, false));
      	$fields = array_merge($fields,$newFields);
      }
      foreach ($struct['superModels'] as $super=>$content) {
        $fields = array_merge($fields, ORM::factory($super)->getSubmittableFields($fk, $website_id, $survey_id, $attrTypeFilter, false));
      }
    }
    if (array_key_exists('metaFields', $struct)) {
      foreach ($struct['metaFields'] as $metaField) {
        $fields["metaFields:$metaField"] = '';
      }
    }
    return $fields;
  }

  /**
   * Retrieves a list of the required fields for this model and its related models.
   * @param <type> $fk
   * @param int $website_id
   * @param int $survey_id
   *
   * @return array List of the fields which are required.
   */
  public function getRequiredFields($fk = false, $website_id=null, $survey_id=null, $use_associations = false) {
    if ($website_id!==null)
      $this->identifiers['website_id']=$website_id;
    if ($website_id!==null)
      $this->identifiers['survey_id']=$survey_id;
    $sub = $this->get_submission_structure();
    $arr = new Validation(array('id'=>1));
    $this->validate($arr, false);
    $fields = array();
    foreach ($arr->errors() as $column=>$error) {
      if ($error=='required') {
        if ($fk && substr($column, -3) == "_id") {
          // don't include the fk link field if the submission is supposed to contain full data
          // for the supermodel record rather than just a link
          if (!isset($sub['superModels'][substr($column, 0, -3)]))
            $fields[] = $this->object_name.":fk_".substr($column, 0, -3);
        } else {
          $fields[] = $this->object_name.":$column";
        }
      }
    }
    if ($this->has_attributes) {
      $result = $this->getAttributes(true);
      foreach($result as $row) {
        $fields[] = $this->attrs_field_prefix.':'.$row->id;
      }
    }

    if (array_key_exists('superModels', $sub)) {
    	// currently can only have associations if a single superModel exists.
    	if($use_associations && count($sub['superModels'])===1){
    		// duplicate all the existing fields, but rename adding a 2 to model end.
    		$newFields = array();
    		foreach($fields as $id){
    			$parts=explode(':',$id);
    			if($parts[0]==$sub['model'] || $parts[0]==$sub['model'].'_image' || $parts[0]==$this->attrs_field_prefix) {
    				$parts[0] .= '_2';
    				$newFields[] = implode(':',$parts);
    			}
    		}
    		$fields = array_merge($fields,$newFields);
    		$fields = array_merge($fields, ORM::factory($sub['model'].'_association')->getRequiredFields($fk, $website_id, $survey_id, false));
    	}
    	 
      foreach ($sub['superModels'] as $super=>$content) {
        $fields = array_merge($fields, ORM::factory($super)->getRequiredFields($fk, $website_id, $survey_id, false));
      }
    }
    return $fields;
  }

  /**
   * Returns the array of values, with each key prefixed by the model name then :.
   *
   * @param string $prefix Optional prefix, only required when overriding the model name
   * being used as a prefix.
   * @return array Prefixed key value pairs.
   */
  public function getPrefixedValuesArray($prefix=null) {
    $r = array();
    if (!$prefix) {
      $prefix=$this->object_name;
    }
    foreach ($this->as_array() as $key=>$val) {
      $r["$prefix:$key"]=$val;
    }
    return $r;
  }

  /**
   * Returns the array of columns, with each column prefixed by the model name then :.
   *
   * @return array Prefixed columns.
   */
  protected function getPrefixedColumnsArray($fk=false, $skipHiddenFields=true) {
    $r = array();
    $prefix=$this->object_name;
    $sub = $this->get_submission_structure();
    foreach ($this->table_columns as $column=>$type) {
      if ($skipHiddenFields && isset($this->hidden_fields) && in_array($column, $this->hidden_fields))
        continue;
      if ($fk && substr($column, -3) == "_id") {
        // don't include the fk link field if the submission is supposed to contain full data
        // for the supermodel record rather than just a link
        if (!isset($sub['superModels'][substr($column, 0, -3)]))
          $r["$prefix:fk_".substr($column, 0, -3)]='';
      } else {
        $r["$prefix:$column"]='';
      }
    }
    return $r;
  }

 /**
  * Create the records for any attributes attached to the current submission.
  * @param bool $isInsert TRUE for when the parent of the attributes is a fresh insert, FALSE for an update.
  * @return bool TRUE if success.
  */
  protected function createAttributes($isInsert) {
    if ($this->has_attributes) {
      // Deprecated submission format attributes are stored in a metafield.
      if (isset($this->submission['metaFields'][$this->attrs_submission_name])) {
        return self::createAttributesFromMetafields();
      } else {
        // loop to find the custom attributes embedded in the table fields
        $multiValueData=array();
        foreach ($this->submission['fields'] as $field => $content) {
          if (preg_match('/^'.$this->attrs_field_prefix.':(fk_)?[\d]+(:([\d]+)?(:[^:]*)?)?$/', $field)) {
            $value = $content['value'];
            // Attribute name is of form tblAttr:attrId:valId:uniqueIdx
            $arr = explode(':', $field);
            $attrId = $arr[1];
            $valueId = count($arr)>2 ? $arr[2] : null;
            $attrDef = self::loadAttrDef($this->object_name, $attrId);
            // If this attribute is a multivalue array, then any existing attributes which are not in the submission for the same attr ID should be removed.
            // We need to keep an array of the multi-value attribute IDs, with a sub-array for the existing values that were included in the submission,
            // so that we can mark-delete the ones that are not in the submission.
            if ($attrDef->multi_value=='t' && count($arr)) {
              if (!isset($multiValueData["attr:$attrId"]))
                $multiValueData["attr:$attrId"]=array('attrId'=>$attrId, 'attrDef'=>$attrDef, 'values'=>array());
              if (is_array($value))
                $multiValueData["attr:$attrId"]['values']=array_merge($multiValueData["attr:$attrId"]['values'], $value);
              else
                $multiValueData["attr:$attrId"]['values'][]=$value;
            }
            if (!$this->createAttributeRecord($attrId, $valueId, $value, $attrDef))
              return false;
          }
        }
        // delete any old values from a mult-value attribute. No need to worry for inserting new records.
        if (!$isInsert && !empty($multiValueData)) {
          // If we did any multivalue updates for existing records, then any attributes whose values were not included in the submission must be removed.
          // We may have more than one multivalue field in the record, each of a different type
          foreach ($multiValueData as $spec) {
            switch ($spec['attrDef']->data_type) {
              case 'I':
              case 'L':
                $vf = 'int_value';
                break;
              case 'F':
                $vf = 'float_value';
                break;
              case 'D':
              case 'V':
                $vf = 'date_start_value';
                break;
              default:
                $vf = 'text_value';
            }
          	$this->db->from($this->object_name.'_attribute_values')->set(array('deleted'=>'t', 'updated_on'=>date("Ymd H:i:s")))
                ->where(array($this->object_name.'_attribute_id'=>$spec['attrId'], $this->object_name.'_id'=>$this->id, 'deleted'=>'f'))
                ->notin($vf, $spec['values'])
                ->update();
          }
        }
      }
    }
    return true;
  }

  /**
   * Up to Indicia v0.4, the custom attributes associated with a submission where held in a sub-structure of the submission
   * called metafields. This code is used to provide backwards compatibility with this submission format.
   */
  protected function createAttributesFromMetafields() {
    foreach ($this->submission['metaFields'][$this->attrs_submission_name]['value'] as $attr)
    {
      $value = $attr['fields']['value'];
      if ($value != '') {
        // work out the *_attribute this is attached to, to figure out the field(s) to store the value in.
        $attrId = $attr['fields'][$this->object_name.'_attribute_id'];
        // If this is an existing attribute value, get the record id to overwrite
        $valueId = (array_key_exists('id', $attr['fields'])) ? $attr['fields']['id'] : null;
        $attrDef = self::loadAttrDef($this->object_name, $attrId);
        if (!$this->createAttributeRecord($attrId, $valueId, $value, $attrDef))
          return false;
      }
    }
    return true;
  }

  protected function createAttributeRecord($attrId, $valueId, $value, $attrDef) {
    // There are particular circumstances when $value is actually an array: when a attribute is multi value,
    // AND has yet to be created, AND is passed in as multiple ***Attr:<n>[] POST variables. This should only happen when
    // the attribute has yet to be created, as after this point the $valueID is filled in and that specific attribute POST variable
    // is no longer multivalue - only one value is stored per attribute value record, though more than one record may exist
    // for a given attribute. There may be others with th same <n> without a $valueID.
    // If attrId = fk_* (e.g. when importing data) then the value is a term whose id needs to be looked up.
    if (is_array($value)){
      if (is_null($valueId)) {
        $retVal = true;
        foreach($value as $singlevalue) { // recurse over array.
          $retVal = $this->createAttributeRecord($attrId, $valueId, $singlevalue, $attrDef) && $retVal;
        }
        return $retVal;
      } else {
        $this->errors['general']='INTERNAL ERROR: multiple values passed in for '.$this->object_name.' '.$valueId.' '.print_r($value, true);
        return false;
      }
    }

    $fk = false;
    $value=trim($value);
    if (substr($attrId, 0, 3) == 'fk_') {
      // value is a term that needs looking up
      $fk = true;
      $attrId = substr($attrId, 3);
    }
    // Create a attribute value, loading the existing value id if it exists, or search for the existing record
    // if not multivalue but no id supplied and not a new record
    // @todo: Optimise attribute saving by using query builder rather than ORM
    if (!empty($this->attrValModels[$this->object_name])) {
      $attrValueModel = $this->attrValModels[$this->object_name];
      $attrValueModel->clear();
      $attrValueModel->wantToUpdateMetadata = TRUE;
    } else {
      $attrValueModel=ORM::factory($this->object_name.'_attribute_value');
      $this->attrValModels[$this->object_name] = $attrValueModel;
    }
    if ($this->existing && (!is_null($valueId)) && (!$attrDef->multi_value=='f'))
      $attrValueModel->where(array($this->object_name.'_attribute_id'=>$attrId, $this->object_name.'_id'=>$this->id))->find();
    if (!$attrValueModel->loaded && !empty($valueId))
      $attrValueModel->find($valueId);

    $oldValues = array_merge($attrValueModel->as_array());
    $dataType = $attrDef->data_type;
    $vf = null;

    $fieldPrefix = (array_key_exists('field_prefix',$this->submission)) ? $this->submission['field_prefix'].':' : '';
    // For attribute value errors, we need to report e.g smpAttr:attrId[:attrValId] as the error key name, not
    // the table and field name as normal.
    $fieldId = $fieldPrefix.$this->attrs_field_prefix.':'.$attrId;
    if ($attrValueModel->id) {
      $fieldId .= ':' . $attrValueModel->id;
    }

    switch ($dataType) {
      case 'T':
        $vf = 'text_value';
        break;
      case 'F':
        $vf = 'float_value';
        break;
      case 'D':
      case 'V':
        // Date
        if (!empty($value)) {
          $vd=vague_date::string_to_vague_date($value);
          if ($vd) {
            $attrValueModel->date_start_value = $vd[0];
            $attrValueModel->date_end_value = $vd[1];
            $attrValueModel->date_type_value = $vd[2];
            kohana::log('debug', "Accepted value $value for attribute $fieldId");
            kohana::log('debug', "  date_start_value=".$attrValueModel->date_start_value);
            kohana::log('debug', "  date_end_value=".$attrValueModel->date_end_value);
            kohana::log('debug', "  date_type_value=".$attrValueModel->date_type_value);
          } else {
            $this->errors[$fieldId] = "Invalid value $value for attribute ".$attrDef->caption;
            kohana::log('debug', "Could not accept value $value into date fields for attribute $fieldId.");
            return false;
          }
        } else {
          $attrValueModel->date_start_value = null;
          $attrValueModel->date_end_value = null;
          $attrValueModel->date_type_value = null;
        }
        break;
      case 'G':
        $vf = 'geom_value';
        break;
      case 'B':
        // Boolean
        $vf = 'int_value';
        if (!empty($value)) {
          $lower = strtolower($value);
          if ($lower == 'false' || $lower == 'f' || $lower == 'no' || $lower == 'n' || $lower == 'off') {
            $value = 0;
          } elseif ($lower == 'true' || $lower == 't' || $lower == 'yes' || $lower == 'y' || $lower == 'on') {
            $value = 1;
          }
        }
        break;
      case 'L':
        // Lookup list
        $vf = 'int_value';
        if (!empty($value) && $fk) {
          // value must be looked up
          $r = $this->fkLookup(array(
            'fkTable' => 'lookup_term',
            'fkSearchField' => 'term',
            'fkSearchValue' => $value,
            'fkSearchFilterField' => 'termlist_id',
            'fkSearchFilterValue' => $attrDef->termlist_id,
          ));
          if ($r) {
            $value = $r;
          } else {
            $this->errors[$fieldId] = "Invalid value $value for attribute ".$attrDef->caption;
            kohana::log('debug', "Could not accept value $value into field $vf  for attribute $fieldId.");
            return false;
          }
        }
        break;
      default:
        // Integer
        $vf = 'int_value';
        break;
    }
    if ($vf != null) {
      $attrValueModel->$vf = $value;
      // Test that ORM accepted the new value - it will reject if the wrong data type for example.
      // Use a string compare to get a proper test but with type tolerance.
      // A wkt geometry gets translated to a proper geom so this will look different - just check it is not empty.
      // A float may loose precision or trailing 0 - just check for small percentage difference
      if ( strcmp($attrValueModel->$vf, $value)===0 ||
          ($dataType === 'G' && !empty($attrValueModel->$vf)) ) {
        kohana::log('debug', "Accepted value $value into field $vf for attribute $fieldId.");
      } else {
        if ( $dataType === 'F' && abs($attrValueModel->$vf - $value) < 0.00001 * $attrValueModel->$vf ) {
          kohana::log('alert', "Lost precision accepting value $value into field $vf for attribute $fieldId. Value=".$attrValueModel->$vf);
        } else {
          $this->errors[$fieldId] = "Invalid value $value for attribute ".$attrDef->caption;
          kohana::log('debug', "Could not accept value $value into field $vf for attribute $fieldId.");
          return false;
        }
      }
    }
    // set metadata
    $exactMatches = array_intersect_assoc($oldValues, $attrValueModel->as_array());
    // which fields do we have in the submission?
    $fieldsWithValuesInSubmission = array_intersect_key($oldValues, $attrValueModel->as_array());
    // Hook to the owning entity (the sample, location, taxa_taxon_list or occurrence)
    $thisFk = $this->object_name.'_id';
    $attrValueModel->$thisFk = $this->id;
    // and hook to the attribute
    $attrFk = $this->object_name.'_attribute_id';
    $attrValueModel->$attrFk = $attrId;
    // we'll update metadata only if at least one of the fields have changed
    $wantToUpdateAttrMetadata = count($exactMatches)!==count($fieldsWithValuesInSubmission);
    if (!$wantToUpdateAttrMetadata)
      $attrValueModel->wantToUpdateMetadata=false;
    try {
      $v=$attrValueModel->validate(new Validation($attrValueModel->as_array()), true);
    } catch (Exception $e) {
      $v=false;
      $this->errors[$fieldId]=$e->getMessage();
      error_logger::log_error('Exception during validation', $e);
    }
    if (!$v) {
      foreach($attrValueModel->errors as $key=>$value) {
        // concatenate the errors if more than one per field.
        $this->errors[$fieldId] = array_key_exists($fieldId, $this->errors) ? $this->errors[$fieldId] . '  ' . $value : $value;
      }
      return false;
    }
    $attrValueModel->save();
    if ($wantToUpdateAttrMetadata && !$this->wantToUpdateMetadata) {
      // we didn't update the parent's metadata. But a custom attribute value has changed, so it makes sense to update it now.
      $this->wantToUpdateMetadata = true;
      $this->set_metadata();
      $this->validate(new Validation($this->as_array()), true);
    }
    $this->nestedChildModelIds[] = $attrValueModel->get_submission_response_metadata();

    return true;
  }

  /**
   * Load the definition of an attribute from the database (cached)
   * @param string $attrTable Attribute type name, e.g. sample or occurrence
   * @param integer $attrId The ID of the attribute
   * @return Object The definition of the attribute.
   * @throws Exception When attribute ID not found.
   */
  protected function loadAttrDef($attrType, $attrId) {
    if (substr($attrId, 0, 3) == 'fk_')
      // an attribute value lookup
      $attrId = substr($attrId, 3);
    $cacheId = 'attrInfo_'.$attrType.'_'.$attrId;
    $this->cache = Cache::instance();
    $attr = $this->cache->get($cacheId);
    if ($attr===null) {
      $attr = $this->db
          ->select('caption','data_type','multi_value','termlist_id','validation_rules')
          ->from($attrType.'_attributes')
          ->where(array('id'=>$attrId))
          ->get()->result_array();
      if (count($attr)===0)
        throw new Exception("Invalid $attrType attribute ID $attrId");
      $this->cache->set($cacheId, $attr[0]);
      return $attr[0];
    } else
      return $attr;
  }

  /**
   * Overrideable function to allow some models to handle additional records created on submission.
   * @param boolean True if this is a new inserted record, false for an update.
   * @return boolean True if successful.
   */
  protected function postSubmit($isInsert) {
    return true;
  }

  /**
   * Accessor for children.
   * @return string The children in this model or an empty string.
   */
  public function getChildren() {
    if (isset($this->ORM_Tree_children)) {
      return $this->ORM_Tree_children;
    } else {
      return '';
    }
  }

  /**
   * Set the submission data for the model using an associative array (normally the
   * form post data). The submission is built as a wrapped structure ready to be
   * saved.
   *
   * @param array $array Associative array of data to submit.
   * @param boolean $fklink
   */
  public function set_submission_data($array, $fklink=false) {
    $this->submission = $this->wrap($array, $fklink);
  }

  /**
  * Wraps a standard $_POST type array into a save array suitable for use in saving
  * records.
  *
  * @param array $array Array to wrap
  * @param bool $fkLink=false Link foreign keys?
  * @return array Wrapped array
  */
  protected function wrap($array, $fkLink = false)
  {
    // share the wrapping library with the client helpers
    require_once(DOCROOT.'client_helpers/submission_builder.php');
    $r = submission_builder::build_submission($array, $this->get_submission_structure());
      // Map fk_* fields to the looked up id
    if ($fkLink) {
      $r = $this->getFkFields($r, $array);
    }
    if (array_key_exists('superModels', $r)) {
      $idx=0;
      foreach ($r['superModels'] as $super) {
        $r['superModels'][$idx]['model'] = $this->getFkFields($super['model'], $array);
        $idx++;
      }
    }
    return $r;
  }

  /**
   * Converts any fk_* fields in a save array into the fkFields structure ready to be looked up.
   * [occ|smp|loc|srv|psn]Attr:fk_* are looked up in createAttributeRecord()
   *
   * @param $submission array Submission containing the foreign key field definitions to convert
   * @param $saveArray array Original form data being wrapped, which can contain filters to operate against the lookup table
   * of the form fkFilter:table:field=value.
   * @return array The submission structure containing the fkFields element.
   */
  public function getFkFields($submission, $saveArray) {
  	if($this->object_name != $submission['id'])
    	$submissionModel = ORM::Factory($submission['id'], -1);
    else $submissionModel = $this;
    
  	foreach ($submission['fields'] as $field=>$value) {
      if (substr($field, 0, 3)=='fk_') {
        // This field is a fk_* field which contains the text caption of a record which we need to lookup.
        // First work out the model to lookup against. The format is fk_{fieldname}(:{search field override})?
        $fieldTokens = explode(':', substr($field,3));
        $fieldName = $fieldTokens[0];
        if (array_key_exists($fieldName, $submissionModel->belongs_to)) {
          $fkTable = $submissionModel->belongs_to[$fieldName];
        } elseif (array_key_exists($fieldName, $submissionModel->has_one)) { // this ignores the ones which are just models in list: the key is used to point to another model
          $fkTable = $submissionModel->has_one[$fieldName];
        } elseif ($submissionModel instanceof ORM_Tree && $fieldName == 'parent') {
          $fkTable = inflector::singular($submissionModel->getChildren());
        } else {
           $fkTable = $fieldName;
        }
        // Create model without initialising, so we can just check the lookup variables
        kohana::log('debug', $fkTable);
        $fkModel = ORM::Factory($fkTable, -1);
        // allow the linked lookup field to override the default model search field
        if (count($fieldTokens)>1)
          $fkModel->search_field = $fieldTokens[1];
        // let the model map the lookup against a view if necessary
        $lookupAgainst = isset($fkModel->lookup_against) ? $fkModel->lookup_against : $fkTable;
        // Generate a foreign key instance
        $submission['fkFields'][$field] = array
        (
          // Foreign key id field is table_id
          'fkIdField' => "$fieldName"."_id",
          'fkTable' => $lookupAgainst,
          'fkSearchField' => $fkModel->search_field,
          'fkSearchValue' => trim($value['value']),
          'readableTableName' => ucfirst(preg_replace('/[\s_]+/', ' ', $fkTable))
        );
        // if the save array defines a filter against the lookup table then also store that.
        // 2 formats: field level or table level : "fkFilter:[fieldname|tablename]:[column]=[value]
        // E.g. a search in the taxa_taxon_list table may want to filter by the taxon list. This is done
        // by adding a value such as fkFilter:taxa_taxon_list:taxon_list_id=2.
        // Search through the save array for a filter value
        foreach ($saveArray as $filterfield=>$filtervalue) {
          if (substr($filterfield, 0, strlen("fkFilter:$fieldName:")) == "fkFilter:$fieldName:" ||
              substr($filterfield, 0, strlen("fkFilter:$fkTable:")) == "fkFilter:$fkTable:") {
        		// found a filter for this field or fkTable. So extract the field name as the 3rd part
        		$arr = explode(':', $filterfield);
        		$submission['fkFields'][$field]['fkSearchFilterField'] = $arr[2];
        		// and remember the value
        		$submission['fkFields'][$field]['fkSearchFilterValue'] = $filtervalue;
            }
        }
        // Alternative location is in the submission array itself:
        // this allows for multiple records with different filters, E.G. when submitting occurrences as associations,
        // may want different taxon lists, will be entered as occurrence<n>:fkFilter:<table>:<field> = <value>
        foreach ($submission['fields'] as $filterfield=>$filtervalue) {
        	if (substr($filterfield, 0, strlen("fkFilter:$fieldName:")) == "fkFilter:$fieldName:" ||
                	substr($filterfield, 0, strlen("fkFilter:$fkTable:")) == "fkFilter:$fkTable:") {
        		// found a filter for this field or fkTable. So extract the field name as the 3rd part
        		$arr = explode(':', $filterfield);
        		$submission['fkFields'][$field]['fkSearchFilterField'] = $arr[2];
        		// and remember the value
        		$submission['fkFields'][$field]['fkSearchFilterValue'] = $filtervalue;
        	}
        }
        
      }
    }
    return $submission;
  }

  /**
   * Returns the structure which defines the relationship between the records that can
   * be submitted when submitting this model. This is the default, which just submits the
   * model and no related records, but it is overrideable to define more complex structures.
   *
   * @return array Submission structure array
   */
  public function get_submission_structure() {
    return array('model'=>$this->object_name);
  }


  /**
   * Overrideable method allowing models to declare any default values for loading into a form
   * on creation of a new record.
   */
  public function getDefaults() {
    return array();
  }

  /**
  * Convert an array of field data (a record) into a sanitised version, with email and password hidden.
  */
  private function sanitise($array) {
    // make a copy of the array
    $r = $array;
    if (array_key_exists('password', $r)) $r['password'] = '********';
    if (array_key_exists('email', $r)) $r['email'] = '********';
    return $r;
  }

  /**
   * Override the ORM clear method to clean up errors and identifier tracking.
   */
  public function clear() {
    parent::clear();
    $this->errors=array();
    $this->identifiers = array('website_id'=>null,'survey_id'=>null);
  }

  /**
   * Method which can be used in a model to add the validation rules required for a set of mandatory spatial fields (sref and system).
   * Although the geom field technically could also be set required here, because the models which call this should automatically
   * generate the geom when it is missing in their preSubmit methods, there is no need to report it as required.
   * @param $validation object The validation object to add rules to.
   * @param string $sref_field The sref field name.
   * @param string $sref_system_field The sref system field name.
   */
  public function add_sref_rules(&$validation, $sref_field, $sref_system_field) {
    $values = $validation->as_array();
    $validation->add_rules($sref_field, 'required');
    $validation->add_rules($sref_system_field, 'required');
    if (!empty($values[$sref_system_field])) {
      $system = $values[$sref_system_field];
      $validation->add_rules($sref_field, "sref[$system]");
      $validation->add_rules($sref_system_field, 'sref_system');
    }
  }

 /**
   * Override the ORM load_type method: modifies float behaviour.
   * Loads a value according to the types defined by the column metadata.
   *
   * @param   string $column Column name
   * @param   mixed $value Value to load
   * @return  mixed
   */
  protected function load_type($column, $value)
  {
    $type = gettype($value);
    if ($type == 'object' OR $type == 'array' OR ! isset($this->table_columns[$column]))
      return $value;

    // Load column data
    $column = $this->table_columns[$column];

    if ($value === NULL AND ! empty($column['null']))
      return $value;

    if ( ! empty($column['binary']) AND ! empty($column['exact']) AND (int) $column['length'] === 1)
    {
      // Use boolean for BINARY(1) fields
      $column['type'] = 'boolean';
    }

    switch ($column['type'])
    {
      case 'int':
        if ($value === '' AND ! empty($column['null']))
        {
          // Forms will only submit strings, so empty integer values must be null
          $value = NULL;
        }
        elseif ((float) $value > PHP_INT_MAX)
        {
          // This number cannot be represented by a PHP integer, so we convert it to a string
          $value = (string) $value;
        }
        else
        {
          $value = (int) $value;
        }
      break;
      case 'float':
        if ($value === '' AND ! empty($column['null']))
        {
          // Forms will only submit strings, so empty float values must be null
          $value = NULL;
        }
        else
        {
          $value = (float) $value;
        }
        break;
      case 'boolean':
        $value = (bool) $value;
      break;
      case 'string':
        $value = (string) $value;
      break;
    }

    return $value;
  }

}