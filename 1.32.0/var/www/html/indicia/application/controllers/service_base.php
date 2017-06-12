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
 
/**
 * Exception class for Indicia services.
 *
 * @package	Core
 * @subpackage Controllers
 */
class ServiceError extends Exception {
}

/**
 * Exception class for exception that contain an array of sub-errors, such as a submission validation failure.
 *
 * @package	Core
 */
class ArrayException extends ServiceError {

  private $errors = array();

  /**
   * Override constructor to accept an errors array
   */
  public function __construct($message, $code, $errors) {
    $this->errors = $errors;
    // make sure everything is assigned properly
    parent::__construct($message, $code);
  }

  public function errors() {
    return $this->errors;
  }
}

/**
 * Exception class for submission validation problems.
 * 
 * @package Core
 * @subpackage Controllers
 */
class ValidationError extends ArrayException {
}

/**
 * Exception class for authentication failures.
 * 
 * @package Core
 * @subpackage Controllers
 */
class AuthenticationError extends ServiceError {
}

/**
 * Exception class for authorisation failures.
 * 
 * @package Core
 * @subpackage Controllers
 */
class AuthorisationError extends ServiceError {
}

/**
 * Exception class for inaccessible entities or view combinations.
 * 
 * @package Core
 * @subpackage Controllers
 */
class EntityAccessError extends ServiceError {
}

/**
 * Base controller class for Indicia Service controllers.
 *
 * @package	Core
 * @subpackage Controllers
 */
class Service_Base_Controller extends Controller {

  /**
   * @var boolean Defines if the user is logged into the warehouse.
   */
  protected $in_warehouse = false;

  /**
   * @var int Id of the website calling the service. Obtained when performing read authentication and used
   * to filter the response. A value of 0 indicates the warehouse.
   */
  protected $website_id = null;

  /**
   * @var int Id of the indicia user ID calling the service. Obtained when performing read authentication and can be used
   * to filter the response. Null if not provided in the report call.
   */
  protected $user_id = null;

  /**
   * @var boolean Flag set to true when user has core admin rights. Only applies when the request originates from the warehouse.
   */
  protected $user_is_core_admin = false;

  /**
  * Before a request is accepted, this method ensures that the POST data contains the
  * correct digest token so we know the request was from the website.
  *
  * @param string $mode Whether the authentication token is required to have read or write access.
  * Possible values are 'read' and 'write'. Defaults to 'write'.
  */
  protected function authenticate($mode = 'write')
  {
    // Read calls are done using get values, so we merge the two arrays
    $array = array_merge($_POST, $_GET);
    $authentic = FALSE; // default
    if (array_key_exists('nonce', $array) && array_key_exists('auth_token',$array))
    {
      $nonce = $array['nonce'];
      $this->cache = new Cache;
      // get all cache entries that match this nonce
      $paths = $this->cache->exists($nonce);
      foreach($paths as $path) {
        // Find the parts of each file name, which is the cache entry ID, then the mode.
        $tokens = explode('~', basename($path));
        // check this cached nonce is for the correct read or write operation.
        if ($mode == $tokens[1]) {
          $id = $this->cache->get($tokens[0]);
          if ($id>0) {
            // normal state, the ID is positive, which means we are authenticating a remote website
            $website = ORM::factory('website', $id);
            if ($website->id)
              $password = $website->password;
          } else
            $password = kohana::config('indicia.private_key');
          // calculate the auth token from the nonce and the password. Does it match the request's auth token?
          if (isset($password) && sha1("$nonce:$password")==$array['auth_token']) {
            Kohana::log('info', "Authentication successful.");
            // cache website_password for subsequent use by controllers
            $this->website_password = $password;
            $authentic=true;
          }
          if ($authentic) {
            if ($id>0) {
              $this->website_id = $id;
              if (isset($_REQUEST['user_id']) && $_REQUEST['user_id']) {
                $this->user_id=$_REQUEST['user_id'];
                // if the request included a user ID, put it in the global var so all ORM saves can use it
                global $remoteUserId;
                $remoteUserId = $this->user_id;
              }
            } else {
              $this->in_warehouse = true;
              $this->website_id = 0; // the Warehouse
              $this->user_id = 0 - $id; // user id was passed as a negative number to differentiate from a website id
              // get a list of the websites this user can see
              $user = ORM::Factory('user', $this->user_id);
              $this->user_is_core_admin=($user->core_role_id===1);
              if (!$this->user_is_core_admin) {
                $this->user_websites = array();
                $userWebsites = ORM::Factory('users_website')->where(array('user_id'=>$this->user_id, 'site_role_id is not'=>null, 'banned'=>'f'))->find_all();
                foreach ($userWebsites as $userWebsite)
                  $this->user_websites[] = $userWebsite->website_id;
              }
            }
            // reset the nonce if requested. Doing it here will mean only gets reset if not already timed out.
            if(array_key_exists('reset_timeout', $array) && $array['reset_timeout']=='true') {
              Kohana::log('info', "Nonce timeout reset.");
              $this->cache->set($nonce, $id, $mode);
            }
          }
        }
      }
    } else {
      $auth = new Auth();
      $authentic = ($auth->logged_in() || $auth->auto_login());
      $this->in_warehouse = $authentic;
      $this->user_is_core_admin = $auth->logged_in('CoreAdmin');
    }

    if (!$authentic)
    {
      Kohana::log('info', "Unable to authenticate.");
      throw new AuthenticationError("unauthorised", 1);
    };
  }

