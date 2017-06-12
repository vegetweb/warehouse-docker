<?php

require_once 'client_helpers/report_helper.php';

class Controllers_Services_Report_Test extends Indicia_DatabaseTestCase {

  protected $auth;

  /**
   * List of featured reports to get tested. Each test has a parameters array
   * plus an expected result, either an integer number of records, or 'params'
   * if a parameters request is expected.
   * @var array
   */
  private $featuredReports = array(
    array(
      'path' => 'library/months/filterable_occurrence_counts',
      'tests' => array(
        array(
          'params' => array(),
          'result' => 12,
          'valueChecks' => array(
            array(
              'row' => 4,
              'field' => 'month_no',
              'value' => 5 // row is zero indexed, month number starts at 1.
            )
          )
        )
      )
    ),
    array(
      'path' => 'library/months/filterable_species_counts',
      'tests' => array(
        array(
          'params' => array(),
          'result' => 12
        )
      )
    ),
    array(
      'path' => 'library/occurrence_images/filterable_explore_list',
      'tests' => array(
        array(
          'params' => array(),
          'result' => 0
        )
      )
    ),
    array(
      'path' => 'library/occurrences/filterable_explore_list',
      'tests' => array(
        array(
          'params' => array(),
          'result' => 1,
          'valueChecks' => array(
            array(
              'row' => 0,
              'field' => 'occurrence_id',
              'value' => 1 // Check the first record returned which is not confidential
            )
          )
        ),
        array(
          'params' => array('confidential' => 't'),
          'result' => 1,
          'valueChecks' => array(
            array(
              'row' => 0,
              'field' => 'occurrence_id',
              'value' => 2 // check the 2nd confidential record returned
            )
          )
        ),
        array(
          'params' => array('confidential' => 'all'),
          'result' => 2 // include both records
        )
      )
    ),
    array(
      'path' => 'library/occurrences/filterable_explore_list_mapping',
      'tests' => array(
        array(
          'params' => array(),
          'result' => 1 // single grid square in test data
        ),
        array(
          'params' => array('date_from' => '2017-04-01'),
          'result' => 0 // the sample is older than the above date
        )
      )
    ),
    array(
      'path' => 'library/occurrences/filterable_explore_list_mapping_lores',
      'tests' => array(
        array(
          'params' => array(),
          'result' => 'parameterRequest'
        ),
        array(
          'params' => array('sq_size' => '10000'),
          'result' => 1 // single grid square in test data
        ),
        array(
          'params' => array('sq_size' => '10000', 'date_from' => '2017-04-01'),
          'result' => 0 // the sample is older than the above date
        )
      )
    ),
    array(
      'path' => 'library/occurrences/filterable_occurrences_download_without_locality',
      'tests' => array(
        array(
          'params' => array(),
          'result' => 1,
          'valueChecks' => array(
            array(
              'row' => 0,
              'field' => 'occurrence_id',
              'value' => 1 // Check the first record returned which is not confidential
            )
          )
        )
      )
    ),
    array(
      'path' => 'library/surveys/filterable_surveys_verification_breakdown',
      'tests' => array(
        array(
          'params' => array(),
          'result' => 1,
          'valueChecks' => array(
            array(
              'row' => 0,
              'field' => 'pending',
              'value' => 1 // Single 'C' record in test data
            )
          )
        )
      )
    ),
    array(
      'path' => 'library/surveys/surveys_list',
      'tests' => array(
        array(
          'params' => array(),
          'result' => 1,
          'valueChecks' => array(
            array(
              'row' => 0,
              'field' => 'title',
              'value' => 'Test survey'
            )
          )
        )
      )
    ),
    array(
      'path' => 'library/taxa/filterable_explore_list',
      'tests' => array(
        array(
          'params' => array(),
          'result' => 1, // only 1 taxon has records attached
          'valueChecks' => array(
            array(
              'row' => 0,
              'field' => 'taxon',
              'value' => 'Test taxon'
            )
          )
        )
      )
    ),
    array(
      'path' => 'library/taxa/search',
      'tests' => array(
        array(
          'params' => array('searchterm' => '%2'),
          'result' => 1,
          'valueChecks' => array(
            array(
              'row' => 0,
              'field' => 'taxon',
              'value' => 'Test taxon 2'
            )
          )
        )
      )
    ),
    array(
      'path' => 'library/terms/search',
      'tests' => array(
        array(
          'params' => array('term' => 'something not found'),
          'result' => 0
        )
      )
    ),
    array(
      'path' => 'library/terms/search',
      'tests' => array(
        array(
          'params' => array('termlist_id' => 4, 'term' => 'e'),
          'result' => 1,
          array(
            'row' => 0,
            'field' => 'term',
            'value' => 'email'
          )
        )
      )
    )
  );

