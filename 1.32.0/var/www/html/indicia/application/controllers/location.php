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
 * @package	Core
 * @subpackage Controllers
 * @author	Indicia Team
 * @license	http://www.gnu.org/licenses/gpl.html GPL
 * @link 	http://code.google.com/p/indicia/
 */

 defined('SYSPATH') or die('No direct script access.');

/**
 * Controller providing CRUD access to the locations data.
 *
 * @package	Core
 * @subpackage Controllers
 */
class Location_Controller extends Gridview_Base_Controller {

  public function __construct()
  {
    parent::__construct('location');
    $this->columns = array(
        'id'=>'',
        'website'=>'',
        'name'=>'',
        'code'=>'',
        'type'=>'',
        'centroid_sref'=>'');
    $this->pagetitle = "Locations";

    $this->set_website_access('editor');
  }

  /**
   * Get the list of terms ready for the location types list. 
   */
  protected function prepareOtherViewData($values)
  {    
    return array(
      'type_terms' => $this->get_termlist_terms('indicia:location_types')    
    );   
  }

  /**
   * Check access to the edit page of a location. Locations cannot be edited if not core admin, unless they are linked 
   * to your website(s) or are not linked to anything.
   */
  protected function record_authorised ($id)
  {
    if (!is_null($id) AND !is_null($this->auth_filter))
    {
      $l = ORM::factory('locations_website')->where(array('location_id'=>$id))->in('website_id', $this->auth_filter['values'])->find();
      if ($l->loaded) return true;
      
      $l = ORM::factory('locations_website')->where(array('location_id'=>$id))->find();
      if ($l->loaded) return false;
    }
    return true;
  }
  
  /**
   * You can only access the list of locations if at least an editor of one website.
   */
  protected function page_authorised() {
    return $this->auth->logged_in('CoreAdmin') || $this->auth->has_any_website_access('editor');
  }
  
  /**
   * Retrieves additional values from the model that are required by the edit form.
   * @return array List of additional values required by the form.
   */
  protected function getModelValues() {
    $r = parent::getModelValues();
    // only allow core admin to edit public locations
    if ($this->auth->logged_in('CoreAdmin')) {
      $r['metaFields:disabled_input']='NO';
    } else {
      $r['metaFields:disabled_input']=($this->model->public===true || $this->model->public==='t') ? 'YES' : 'NO';
    }
    $this->loadLocationAttributes($r);
    if ($this->model->parent_id)
      $r['parent:name'] = $this->model->parent->name;
    return $r;      
  }
  
  /**
   * Find the websites this location is linked to so we can load the appropriate 
   * attribute list.
   */
  private function loadLocationAttributes(&$valueArray) {
    $websiteIds = array();
    foreach($this->model->websites as $ws)
      $websiteIds[] = $ws->id;
    $this->loadAttributes($valueArray, array('website_id'=>$websiteIds));
  }

  /**
   * Load default values either when creating a location new or reloading after a validation failure.
   * This adds the custome attributes list to the data available for the view.
   */
  protected function getDefaults() {
    $r = parent::getDefaults();
    // when creating a location, we don't know the websites for the location, so cannot 
    // filter the attribute values available. Therefore they are not displayed until 
    // after the location has been saved. Therefore no need to call loadAttributes.
    // We do when editing after a validation failure though.
    if ($this->model->id!==0) 
      $this->loadLocationAttributes($r);
    else // not an existing record: check if the parent_id has been posted to us., by a "create child"
    {
      if(!isset($r['location:parent_id']) && isset($_POST['parent_id'])) {
        $r['location:parent_id'] = $_POST['parent_id'];
        $parent = ORM::factory('location', $r['location:parent_id']);
        $r['parent:name'] = $parent->name;
      }
    }
    	
    return $r;
  }

  /**
   * Adds the upload csv form to the view (which should then insert it at the bottom of the grid).
   */
  protected function add_upload_shp_form() {
    $this->upload_shp_form = new View('templates/upload_shp');
    $this->upload_shp_form->staticFields = null;
    $this->upload_shp_form->controllerpath = $this->controllerpath;
    $this->view->upload_shp_form = $this->upload_shp_form;
  }

  /**
   * This is the main controller action method for the index page of the grid.
   */
  public function index() {
    parent::index();
    $this->add_upload_shp_form();
  }