  /**
   * Set the content type and then issue the response.
   */
  protected function send_response()
  {
    // last thing we do is set the output
    if (isset($this->content_type))
    {
      header($this->content_type);
    }
    echo $this->response;
  }


  /**
   * Return an error XML or json document to the client
   * @param string Id of the transaction calling the service. Optional.
   * Returned to the calling code.
   */
  protected function handle_error($e, $transaction_id = null)
  {
    if($e instanceof ValidationError || $e instanceof InvalidArgumentException)
      $statusCode = 400;
    elseif($e instanceof AuthenticationError || $e instanceof AuthorisationError) 
      $statusCode = 403; // not 401 as not using browser or official digest authentication
    elseif($e instanceof EntityAccessError)
      $statusCode = 404;
    else
      $statusCode = 500;
    $message=kohana::lang('general_errors.'.$e->getMessage());
    $mode = $this->get_output_mode();
    // Set the HTTP response code only if configured to do so and not JSONP. JSONP will need
    // to check the response error instead.
    if (kohana::config('indicia.http_status_responses')===true && empty($_GET['callback']))
      header(' ', true, $statusCode);
    if ($mode=='xml') {
      $view = new View("services/error");
      $view->message = $message;
      $view->code = $e->getCode();
      $view->render(true);
    } else {
      header("Content-Type: application/json");
      $response = array(
        'error'=>$message,
        'code'=>$e->getCode()
      );
      if ($transaction_id) {
      	$response['transaction_id'] = $transaction_id;
      }
      if ($e instanceof ArrayException) {
        $response['errors'] = $e->errors();
      } elseif (!$e instanceof ServiceError) {
        $response['file']=$e->getFile();
        $response['line']=$e->getLine();
        $response['trace']=array();
      }
      $a = json_encode($response);
      if (array_key_exists('callback', $_GET))
      {
        $a = $_GET['callback']."(".$a.")";
      }
      echo $a;
    }
  }


  /**
   * Retrieve the output mode for a RESTful request from the GET or POST data.
   * Defaults to json. Other options are xml and csv, or a view loaded from the views folder.
   */
  protected function get_output_mode() {
    if (isset($_REQUEST['mode']))
      return $_REQUEST['mode'];
    else
      return 'json';
  }

  /**
   * Retrieve the input mode for a RESTful request from the POST data.
   * Defaults to json. Other options not yet implemented.
   */
  protected function get_input_mode() {
    if (array_key_exists('mode', $_POST)){
      $result = $_POST['mode'];
    } else {
      $result = 'json';
    }
    return $result;
  }

}