  public function getDataSet()
  {
    $ds1 =  new PHPUnit_Extensions_Database_DataSet_YamlDataSet('modules/phpUnit/config/core_fixture.yaml');
    return $ds1;
  }

  public static function setUpBeforeClass() {
    // The indicia_report_user is used when querying for reports and needs
    // adequate permissions to work. These cannot be established until
    // the application has created the schema.
    $db = new Database();
    $db->query('GRANT USAGE ON SCHEMA indicia TO indicia_report_user;');
    $db->query('ALTER DEFAULT PRIVILEGES IN SCHEMA indicia GRANT SELECT ON TABLES TO indicia_report_user;');
    $db->query('GRANT SELECT ON ALL TABLES IN SCHEMA indicia TO indicia_report_user;');
  }

  public function setup() {
    // Calling parent::setUp() will build the database fixture.
    parent::setUp();
    
    $this->auth = report_helper::get_read_write_auth(1, 'password');
    // make the tokens re-usable
    $this->auth['write_tokens']['persist_auth']=true;    
  }
  
  private function getResponse($url, $post = FALSE, $params = array()) {
    Kohana::log('debug', "Making request to $url");
    $session = curl_init();
    curl_setopt ($session, CURLOPT_URL, $url);
    curl_setopt($session, CURLOPT_HEADER, false);
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
    if ($post) {
      Kohana::log('debug', "with params " . print_r($params, TRUE));
      curl_setopt ($session, CURLOPT_POST, true);
      curl_setopt ($session, CURLOPT_POSTFIELDS, $params);
    }
    $response = curl_exec($session);
    Kohana::log('debug', "Received response " . print_r($response, TRUE));
    return $response;
  }
  
  public function testRequestReportGetJson() {
    Kohana::log('debug', "Running unit test, Controllers_Services_Report_Test::testRequestReportGetJson");
    $params = array(
      'report' => 'library/websites/species_and_occurrence_counts.xml',
      'reportSource' => 'local',
      'mode' => 'json',
      'auth_token' => $this->auth['read']['auth_token'],
      'nonce' => $this->auth['read']['nonce']
    );
    $url = report_helper::$base_url.'index.php/services/report/requestReport?'.http_build_query($params, '', '&');
    $response = self::getResponse($url);
    // valid json response will decode
    $response = json_decode($response, true);
    $this->assertFalse(isset($response['error']), "testRequestReportGetJson returned error. See log for details");
    $this->assertNotCount(0, $response, "Database contains no records to report on");
    $this->assertTrue(isset($response[0]['title']), 'Report get JSON response not as expected');
  }
  
  public function testRequestReportPostJson() {
    Kohana::log('debug', "Running unit test, Controllers_Services_Report_Test::testRequestReportPostJson");
    $params = array(
      'report' => 'library/websites/species_and_occurrence_counts.xml',
      'reportSource' => 'local',
      'mode' => 'json',
      'auth_token' => $this->auth['read']['auth_token'],
      'nonce' => $this->auth['read']['nonce']
    );
    $url = report_helper::$base_url.'index.php/services/report/requestReport';
    $response = self::getResponse($url, TRUE, $params);
    // valid json response will decode
    $response = json_decode($response, true);
    $this->assertFalse(isset($response['error']), "testRequestReportPostJson returned error. See log for details");
    $this->assertTrue(isset($response[0]['title']), 'Report post JSON response not as expected');
  }
  
  public function testRequestReportGetXML() {
    Kohana::log('debug', "Running unit test, Controllers_Services_Report_Test::testRequestReportGetXML");
    $params = array(
      'report' => 'library/websites/species_and_occurrence_counts.xml',
      'reportSource' => 'local',
      'mode' => 'xml',
      'auth_token' => $this->auth['read']['auth_token'],
      'nonce' => $this->auth['read']['nonce']
    );
    $url = report_helper::$base_url.'index.php/services/report/requestReport?'.http_build_query($params, '', '&');
    $response = self::getResponse($url);
    // valid xml response will decode
    $response = new SimpleXmlElement($response, true);    
    $this->assertFalse(isset($response->error), "testRequestReportGetXML returned error. See log for details");
    $this->assertTrue(isset($response->record[0]->title), 'Report get XML response not as expected');
  }
  