  /**
   * Controller action to build the page that allows the user to choose which field is to be
   * used for the location name, and optionally for the name of the parent location.
   * A lot of this is stolen from the csv upload code.
   */
  public function upload_shp() {    
    $sizelimit = kohana::config('indicia.maxUploadSize');
    $_FILES = Validation::factory($_FILES)->add_rules(
        'zip_upload', 'upload::valid', 'upload::required', 'upload::type[zip]', "upload::size[$sizelimit]"
    );
    
    if ($_FILES->validate()) {
      // move the file to the standard upload directory
      $zipTempFile = upload::save('zip_upload');
      $_SESSION['uploaded_zip'] = $zipTempFile;

      // Following helps for files from Macs
      ini_set('auto_detect_line_endings',1);
      $view = new View('location/upload_shp');
      $zip = new ZipArchive;
      $res = $zip->open($zipTempFile);
      if ($res != TRUE) {
        $this->setError('Upload file problem', 'Could not open Zip archive file - possible invalid format.');
        return;
      }
      $directory = $this->create_zip_extract_dir();
      if($directory == false) return;
      if ( ! $zip->extractTo($directory)) {
        $this->setError('Upload file problem', 'Could not extract Zip archive file contents.');
        return;
      }
      $entry = '';
      $dbf = 0;
      $shp = 0;
      for($i = 0; $i < $zip->numFiles; $i++) {
        if(basename($zip->getNameIndex($i)) != basename($zip->getNameIndex($i), '.dbf')) {
          $entry = $zip->getNameIndex($i);
          $dbf++;
        }
        if(basename($zip->getNameIndex($i)) != basename($zip->getNameIndex($i), '.shp')) {
          $shpentry = $zip->getNameIndex($i);
          $shp++;
        }
      }
      if($shp == 0) {
        $this->setError('Upload file problem', 'Zip archive file does not contain a file with a .shp extension.');
        return;
      }
      if($shp > 1) {
        $this->setError('Upload file problem', 'Zip archive file contains more than one file with a .shp extension.');
        return;
      }
      if($dbf == 0) {
        $this->setError('Upload file problem', 'Zip archive file does not contain a file with a .dbf extension.');
        return;
      }
      if($dbf > 1) {
        $this->setError('Upload file problem', 'Zip archive file contains more than one file with a .dbf extension.');
        return;
      }
      if(basename($entry, '.dbf') != basename($shpentry, '.shp')) {
        $this->setError('Upload file problem', '.dbf and .shp files in Zip archive have different names.');
        return;
      }
      $_SESSION['extracted_basefile'] = $directory.basename($entry, '.dbf');
      $zip->close();
      $this->template->title = "Choose details in ".$entry." for ".$this->pagetitle;
      try {
        $dbasedb = dbase_open($directory.$entry, 0);
      } catch (Exception $e) {
        // error handled next
      }
      if ($dbasedb) {
          // read some data ..
          $view->columns = dbase_get_header_info($dbasedb);
          dbase_close($dbasedb);
      } else  {
        $this->setError('Upload file problem', 'Could not open '.$entry.' from Zip archive.');
        return;
      }
      $view->model = $this->model;
      $view->controllerpath = $this->controllerpath;
      $view->systems = kohana::config('sref_notations.sref_notations');
      $this->template->content = $view;
      // Setup a breadcrumb
      $this->page_breadcrumbs[] = html::anchor($this->model->object_name, $this->pagetitle);
      $this->page_breadcrumbs[] = 'Setup SHP File upload';
    } else {
      $errors = $_FILES->errors();
      $error = '';
      foreach ($errors as $key => $val) {
        switch ($val) {
          case 'required': 
            $error .= 'The file failed to upload. It might be larger than the file size limit configured for this server.<br/>';
            break;
          case 'valid': 
            $error .= 'The uploaded file is not valid.<br/>';
            break;
          case 'type': 
            $error .= 'The uploaded file is not a zip file. The Shapefile should be uploaded in a Zip Archive file, which should also contain the .dbf file containing the data for each record.<br/>';
            break;
          case 'size': 
            $error .= 'The upload file is greater than the limit of '.$sizelimit.'b.<br/>';
            break;
          default : $error .= 'An unknown error occurred when checking the upload file.<br/>';
        }
      }
      // TODO: error message needs a back button.
      $this->setError('Upload file problem', $error);
    }
  }