  public function testRequestReportPostXML() {
    Kohana::log('debug', "Running unit test, Controllers_Services_Report_Test::testRequestReportPostXML");
    $params = array(
      'report' => 'library/websites/species_and_occurrence_counts.xml',
      'reportSource' => 'local',
      'mode' => 'xml',
      'auth_token' => $this->auth['read']['auth_token'],
      'nonce' => $this->auth['read']['nonce']
    );
    $url = report_helper::$base_url.'index.php/services/report/requestReport';
    $response = self::getResponse($url, TRUE, $params);
    // valid xml response will decode
    $response = new SimpleXmlElement($response, true);    
    $this->assertFalse(isset($response->error), "testRequestReportPostXML returned error. See log for details");
    $this->assertTrue(isset($response->record[0]->title), 'Report post XML response not as expected');
  }
  
  /**
   * A small test for a report with advanced features. 
   */
  public function testAdvancedReport() {
    Kohana::log('debug', "Running unit test, Controllers_Services_Report_Test::testAdvancedReport");
    $params = array(
      'report' => 'library/locations/locations_list.xml',
      'reportSource' => 'local',
      'mode' => 'json',
      'auth_token' => $this->auth['read']['auth_token'],
      'nonce' => $this->auth['read']['nonce'],
      'params' => json_encode(array('locattrs' => 'Test text', 'location_type_id' => 2))
    );
    $url = report_helper::$base_url.'index.php/services/report/requestReport';
    $response = self::getResponse($url, TRUE, $params);
    // valid json response will decode
    $response = json_decode($response, true);
    $this->assertFalse(isset($response['error']), "testAdvancedReport returned error. See log for details");
    $this->assertCount(1, $response, 'Advanced report response should only include 1 record');
    $this->assertTrue(isset($response[0]['name']), 'Advanced report did not return a name column');
    $this->assertEquals('Test location', $response[0]['name'],
        'Advanced report should return location called \'Test location\'');
    $this->assertTrue(array_key_exists('attr_location_test_text', $response[0]),
        'Advanced report should return column for test_text');
  }
  
  /**
   * Repeat check for advanced report output, this time requesting an attribute by ID rather than name.
   */
  public function testAdvancedReportByAttrId() {
    Kohana::log('debug', "Running unit test, Controllers_Services_Report_Test::testAdvancedReportByAttrId");
    $params = array(
      'report' => 'library/locations/locations_list.xml',
      'reportSource' => 'local',
      'mode' => 'json',
      'auth_token' => $this->auth['read']['auth_token'],
      'nonce' => $this->auth['read']['nonce'],
      'params' => json_encode(array('locattrs' => 1, 'location_type_id' => 2))
    );
    $url = report_helper::$base_url.'index.php/services/report/requestReport';
    $response = self::getResponse($url, TRUE, $params);
    // valid json response will decode
    $response = json_decode($response, true);
    $this->assertFalse(isset($response['error']), "testAdvancedReportByAttrId returned error. See log for details");
    $this->assertTrue(array_key_exists('attr_location_1', $response[0]),
        'Advanced report should return column for test_text by ID');
  }

  public function testReportRequestsParams() {
    Kohana::log('debug', "Running unit test, Controllers_Services_Report_Test::testReportRequestsParams");
    $params = array(
      'report' => 'library/locations/locations_list.xml',
      'reportSource' => 'local',
      'mode' => 'json',
      'auth_token' => $this->auth['read']['auth_token'],
      'nonce' => $this->auth['read']['nonce']
    );
    $url = report_helper::$base_url.'index.php/services/report/requestReport';
    $response = self::getResponse($url, TRUE, $params);
    // valid json response will decode
    $response = json_decode($response, true);
    $this->assertFalse(isset($response['error']), "testReportRequestsParams returned error. See log for details");
    $this->assertTrue(isset($response['parameterRequest']), 'Report should request parameters');
  }

  public function testInvalidReportRequest() {
    Kohana::log('debug', "Running unit test, Controllers_Services_Report_Test::testInvalidReportRequest");
    $params = array(
      'report' => 'invalid.xml',
      'reportSource' => 'local',
      'mode' => 'json',
      'auth_token' => $this->auth['read']['auth_token'],
      'nonce' => $this->auth['read']['nonce']
    );
    $url = report_helper::$base_url.'index.php/services/report/requestReport';
    $response = self::getResponse($url, TRUE, $params);
    // valid json response will decode
    $response = json_decode($response, true);
    $this->assertTrue(isset($response['error']), 'Invalid report request should return error');
  }
  
  public function testLookupCustomAttrs() {
    Kohana::log('debug', "Running unit test, Controllers_Services_Report_Test::testLookupCustomAttrs");
    $response = $this->getReportResponse(
      'library/locations/locations_list.xml', array('locattrs' => 'Test lookup', 'location_type_id' => 2));
    $this->assertFalse(isset($response['error']), "testLookupCustomAttrs returned error. See log for details");
    $this->assertCount(1, $response, 'Report response should only include 1 record');    
    $this->assertTrue(array_key_exists('attr_location_test_lookup', $response[0]),
        'Locations report should return column for test_lookup');
    $this->assertTrue(array_key_exists('attr_location_term_test_lookup', $response[0]),
        'Locations report should return column for test_lookup term');
    $this->assertEquals('Test term', $response[0]['attr_location_term_test_lookup'],
        'Locations report did not return correct attribute value');
  }

  public function testReportLibraryLocationsFilterableRecordCountsLeague() {
    Kohana::log('debug',
        "Running unit test, Controllers_Services_Report_Test::testReportLibraryLocationsFilterableRecordCountsLeague");
    $response = $this->getReportResponse(
        'library/locations/filterable_record_counts_league.xml', array('location_type_id' => 2));
    // Simply testing that the report parses and the SQL runs
    $this->assertFalse(isset($response['error']),
        "testReportLibraryLocationsFilterableRecordCountsLeague returned error. See log for details");
  }

  public function testReportLibraryLocationsFilterableRecordCountsLeagueLinked() {
    Kohana::log('debug', 'Running unit test, ' .
        'Controllers_Services_Report_Test::testReportLibraryLocationsFilterableRecordCountsLeagueLinked');
    $response = $this->getReportResponse(
      'library/locations/filterable_record_counts_league_linked.xml', array('location_type_id' => 2));
    // Simply testing that the report parses and the SQL runs
    $this->assertFalse(isset($response['error']),
      "testReportLibraryLocationsFilterableRecordCountsLeague returned error. See log for details");
  }

  public function testReportLibraryLocationsFilterableSpeciesCountsLeague() {
    Kohana::log('debug',
      "Running unit test, Controllers_Services_Report_Test::testReportLibraryLocationsFilterableSpeciesCountsLeague");
    $response = $this->getReportResponse(
      'library/locations/filterable_species_counts_league.xml', array('location_type_id' => 2));
    // Simply testing that the report parses and the SQL runs
    $this->assertFalse(isset($response['error']),
      "testReportLibraryLocationsFilterableRecordCountsLeague returned error. See log for details");
  }

  public function testReportLibraryLocationsFilterableSpeciesCountsLeagueLinked() {
    Kohana::log('debug', 'Running unit test, ' .
        'Controllers_Services_Report_Test::testReportLibraryLocationsFilterableSpeciesCountsLeagueLinked');
    $response = $this->getReportResponse(
      'library/locations/filterable_species_counts_league_linked.xml', array('location_type_id' => 2));
    // Simply testing that the report parses and the SQL runs
    $this->assertFalse(isset($response['error']),
      "testReportLibraryLocationsFilterableRecordCountsLeague returned error. See log for details");
  }

  public function testReportLibraryLocationsLocationsList() {
    Kohana::log('debug',
      "Running unit test, Controllers_Services_Report_Test::testReportLibraryLocationsLocationsList");
    $response = $this->getReportResponse(
      'library/locations/locations_list.xml', array('location_type_id' => 2, 'locattrs' => ''));
    // Simply testing that the report parses and the SQL runs
    $this->assertFalse(isset($response['error']), 'testReportLibraryLocationsLocationsList returned error ' .
        'when passed integer location type id. See log for details');
    $this->assertCount(1, $response, 'Report response should only include 1 record');
    $this->assertEquals($response[0]['name'], 'Test location',
        'Locations list report returned incorrect location name.');
    $response = $this->getReportResponse(
      'library/locations/locations_list.xml', array('location_type_id' => 'Test location type', 'locattrs' => ''));
    // Simply testing that the report parses and the SQL runs
    $this->assertFalse(isset($response['error']), 'testReportLibraryLocationsLocationsList returned error ' .
        'when passed a string location type id. See log for details');
    $this->assertCount(1, $response, 'Report response should only include 1 record');
    $this->assertEquals($response[0]['name'], 'Test location',
        'Locations list report returned incorrect location name.');
  }