  /**
   * TODO create an AJAX call to give details of what will be done before it is done:
   * ie how many will be created, how many will be updated.
   * /
  public function upload_shp_check() {
  }

  /**
   * Controller action that performs the import of data in an uploaded Shapefile.
   * TODO Sort out how large geometries are displayed in the locations indicia page, and also other non
   * WGS84/OSGB srids.
   * TODO Add identification of record by external code as alternative to name.
   */
  public function upload_shp2() {
    $zipTempFile = $_POST['uploaded_zip'];
    $basefile = $_POST['extracted_basefile'];
    // at this point do I need to extract the zipfile again? will assume at the moment that it is
    // already extracted: TODO make sure the extracted files still exist
    ini_set('auto_detect_line_endings',1);
    $view = new View('location/upload_shp2');
    $view->update = Array();
    $view->create = Array();
    $view->location_id = Array();
    // create the file pointer, plus one for errors
    $count=0;
    $this->template->title = "Confirm Shapefile upload for ".$this->pagetitle;
    $dbasedb = dbase_open($basefile.'.dbf', 0);
    if(!array_key_exists('name', $_POST))  {
      $this->setError('Upload problem', 'Name column in .dbf file must be specified.');
      return;
    }
    if(array_key_exists('use_parent', $_POST) && !array_key_exists('parent', $_POST))  {
      $this->setError('Upload problem', 'Parent column in .dbf file must be specified.');
      return;
    }
    if ($dbasedb) {
      // read some data ..
      $record_numbers = dbase_numrecords($dbasedb);
      $handle = fopen($basefile.'.shp', "rb");
      //Don't care about file header: jump direct to records.
      fseek($handle, 100, SEEK_SET);

      for ($i = 1; $i <= $record_numbers; $i++) {
        $row = dbase_get_record_with_names($dbasedb, $i);
        $location_name = $_POST['prepend'].trim(utf8_encode($row[$_POST['name']]));
        $this->loadFromFile($handle);

        if(kohana::config('sref_notations.internal_srid') != $_POST['srid']) {
          //convert to internal srid. First convert +/-90 to a value just off, as Google Maps doesn't cope with the poles!
          $this->wkt = str_replace(
              array(' 90,', ' -90,', ' 90)', ' -90)'), 
              array(' 89.99999999,', ' -89.99999999,', ' 89.99999999)', ' -89.99999999)'), 
              $this->wkt
          );
          $result = $this->db->query("SELECT ST_asText(ST_Transform(ST_GeomFromText('".$this->wkt."',".$_POST['srid']."),".
          kohana::config('sref_notations.internal_srid').")) AS wkt;")->current();
          $this->wkt = $result->wkt;
        }

        if(array_key_exists('use_parent', $_POST)) {
          //Ensure parent already exists and is unique  - no account of website taken...
          $parent = trim($row[$_POST['parent']]);
          $parentSelector = array_key_exists('use_parent_code', $_POST) ? 'code' : 'name';
          $parent_locations = $this->findLocations(array($parentSelector => $parent));
          if(count($parent_locations) == 0) {
            $this->setError('Upload problem', "Could not find non deleted parent where $parentSelector = $parent");
            return;
          }
          if(count($parent_locations) > 1) {
            $this->setError('Upload problem', "Found more than one non deleted parent where $parentSelector = $parent");
            return;
          }
          $parent_id = $parent_locations[0]->id;
        }

        if (isset($parent_id)) {
          //Where there is a parent, look for existing child location with same name - no account of website taken...
          $my_locations=ORM::factory('location')->where('name', $location_name)->where('parent_id', $parent_id)->where('deleted', 'false')->find_all();
          if(count($my_locations) > 1) {
            $this->setError('Upload problem', 'Found '.count($my_locations).' non deleted children where name = '.$location_name." and parent $parentSelector = $parent");
            return;
          }
          $myLocation = ORM::factory('location', array('name' => $location_name, 'parent_id' => $parent_id, 'deleted' => 'false'));
        } else {
          $my_locations_args = array('name' => $location_name);
          if(array_key_exists('type', $_POST))
            $my_locations_args['location_type_id'] = $_POST['type'];
          $my_locations = $this->findLocations($my_locations_args);
          if(count($my_locations) > 1) {
            $this->setError('Upload problem', 'Found more than one location where name = '.$location_name);
            return;
          } elseif (count($my_locations)===1) {
            $myLocation = ORM::factory('location', $my_locations[0]->id);
          } else {
            $myLocation = ORM::factory('location');
          }
        }

        if ($myLocation->loaded){
          // update existing record
          if(array_key_exists('boundary', $_POST)) {
            $myLocation->__set('boundary_geom', $this->wkt);
            $myLocation->setCentroid(isset($_POST['use_sref_system']) && $_POST['use_sref_system'] && isset($_POST['srid']) && $_POST['srid'] != '' ? $_POST['srid'] : '4326');
          } else {
            $myLocation->__set('centroid_geom', $this->wkt);
            $myLocation->__set('centroid_sref', $this->firstPoint);
            $myLocation->__set('centroid_sref_system', $_POST['srid']);
          }
          $myLocation->save();
          $description = $location_name.(isset($parent) ? ' - parent '.$parent : '');
          $view->update[] = $description;
          $view->location_id[$description] = $myLocation->id;
        } else {
          // create a new record
          $fields = array('name' => array('value' => $location_name)
                        ,'deleted' => array('value' => 'f')
                        ,'public' => array('value' => ($_POST['website_id'] == 'all' ? 't' : 'f')));
          if(array_key_exists('boundary', $_POST)) {
            //centroid is calculated in Location_Model::preSubmit
            $fields['boundary_geom'] = array('value' => $this->wkt);
            if(isset($_POST['use_sref_system']) && $_POST['use_sref_system'] && isset($_POST['srid']) && $_POST['srid'] != '')
              $fields['centroid_sref_system'] = array('value' => $_POST['srid']);
          } else {
            $fields['centroid_geom'] = array('value' => $this->wkt);
            $fields['centroid_sref'] = array('value' => $this->firstPoint);
            $fields['centroid_sref_system'] = array('value' => $_POST['srid']);
          }
          if(array_key_exists('use_parent', $_POST))
            $fields['parent_id'] = array('value' => $parent_id);
          if(array_key_exists('code', $_POST))
            $fields['code'] =  array('value' => trim($row[$_POST['code']]));
          if(array_key_exists('type', $_POST))
            $fields['location_type_id'] = array('value' => $_POST['type']);

          $save_array = array(
              'id' => $myLocation->object_name
              ,'fields' => $fields
              ,'fkFields' => array()
              ,'superModels' => array());
          if ($_POST['website_id'] != 'all') 
            $save_array['joinsTo']=array('website'=>array($_POST['website_id']));
          $myLocation->submission = $save_array;
          $myLocation->submit();

          $description = $location_name.(isset($parent) ? ' - parent '.$parent : '');
          $view->create[] = $description;
          $view->location_id[$description] = $myLocation->id;
        }
      }
      fclose($handle);
      dbase_close($dbasedb);
    }
    kohana::log('debug', 'locations import done');
    $view->model = $this->model;
    $view->controllerpath = $this->controllerpath;
    $this->template->content = $view;
    $this->page_breadcrumbs[] = html::anchor($this->model->object_name, $this->pagetitle);
    $this->page_breadcrumbs[] = 'Setup SHP File upload';
  }
  