  public function testReportLibraryLocationsLocationsList2() {
    Kohana::log('debug',
      "Running unit test, Controllers_Services_Report_Test::testReportLibraryLocationsLocationsList2");
    $response = $this->getReportResponse(
      'library/locations/locations_list_2.xml', array('location_type_id' => 2, 'locattrs' => ''));
    // Simply testing that the report parses and the SQL runs
    $this->assertFalse(isset($response['error']),
      "testReportLibraryLocationsLocationsList2 returned an error. See log for details");
    $this->assertCount(1, $response, 'Report response should only include 1 record');
    $this->assertEquals($response[0]['name'], 'Test location',
        'Locations list report returned incorrect location name.');
    $response = $this->getReportResponse(
      'library/locations/locations_list_2.xml', array('location_type_id' => 99999, 'locattrs' => ''));
    // Simply testing that the report parses and the SQL runs
    $this->assertFalse(isset($response['error']), 'testReportLibraryLocationsLocationsList2 returned an error '.
        'when filtering for a missing location type ID. See log for details');
    $this->assertCount(0, $response, 'Report response be empty, location type filter failed');
  }

  public function testReportLibraryOccurrencesFilterableOccurrencesDownloadWithoutLocality() {
    Kohana::log('debug', 'Running unit test, ' .
        'Controllers_Services_Report_Test::testReportLibraryOccurrencesFilterableOccurrencesDownloadWithoutLocality');
    $response = $this->getReportResponse(
      'library/occurrences/filterable_occurrences_download_without_locality.xml', array());
    // Simply testing that the report parses and the SQL runs
    $this->assertFalse(isset($response['error']), 'Error returned when calling ' .
        'library/occurrences/filterable_occurrences_download_without_locality.xml');
    // In following test, the confidential record in the fixture is skipped.
    $this->assertCount(1, $response, 'Report response should include 1 record');
  }

  public function testReportLibraryOccurrencesFilterableOccurrencesDownloadGisWithoutLocality() {
    Kohana::log('debug', 'Running unit test, ' .
        'Controllers_Services_Report_Test::testReportLibraryOccurrencesFilterableOccurrencesDownloadGisWithoutLocality');
    $response = $this->getReportResponse(
      'library/occurrences/filterable_occurrences_download_gis_without_locality.xml', array());
    // Simply testing that the report parses and the SQL runs
    $this->assertFalse(isset($response['error']), 'Error returned when calling ' .
      'library/occurrences/filterable_occurrences_download_gis_without_locality.xml');
    // In following test, the confidential record in the fixture is skipped.
    $this->assertCount(1, $response, 'Report response should include 1 record');
    $this->assertArrayHasKey('geom', $response[0]);
    $this->assertArrayHasKey('point_geom', $response[0]);
  }

  /**
   * Runs a test using the configuration array at the top of the class which does a fairly
   * thorough test of all the reports flagged as featured.
   */
  public function testAllFeaturedReports() {
    foreach ($this->featuredReports as $cfg) {
      foreach ($cfg['tests'] as $test) {
        $response = $this->getReportResponse("$cfg[path].xml", $test['params']);
        $this->assertFalse(isset($response['error']),
          "$cfg[path] returned an error with params " . var_export($test['params'], true));
        // count of records expected?
        if (is_int($test['result'])) {
          $this->assertEquals($test['result'], count($response),
            "Incorrect count returned for $cfg[path] with params " . var_export($test['params'], true));
        } else {
          $this->assertArrayHasKey($test['result'], $response,
            "Incorrect response returned for $cfg[path] with params " . var_export($test['params'], true));
        }
        if (isset($test['valueChecks'])) {
          foreach ($test['valueChecks'] as $check) {
            $this->assertGreaterThan($check['row'], count($response),
              "$cfg[path] did not return enough rows with params " . var_export($test['params'], true));
            $this->assertEquals($check['value'], $response[$check['row']][$check['field']],
              "Incorrect value returned in data for $cfg[path] with params " . var_export($test['params'], true));
          }
        }
      }
    }
  }

  private function getReportResponse($report, $params = []) {
    $requestParams = array(
      'report' => $report,
      'reportSource' => 'local',
      'mode' => 'json',
      'auth_token' => $this->auth['read']['auth_token'],
      'nonce' => $this->auth['read']['nonce'],
      'params' => json_encode($params)
    );
    $url = report_helper::$base_url.'index.php/services/report/requestReport';
    $response = self::getResponse($url, TRUE, $requestParams);
    // valid json response will decode
    return json_decode($response, true);
  }

}