  function loadData($type, $data)
  {
    if (!$data) return $data;
    $tmp = unpack($type, $data);
    return current($tmp);
  }
  
  /**
   * Finds a list of locations that match a field against a value. Filtered by website if appropriate.
   * @param string $name Field name to filter on.
   * @param string $value Value to filter for.
   * @return array Array of matched locations.
   */
  function findLocations($fields) {
    //Where there is no parent, look for existing location attached to chosen website
    $query = $this->db
        ->select('locations.id')
        ->from('locations');
    if ($_POST['website_id'] == 'all') {
      $query = $query->where('locations.public', 'true');
    } else {
      $query = $query->join('locations_websites', 'locations_websites.location_id', 'locations.id')
        ->where('locations_websites.deleted', 'false')
        ->where('locations_websites.website_id', $_POST['website_id']);
    }
    foreach($fields as $field=>$value){
      $query = $query->where("locations.$field", $value);
    }
    return $query->where('locations.deleted', 'false')->get()->result_array(TRUE);
  }

  function loadStoreHeaders($handle)
  {
      $this->recordNumber = $this->loadData("N", fread($this->SHPFile, 4));
      $this->recordLength = $this->loadData("N", fread($this->SHPFile, 4)); //We read the length of the record: NB this ignores the header
      $this->recordStart =  ftell($this->SHPFile);
      $this->shapeType = $this->loadData("V", fread($this->SHPFile, 4));
  }

  private function loadFromFile($handle)
  {
      $this->SHPFile = $handle;
      $this->loadStoreHeaders($handle);
      $this->firstPoint = "";
      switch ($this->shapeType) {
          case 0:
              $this->loadFromFile($handle);
              break;
          case 1:
              $this->loadPointRecord();
              break;
          case 3:
              $this->loadPolyLineRecord('MULTILINESTRING');
              break;
          case 5:
              $this->loadPolyLineRecord('POLYGON');
              break;
          case 13:
              $this->loadPolyLineZRecord('MULTILINESTRING');
              break;
          case 15:
              $this->loadPolyLineZRecord('POLYGON'); // we discard the Z data.
              break;
          default:
              throw new exception('ShapeType ' . $this->shapeType . ' not supported');
      }
  }

  function loadPoint()
  {
      $x1 = $this->loadData("d", fread($this->SHPFile, 8));
      $y1 = $this->loadData("d", fread($this->SHPFile, 8));
      $data = "$x1 $y1";
      if($this->firstPoint == "") $this->firstPoint = "$x1".Kohana::lang('misc.x_y_separator')." $y1";
      return $data;
  }

  function loadPointRecord()
  {
      $data = $this->loadPoint();
      $this->wkt = 'POINT('.$data.')';
  }

  function loadPolyLineRecord($title)
  {
      $this->SHPData = array();
      $this->loadData("d", fread($this->SHPFile, 8)); // xmin
      $this->loadData("d", fread($this->SHPFile, 8)); // ymin
      $this->loadData("d", fread($this->SHPFile, 8)); // xmax
      $this->loadData("d", fread($this->SHPFile, 8)); // ymax

      $this->SHPData["numparts"] = $this->loadData("V", fread($this->SHPFile, 4));
      $this->SHPData["numpoints"] = $this->loadData("V", fread($this->SHPFile, 4));

      for ($i = 0; $i < $this->SHPData["numparts"]; $i++) {
          $this->SHPData["parts"][$i] = $this->loadData("V", fread($this->SHPFile, 4));
      }

      $this->wkt = $title.'(';
      $firstIndex = ftell($this->SHPFile);
      $readPoints = 0;
      while (list($partIndex, $partData) = each($this->SHPData["parts"])) {
          if (!isset($this->SHPData["parts"][$partIndex]["pointString"]) || !is_array($this->SHPData["parts"][$partIndex]["pointString"])) {
              $this->SHPData["parts"][$partIndex] = array();
              $this->SHPData["parts"][$partIndex]["pointString"] = "";
          }
          while (!in_array($readPoints, $this->SHPData["parts"]) && ($readPoints < ($this->SHPData["numpoints"])) && !feof($this->SHPFile)) {
              $data = $this->loadPoint();
              $this->SHPData["parts"][$partIndex]["pointString"] .= ($this->SHPData["parts"][$partIndex]["pointString"] == "" ? "" : ', ').$data;
              $readPoints++;
          }
          $this->wkt .= ($partIndex == 0 ? "" : ",").'('.$this->SHPData["parts"][$partIndex]["pointString"].')';
      }

      $this->wkt .= ')';
      // Seek to the exact end of this record
    fseek($this->SHPFile, $this->recordStart + ($this->recordLength * 2));
  }

  /**
   * Read a PolyLineZ record. This is the same as a PolyLine for our purposes since we do not hold Z data.
   */
  private function loadPolyLineZRecord($title)
  {
    $this->loadPolyLineRecord($title);
    // According to the spec there are 2 sets of minima and maxima, plus 2 arrays of values * numpoints, that we skip, but since each
    // record's length is read and used to find the next record, this does not matter.
  }


  public function children($id) {
    $parentLocation = ORM::factory('location', $id);
    $this->base_filter['parent_id'] = $id;
    parent::index();
    // pass the parent id into the view, so the create button can use it to autoset
    // the parent of the new list.
    $this->view->parent_id=$id;
    $this->view->upload_csv_form ="";
    $this->view->upload_shp_form ="";
  }

    /**
   * Return a list of the tabs to display for this controller's actions.
   */
  protected function getTabs($name) {
    return array(
      array(
        'controller' => 'location/children',
        'title' => 'Child Locations',
        'actions'=>array('edit')
      ), array(
        'controller' => 'sample/index_from_location',
        'title' => 'Samples',
        'actions'=>array('edit')
      ), array(
        'controller' => 'location_medium',
        'title' => 'Media Files',
        'actions'=>array('edit')
      )
    );
  }
}