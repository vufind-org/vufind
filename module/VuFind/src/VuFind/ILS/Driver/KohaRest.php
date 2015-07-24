<?php
/**
 * KohaRest ILS Driver 
*
 * PHP version 5
 *
 * Copyright (C) Alex Sassmannshausen, PTFS Europe 2014.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Alex Sassmannshausen, <alex.sassmannshausen@ptfs-europe.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace KatalogCT\ILS\Driver;
use PDO, PDOException;
use VuFind\Exception\ILS as ILSException;
use VuFindHttp\HttpServiceInterface;
use Zend\Log\LoggerInterface;
use VuFind\Exception\Date as DateException;

/**
 * VuFind Driver for Koha, using web APIs (version: 0.1)
 *
 * last updated: 05/13/2014
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Alex Sassmannshausen, <alex.sassmannshausen@ptfs-europe.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class KohaRest extends \VuFind\ILS\Driver\AbstractBase implements \VuFindHttp\HttpServiceAwareInterface,
    \Zend\Log\LoggerAwareInterface
{
    /**
     * Web services host
     *
     * @var string
     */
    protected $host;

    /**
     * Web services application path
     *
     * @var string
     */
    //protected $api_path = "/cgi-bin/koha/ilsdi.pl?service=";

    /**
     * ILS base URL
     *
     * @var string
     */
    protected $ilsBaseUrl;

    /**
     * Location codes
     *
     * @var array
     */
    protected $locations;
    
    /**
     * Codes of locations avalaible for pickup
     *
     * @var array
     */
    protected $pickupEnableBranchcodes;

    /**
     * Default location code
     *
     * @var string
     */
    protected $default_location;

    /**
     * Database connection
     *
     * @var string
     */
    
    protected $db;
    
    /**
     * Set the logger
     *
     * @param LoggerInterface $logger Logger to use.
     *
     * @return void
     */
    
    protected $logger = false;
    
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Show a debug message.
     *
     * @param string $msg Debug message.
     *
     * @return void
     */
    protected function debug($msg)
    {
        if ($this->logger) {
            $this->logger->debug($msg);
        }
    }

    /**
     * HTTP service
     *
     * @var \VuFindHttp\HttpServiceInterface
     */
    protected $httpService = null;

    /**
     * Set the HTTP service to be used for HTTP requests.
     *
     * @param HttpServiceInterface $service HTTP service
     *
     * @return void
     */
    public function setHttpService(HttpServiceInterface $service)
    {
        $this->httpService = $service;
    }

    /**
     * Date converter object
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter;

    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
    public function init()
    {
        if (empty($this->config)) {
            throw new ILSException('Configuration needs to be set.');
        }

        // Is debugging enabled?
        $this->debug_enabled = isset($this->config['Catalog']['debug'])
            ? $this->config['Catalog']['debug'] : false;

        // Base for API address
        $this->host = isset($this->config['Catalog']['host']) ?
            $this->config['Catalog']['host'] : "localhost";

        // Storing the base URL of ILS
        $this->ilsBaseUrl = isset($this->config['Catalog']['url'])
        	? $this->config['Catalog']['url'] : "";

        // Default location defined in 'KohaRest.ini'
        $this->default_location
            = isset($this->config['Holds']['defaultPickUpLocation'])
            ? $this->config['Holds']['defaultPickUpLocation'] : null;

        $this->pickupEnableBranchcodes 
        	= isset($this->config['Holds']['pickupLocations'])
        	? $this->config['Holds']['pickupLocations'] : array();
        
        // Create a dateConverter
        $this->dateConverter = new \VuFind\Date\Converter;

        if ($this->debug_enabled) {
            $this->debug("Config Summary:");
            $this->debug("Debug: " . $this->debug_enabled);
            $this->debug("DB Host: " . $this->host);
            $this->debug("ILS URL: " . $this->ilsBaseUrl);
            $this->debug("Locations: " . $this->locations);
            $this->debug("Default Location: " . $this->default_location);
        }
    }
    
    /**
     * Initialize the DB driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
    public function initDB()
    {           
      if (empty($this->config)) {
        throw new ILSException('Configuration needs to be set.');
      }
      
      //Connect to MySQL
      try {
        $this->db = new PDO(
        		'mysql:host=' . $this->host .
        		';port=' . $this->config['Catalog']['port'] .
        		';dbname=' . $this->config['Catalog']['database'],
        		$this->config['Catalog']['username'],
        		$this->config['Catalog']['password']
        );
        
        // Throw PDOExceptions if something goes wrong
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        //Return result set like mysql_fetch_assoc()
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
		// set communication enoding to utf8
        $this->db->exec("SET NAMES utf8");
      } catch (PDOException $e) {
      	echo 'Connection failed: ' . $e->getMessage();
        $this->debug('Connection failed: ' . $e->getMessage());
      }
      
      $this->debug('Connected to DB');
    }

    /**
     * getField 
     *
     * Check $contents is not "", return it; else return $default.
     *
     * @param string $contents string to be checked
     * @param string $default  value to return if $contents is ""
     *
     * @return $contents or $default
     */
    protected function getField($contents, $default="Unknown")
    {
        if ((string) $contents != "") {
            return (string) $contents;
        } else {
            return $default;
        }
    }

    /**
     * Make Request
     *
     * Makes a request to the Polaris Restful API
     *
     * @param string $api_query   Query string for request
     * @param string $http_method HTTP method (default = GET)
     *
     * @throws ILSException
     * @return obj
     */
    protected function makeRequest($api_query, $http_method="GET")
    {
        //$url = $this->host . $this->api_path . $api_query;

        $url = $this->ilsBaseUrl . "?service=" . $api_query;
        
        if ($this->debug_enabled) {
            $this->debug("URL: '$url'");
        }
        $http_headers = array(
            "Accept: text/xml",
            "Accept-encoding: plain",
        );

        try {
            $client = $this->httpService->createClient($url);

            $client->setMethod($http_method);
            $client->setHeaders($http_headers);
            $result = $client->send();
        } catch (\Exception $e) {
            $this->debug("Result is invalid.");
            throw new ILSException($e->getMessage());
        }

        if (!$result->isSuccess()) {
            $this->debug("Result is invalid.");
            throw new ILSException('HTTP error');
        }
        $answer = $result->getBody();
        //$answer = str_replace('xmlns=', 'ns=', $answer);
        $result = simplexml_load_string($answer);
        if (!$result) {
            if ($this->debug_enabled) {
                $this->debug("XML is not valid, URL: $url");
            }
            throw new ILSException(
                "XML is not valid, URL: $url method: $method answer: $answer."
            );
        }
        return $result;
    }

    /**
     * Make Ilsdi Request Array
     *
     * Makes a request to the Polaris Restful API
     *
     * @param string $service   Called function (GetAvailability, GetRecords, GetAuthorityRecords, LookupPatron, 
     * 								AuthenticatePatron, GetPatronInfo, GetPatronStatus, GetServices, RenewLoan, 
	 * 								HoldTitle, HoldItem, CancelHold)
	 * @param array $params		Params for the function, key is parameter name, value is parameter value 
     * @param string $http_method HTTP method (default = GET)
     *
     * @throws ILSException
     * @return obj
     */
    protected function makeIlsdiRequest($service, $params, $http_method="GET")
    {
    	$start = microtime(true);
    	$url = $this->ilsBaseUrl . "?service=" . $service;
    	foreach ($params as $paramname => $paramvalue) {
    		$url .= "&$paramname=" . urlencode($paramvalue);
    	}
    
    	if ($this->debug_enabled) {
    		$this->debug("URL: '$url'");
    	}
    	$http_headers = array(
    			"Accept: text/xml",
    			"Accept-encoding: plain",
    	);
    
    	try {
    		$client = $this->httpService->createClient($url);
    		$client->setMethod($http_method);
    		$client->setHeaders($http_headers);
    		$result = $client->send();
    	} catch (\Exception $e) {
    		$this->debug("Result is invalid.");
    		throw new ILSException($e->getMessage());
    	}
    
    	if (!$result->isSuccess()) {
    		$this->debug("Result is invalid.");
    		throw new ILSException('HTTP error');
    	}
    	$end = microtime(true);
    	$time1 = $end - $start;
    	$start = microtime(true);
    	$result = simplexml_load_string($result->getBody());
    	if (!$result) {
    		if ($this->debug_enabled) {
    			$this->debug("XML is not valid, URL: $url");
    		}
    		throw new ILSException(
    				"XML is not valid, URL: $url"
    		);
    	}
    	$end = microtime(true);
    	$time2 = $end - $start;
    	echo "\t$time1 - $time2";
    	return $result;
    }
    
    
    /**
     * toKohaDate
     *
     * Turns a display date into a date format expected by Koha.
     *
     * @param string $display_date Date to be converted
     *
     * @throws ILSException
     * @return string $koha_date
     */
    protected function toKohaDate($display_date)
    {
        $koha_date = "";

        // Convert last interest date from format to Koha format
        $koha_date = $this->dateConverter->convertFromDisplayDate(
            "Y-m-d", $display_date
        );

        $checkTime =  $this->dateConverter->convertFromDisplayDate(
            "U", $display_date
        );
        if (!is_numeric($checkTime)) {
            throw new DateException('Result should be numeric');
        }

        if (time() > $checkTime) {
            // Hold Date is in the past
            throw new DateException('hold_date_past');
        }
        return $koha_date;
    }

    /**
     * Public Function which retrieves renew, hold and cancel settings from the
     * driver ini file.
     *
     * @param string $function The name of the feature to be checked
     *
     * @return array An array with key-value pairs.
     */
    public function getConfig($function)
    {
        $functionConfig = "";
        if (isset($this->config[$function])) {
            $functionConfig = $this->config[$function];
        } else {
            $functionConfig = false;
        }
        return $functionConfig;
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible for gettting a list of valid library locations for
     * holds / recall retrieval
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.    May be used to limit the pickup options
     * or may be ignored.  The driver must not add new options to the return array
     * based on this data or other areas of VuFind may behave incorrectly.
     *
     * @throws ILSException
     * @return array             An array of associative arrays with locationID
     * and locationDisplay keys
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
    	if (!$this->locations) {
    		if (!$this->db) {
    			$this->initDB();
    		}
    		$branchcodes = "'" . implode("','", $this->pickupEnableBranchcodes) . "'";
    		$sql = "SELECT branchcode as locationID, branchname as locationDisplay FROM branches WHERE branchcode IN ($branchcodes)";
    		try {
    			$sqlSt = $this->db->prepare($sql);
    			$sqlSt->execute();
    			$this->locations = $sqlSt->fetchAll();
    		} catch (PDOException $e) {
        		$this->debug('Connection failed: ' . $e->getMessage());
        		throw new ILSException($e->getMessage());
      		}
    	}
      	return $this->locations;

            // we get them from the API
            // FIXME: Not yet possible: API incomplete.
            // TODO: When API: pull locations dynamically from API.
            /* $response = $this->makeRequest("organizations/branch"); */
            /* $locations_response_array = $response->OrganizationsGetRows; */
            /* foreach ($locations_response_array as $location_response) { */
            /*     $locations[] = array( */
            /*         'locationID'      => $location_response->OrganizationID, */
            /*         'locationDisplay' => $location_response->Name, */
            /*     ); */
            /* } */
    }

    /**
     * Get Default Pick Up Location
     *
     * Returns the default pick up location set in KohaRest.ini
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.    May be used to limit the pickup options
     * or may be ignored.
     *
     * @return string           The default pickup location for the patron.
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        return $this->default_location;
    }

    /**
     * Place Hold
     *
     * Attempts to place a hold or recall on a particular item and returns
     * an array with result details or throws an exception on failure of support
     * classes
     *
     * @param array $holdDetails An array of item and patron data
     *
     * @throws ILSException
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeHold($holdDetails)
    {
        $rsvLst             = array();
        $patron             = $holdDetails['patron'];
        $patron_id          = $patron['id'];
        $request_location   = isset($patron['ip']) ? $patron['ip'] : "127.0.0.1";
        $bib_id             = $holdDetails['id'];
        $item_id            = $holdDetails['item_id'];
        $pickup_location    = !empty($holdDetails['pickUpLocation'])
            ? $holdDetails['pickUpLocation'] : $this->default_location;
        $level              = isset($holdDetails['level'])
            && !empty($holdDetails['level']) ? $holdDetails['level'] : "item";

        try {
            //$needed_before_date = $this->toKohaDate($holdDetails['requiredBy']);
            $dateObject = \DateTime::createFromFormat("j. n. Y", $holdDetails['requiredBy']);
            $needed_before_date = $dateObject->format("Y-m-d");
        } catch (DateException $e) {
            return array(
                "success" => false,
                "sysMessage" => "It seems you entered an invalid expiration date."
            );
        }

        if ($this->debug_enabled) {
            $this->debug("patron: " . $patron);
            $this->debug("patron_id: " . $patron_id);
            $this->debug("request_location: " . $request_location);
            $this->debug("item_id: " . $item_id);
            $this->debug("bib_id: " . $bib_id);
            $this->debug("pickup loc: " . $pickup_location);
            $this->debug("Needed before date: " . $needed_before_date);
            $this->debug("Level: " . $level);
        }


        if ( $level == "title" ) {
            $rqString = "HoldTitle&patron_id=$patron_id&bib_id=$bib_id"
                . "&request_location=$request_location"
                . "&pickup_location=$pickup_location"
                . "&pickup_expiry_date=$needed_before_date";
        } else {
            $rqString = "HoldItem&patron_id=$patron_id&bib_id=$bib_id"
                . "&item_id=$item_id"
                . "&pickup_location=$pickup_location"
                . "&needed_before_date=$needed_before_date"
                . "&pickup_expiry_date=$needed_before_date";
        }
        
        $rsp = $this->makeRequest($rqString);
        
        //TODO - test this new functionality
        /*
        if ( $level == "title" ) {
        	$rsp2 = $this->makeIlsdiRequest("HoldTitle", 
        			array("patron_id" => $patron_id,
        				  "bib_id" => $bib_id,
        				  "request_location" => $request_location,
        				  "pickup_location" => $pickup_location,
        				  "pickup_expiry_date" => $needed_before_date,
        				  "needed_before_date" => $needed_before_date
        			));
        } else {
        	$rsp2 = $this->makeIlsdiRequest("HoldItem",
        			array("patron_id" => $patron_id,
        				  "bib_id" => $bib_id,
        				  "item_id" => $item_id,
        				  "pickup_location" => $pickup_location,
        				  "pickup_expiry_date" => $needed_before_date,
        				  "needed_before_date" => $needed_before_date
        			));	
        }
        */
        if ($this->debug_enabled) {
            $this->debug("Title: " . $rsp->{'title'});
            $this->debug("Pickup Location: " . $rsp->{'pickup_location'});
            $this->debug("Code: " . $rsp->{'code'});
        }

        if ($rsp->{'code'} != "") {
            return array(
                "success"    => false,
                "sysMessage" => $this->getField($rsp->{'code'}) . $holdDetails['level'],
            );
        }
        return array(
            "success"    => true,
        	//"sysMessage" => $message,
        );
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, array $patron = NULL)
    {  
          
      $this->debug("Function getHolding($id, $patron) called");
      
      $started = microtime(TRUE);
    
      $holding = array();
      $available = true;
      $duedate = $status = '';
      $loc = $shelf = '';
      $reserves = "N";
    
      $sql = "select i.itemnumber as ITEMNO, i.location as LOCATION, i.holdingbranch as HLDBRNCH, 
      		i.homebranch as HOMEBRANCH, i.reserves as RESERVES, i.itemcallnumber as CALLNO, i.barcode as BARCODE, 
      		i.barcode as COPYNO, i.notforloan as NOTFORLOAN, i.itemnotes as PERIONAME, b.frameworkcode as DOCTYPE,
      		t.frombranch as TRANSFERFROM, t.tobranch as TRANSFERTO
                    from items i join biblio b on i.biblionumber = b.biblionumber
                    left outer join (SELECT itemnumber, frombranch, tobranch from branchtransfers where datearrived IS NULL) as t on t.itemnumber = i.itemnumber
                    where i.biblionumber = :id AND i.itemlost = '0' order by i.itemnumber DESC";
     $sqlReserves = "select count(*) as RESERVESCOUNT from reserves WHERE biblionumber = :id AND found IS NULL";
     $sqlWaitingReserve = "select count(*) as WAITING from reserves WHERE itemnumber = :item_id and found = 'W'";
      //var_dump($this->db);
      if (!$this->db) {
        $this->initDB();
      }
      try {
      	$itemSqlStmt = $this->db->prepare($sql);
      	$itemSqlStmt->execute(array(':id' => $id));
      	$sqlStmtReserves = $this->db->prepare($sqlReserves);
      	$sqlStmtWaitingReserve = $this->db->prepare($sqlWaitingReserve);
      	$sqlStmtReserves->execute(array(':id' => $id));
      } catch (PDOException $e) {
        $this->debug('Connection failed: ' . $e->getMessage());
      }
      	      
      if ($this->debug_enabled) {
        $this->debug("Rows count: " . $itemSqlStmt->rowCount());
      }
      
      $reservesRow = $sqlStmtReserves->fetch();	
      $reservesCount = $reservesRow["RESERVESCOUNT"];
      
      foreach ($itemSqlStmt->fetchAll() as $rowItem) {
        $inum = $rowItem['ITEMNO'];
        $sqlStmtWaitingReserve->execute(array(':item_id' => $inum));
        $waitingReserveRow = $sqlStmtWaitingReserve->fetch();
        $waitingReserve = $waitingReserveRow["WAITING"];
        $sql = "select date_due as DUEDATE from issues where itemnumber = :inum";
        switch ($rowItem['NOTFORLOAN']) {
          case 0:
            // If the item is available for loan, then check its current
            // status
            $issueSqlStmt = $this->db->prepare($sql);
            $issueSqlStmt->execute(array(':inum' => $inum));
            $rowIssue = $issueSqlStmt->fetch();
            if ($rowIssue) {
              $available = false;
              $status = 'Checked out';
              $duedate = $rowIssue['DUEDATE'];
            } else {
              $available = true;
              $status = 'Available';
              // No due date for an available item
              $duedate = '';
            }
            break;
          case 1: // The item is not available for loan
          default:
            $available = false;
            $status = 'Not for loan';
            $duedate = '';
            break;
        }
        
        
        $duedate_formatted = date_format(new \DateTime($duedate), "j. n. Y");
        
       
        //Retrieving the full branch name
        if ($rowItem['HLDBRNCH'] == null) {
        	if($rowItem['HOMEBRANCH'] == null) {
        		$loc = "Unknown";
        	} else {
        		$loc = $rowItem['HOMEBRANCH'];
        	}
        } else {
          	$loc = $rowItem['HLDBRNCH'];
        }

        if($loc != "Unknown") {
        	$sqlBranch = "select branchname as BNAME from branches where branchcode = :branch";
        	$branchSqlStmt = $this->db->prepare($sqlBranch);
        	$branchSqlStmt->execute(array(':branch' => $loc));
        	$row = $branchSqlStmt->fetch();
        	if ($row) {
        		$loc = $row['BNAME'];
        	}
        }

        $onTransfer = false;
        if(($rowItem["TRANSFERFROM"] != null) && ($rowItem["TRANSFERTO"] != null)) {
        	$branchSqlStmt->execute(array(':branch' => $rowItem["TRANSFERFROM"]));
        	$rowFrom = $branchSqlStmt->fetch();
        	$transferfrom = $rowFrom ? $rowFrom["BNAME"] : $rowItem["TRANSFERFROM"];
        	$branchSqlStmt->execute(array(':branch' => $rowItem["TRANSFERTO"]));
        	$rowTo = $branchSqlStmt->fetch();
        	$transferto = $rowTo ? $rowTo["BNAME"] : $rowItem["TRANSFERTO"];
        	$status = "Na cestě z $transferfrom do $transferto";
        	$available = false;
        	$onTransfer = true;
        }
        
        if ($rowItem['DOCTYPE'] == "PE") {  
        	$rowItem['COPYNO'] = $rowItem['PERIONAME'];  
        }
        if ($waitingReserve) {
        	$available = false;
        	$status = "Waiting";
        	$waiting = true;
        }
        
        $holding[] = array(
            'id'           => $id,
            'availability' => (string) $available,
            'item_id'      => $rowItem['ITEMNO'],
            'status'       => $status,
            'location'     => $loc,
        	//'reserve'      => (null == $rowItem['RESERVES']) ? 'N' : $rowItem['RESERVES'],
        	'reserve'      => 'N',
            'callnumber'   => ((null == $rowItem['CALLNO']) || ($rowItem['DOCTYPE'] == "PE")) ? '' : $rowItem['CALLNO'],
            'duedate'      => ($onTransfer || $waiting) ? '' : (string) $duedate_formatted, 
            'barcode'      => (null == $rowItem['BARCODE']) ? 'Unknown' : $rowItem['BARCODE'],
            'number'       => (null == $rowItem['COPYNO']) ? '' : $rowItem['COPYNO'],
        	'requests_placed' => $reservesCount ? $reservesCount : 0,
        	'frameworkcode'=> $rowItem['DOCTYPE'],
        );        
        
      }
      
      //file_put_contents('holding.txt', print_r($holding,TRUE), FILE_APPEND);
    
      $this->debug("Processing finished, rows processed: " . count($holding).", took ".(microtime(TRUE)-$started)." sec");    
      
      return $holding;
    }
    
    
    public function getHoldingOld($id, $patron = false)
    {
      
      $holding = array();
      $available = true;
      $duedate = $status = '';
      $loc = $shelf = '';
      $reserves = "N";
    
      $rsp = $this->makeRequest("GetRecords&id=$id");
    
      if ($this->debug_enabled) {
        $this->debug("ISBN: " . $rsp->{'record'}->{'isbn'});
      }
    
      foreach ($rsp->{'record'}->{'items'}->{'item'} as $item) {
        if ($this->debug_enabled) {
          $this->debug("Biblio: " . $item->{'biblioitemnumber'});
          $this->debug("ItemNo: " . $item->{'itemnumber'});
        }
        switch ($item->{'notforloan'}) {
          case 0:
            if ($item->{'date_due'} != "") {
              $available = false;
              $status    = 'Checked out';
              $duedate   = $this->getField($item->{'date_due'});
            } else {
              $available = true;
              $status    = 'Available';
              $duedate   = '';
            }
            break;
          case 1: // The item is not available for loan
          default: $available = false;
          $status = 'Not for loan';
          $duedate = '';
          break;
        }
    
        foreach ($rsp->{'record'}->{'reserves'}->{'reserve'} as $reserve) {
          if ($reserve->{'suspend'} == '0') {
            $reserves = "Y";
            break;
          }
        }
        $holding[] = array(
            'id'           => (string) $id,
            'availability' => (string) $available,
            'item_id'      => $this->getField($item->{'itemnumber'}),
            'status'       => (string) $status,
            'location'     => $this->getField($item->{'holdingbranchname'}),
            'reserve'      => (string) $reserves,
            'callnumber'   => $this->getField($item->{'itemcallnumber'}),
            'duedate'      => (string) $duedate,
            'barcode'      => $this->getField($item->{'barcode'}),
            'number'       => $this->getField($item->{'copynumber'}),
        );
      }
      return $holding;
    }    
    
    /**
     * This method queries the ILS for new items
     * 
     * @param unknown $page - page number of results to retrieve (counting starts at 1)
     * @param unknown $limit - the size of each page of results to retrieve
     * @param unknown $daysOld - the maximum age of records to retrieve in days (maximum 30)
     * @param string $fundId - optional fund ID to use for limiting results (use a value returned by getFunds, or exclude for no limit); note that “fund” may be a misnomer – if funds are not an appropriate way to limit your new item results, you can return a different set of values from getFunds. The important thing is that this parameter supports an ID returned by getFunds, whatever that may mean.
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null) {

      $this->debug("getNewItems called $page|$limit|$daysOld|$fundId");
      
      $items = array();
      $daysOld = min(abs(intval($daysOld)), 30);
      $sql = "SELECT distinct biblionumber as id FROM items WHERE itemlost = 0 and stocknumber > 1 and dateaccessioned > DATE_ADD(CURRENT_TIMESTAMP, INTERVAL -$daysOld day) ORDER BY dateaccessioned DESC";      
            
      if (!$this->db) {
        $this->initDB();
      }
      
      $this->debug($sql);
      
      $itemSqlStmt = $this->db->prepare($sql);
      $itemSqlStmt->execute();
      
      $rescount = 0;
      foreach ($itemSqlStmt->fetchAll() as $rowItem) {        
        $items[] = array (
            'id' => $rowItem['id']
        );
        $rescount++;
      }
      
      $this->debug($rescount." fetched");
      
      $results = array_slice($items, ($page - 1) * $limit, ($page * $limit)-1);
      return array('count' => $rescount, 'results' => $results);      
    }

    /**
     * Get Hold Link
     *
     * The goal for this method is to return a URL to a "place hold" web page on
     * the ILS OPAC. This is used for ILSs that do not support an API or method
     * to place Holds.
     *
     * @param string $id      The id of the bib record
     * @param array  $details Item details from getHoldings return array
     *
     * @return string         URL to ILS's OPAC's place hold screen.
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    /*public function getHoldLink($id, $details)
    {
        // Web link of the ILS for placing hold on the item
        return $this->ilsBaseUrl . "/cgi-bin/koha/opac-reserve.pl?biblionumber=$id";
    }*/



    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return mixed        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {
        $id = 0;
        $transactionLst = array();
        $row = $sql = $sqlStmt = '';
        try {
            $id = $patron['id'];
            $sql = "select old_issues.issuedate as DUEDATE, items.biblionumber as " .
                "BIBNO, items.barcode BARCODE, items.itemnumber as ITEM_ID, items.itemnotes as NOTES " .
                "from old_issues join items on old_issues.itemnumber = items.itemnumber " .
                "where old_issues.borrowernumber = :id ORDER BY DUEDATE DESC ";
 			if (!$this->db) {
        		$this->initDB();
      		}
            
			$sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute(array(':id' => $id));
            foreach ($sqlStmt->fetchAll() as $row) {
                $transactionLst[] = array(
                    'duedate' => date_format(new \DateTime($row['DUEDATE']), "j. n. Y"),
                    'id' => $row['BIBNO'],
                    'barcode' => $row['BARCODE'],
                    'renew' => $row['RENEWALS'],
                    'item_id' => $row['ITEM_ID'],
                    'message' => $row['NOTES'],
                );
            }
            return $transactionLst;
        }
        catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }
    }


    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return mixed        Array of the patron's fines on success.
     */
    public function getMyFines_ILS($patron)
    {
        $id = $patron['id'];        
        $fineLst = array();
        
        $rsp = $this->makeRequest(
            "GetPatronInfo&patron_id=$id" . "&show_contact=0&show_fines=1"
        );

        if ($this->debug_enabled) {
            $this->debug("ID: " . $rsp->{'borrowernumber'});
            $this->debug("Chrgs: " . $rsp->{'charges'});
        }

        foreach ($rsp->{'fines'}->{'fine'} as $fine) {
            $fineLst[] = array(
                'amount'     => 100 * $this->getField($fine->{'amount'}),
                // FIXME: require accountlines.itemnumber -> issues.issuedate data
                'checkout'   => "N/A",
                'fine'       => $this->getField($fine->{'description'}),
                'balance'    => 100 * $this->getField($fine->{'amountoutstanding'}),
                'createdate' => $this->getField($fine->{'date'}),
                // FIXME: require accountlines.itemnumber -> issues.date_due data.
                'duedate'    => "N/A",
                // FIXME: require accountlines.itemnumber -> items.biblionumber data 
                'id'         => "N/A",
            );
        }
        return $fineLst;
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array        Array of the patron's holds on success.
     */
    public function getMyHolds($patron)
    {
        $id = $patron['id'];        
        $holdLst = array();
        
        $rsp = $this->makeRequest(
            "GetPatronInfo&patron_id=$id" . "&show_contact=0&show_holds=1"
        );

        if ($this->debug_enabled) {
            $this->debug("ID: " . $rsp->{'borrowernumber'});
            //print_r($rsp); // Proof that no itemnumber is returned.
        }
        foreach ($rsp->{'holds'}->{'hold'} as $hold) {
            $holdLst[] = array(
                'id'       => $this->getField($hold->{'biblionumber'}),
                'location' => $this->getField($hold->{'branchname'}),
                // FIXME: require exposure of reserves.expirationdate
                'expire'   => "N/A",
                'create'   => date_format(new \DateTime($this->getField($hold->{'reservedate'})), "j. n. Y"),
                'position' => $this->getField($hold->{'priority'}),
                'title' => $this->getField($hold->{'title'}),
                'available' => ($this->getField($hold->{'found'}) == "W")?true:false,
               	'reserve_id' => $this->getField($hold->{'reserve_id'}),
            );
        }
        return $holdLst;
    }

    /**
     * Get Cancel Hold Details
     *
     * In order to cancel a hold, Koha requires the patron details and
     * an item ID. This function returns the item id as a string. This
     * value is then used by the CancelHolds function.
     *
     * @param array $holdDetails An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getCancelHoldDetails($holdDetails)
    {
        return $holdDetails['reserve_id'];
    }

    /**
     * Cancel Holds
     *
     * Attempts to Cancel a hold or recall on a particular item. The
     * data in $cancelDetails['details'] is determined by getCancelHoldDetails().
     *
     * @param array $cancelDetails An array of item and patron data
     *
     * @return array               An array of data on each request including
     * whether or not it was successful and a system message (if available)
     */
    public function cancelHolds($cancelDetails)
    {
    	$retVal         = array('count' => 0, 'items' => array());
    	$details        = $cancelDetails['details'];
        $patron_id      = $cancelDetails['patron']['id'];
        $request_prefix = "CancelHold&patron_id=" . $patron_id . "&item_id=";

        foreach ($details as $cancelItem) {
            $rsp = $this->makeRequest($request_prefix . $cancelItem);
            if ($rsp->{'code'} != "Canceled") {
                $retVal['items'][$cancelItem] = array(
                    'success'    => false,
                    'status'     => 'hold_cancel_fail',
                    'sysMessage' => $this->getField($rsp->{'code'}),
                );
            } else {
                $retVal['count']++;
                $retVal['items'][$cancelItem] = array(
                    'success' => true,
                    'status' => 'hold_cancel_success',
                );
            }
        }
        return $retVal;
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @throws ILSException
     * @return array        Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        $id = $patron['id'];
        $profile = array();
        
        $rsp = $this->makeRequest(
            "GetPatronInfo&patron_id=$id" . "&show_contact=1"
        );

        if ($this->debug_enabled) {
            $this->debug("Code: " . $rsp->{'code'});
            $this->debug("Cardnumber: " . $rsp->{'cardnumber'});
        }

        if ($rsp->{'code'} != 'PatronNotFound') {
            $profile = array(
                'firstname' => $this->getField($rsp->{'firstname'}),
                'lastname'  => $this->getField($rsp->{'surname'}),
                'address1'  => $this->getField($rsp->{'address'}),
                'address2'  => $this->getField($rsp->{'address2'}),
                'zip'       => $this->getField($rsp->{'zipcode'}),
                'phone'     => $this->getField($rsp->{'phone'}),
                'group'     => $this->getField($rsp->{'categorycode'}),
            );
            return $profile;
        } else {
            return null;
        }
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($patron)
    {
    	echo "<!--";
    	$id = $patron['id'];
        $transactionLst = array();
        $start = microtime(true);
        $rsp = $this->makeRequest(
            "GetPatronInfo&patron_id=$id" . "&show_contact=0&show_loans=1"
        );
        $end = microtime(true);
        $requestTimes[] = $end - $start;  

        if ($this->debug_enabled) {
            $this->debug("ID: " . $rsp->{'borrowernumber'});
        }
		
        foreach ($rsp->{'loans'}->{'loan'} as $loan) {
        	$start = microtime(true);
        	$rsp2 = $this->makeIlsdiRequest("GetServices", array(
        			"patron_id" => $id,
        			"item_id" => $this->getField($loan->{'itemnumber'})
        			));
        	$end = microtime(true);
        	$requestTimes[] = $end - $start;
        	$renewable = false;
        	foreach($rsp2->{'AvailableFor'} as $service) {
        		if ($this->getField($service->{0}) == "loan renewal") {
        			$renewable = true;
        		}
        	}

        	$transactionLst[] = array(
                'duedate'   => date_format(new \DateTime($this->getField($loan->{'date_due'})), "j. n. Y"),
                'id'        => $this->getField($loan->{'biblionumber'}),
                'item_id'   => $this->getField($loan->{'itemnumber'}),
                'barcode'   => $this->getField($loan->{'barcode'}),
                'renew'     => $this->getField($loan->{'renewals'}, '0'),
    			'renewable' => $renewable,
            );
        }
        foreach($requestTimes as $time) {
        	echo "\n$time\n";
        }
        echo "-->";
        return $transactionLst;
    }
    

    /**
     * Get Renew Details
     *
     * In order to renew an item, Koha requires the patron details and
     * an item id. This function returns the item id as a string which
     * is then used as submitted form data in checkedOut.php. This
     * value is then extracted by the RenewMyItems function.
     *
     * @param array $checkOutDetails An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getRenewDetails($checkOutDetails)
    {
        return $checkOutDetails['item_id'];
    }

    /**
     * Renew My Items
     *
     * Function for attempting to renew a patron's items.  The data in
     * $renewDetails['details'] is determined by getRenewDetails().
     *
     * @param array $renewDetails An array of data required for
     * renewing items including the Patron ID and an array of renewal
     * IDS
     *
     * @return array An array of renewal information keyed by item ID
     */
    public function renewMyItems($renewDetails)
    {
        $retVal         = array('blocks' => false, 'details' => array());
        $details        = $renewDetails['details'];
        $patron_id      = $renewDetails['patron']['id'];
        $request_prefix = "RenewLoan&patron_id=" . $patron_id . "&item_id=";

        foreach ($details as $renewItem) {
            $rsp = $this->makeRequest($request_prefix . $renewItem);
            if ($rsp->{'success'} != '0') {
                list($date, $time)
                    = explode(" ", $this->getField($rsp->{'date_due'}));
                $retVal['details'][$renewItem] = array(
                    "success"  => true,
                    "new_date" => $date,
                    "new_time" => $time,
                    "item_id"  => $renewItem,
                );
            } else {
                $retVal['details'][$renewItem] = array(
                    "success"    => false,
                    "new_date"   => false,
                    "item_id"    => $renewItem,
                    //"sysMessage" => $this->getField($rsp->{'error'}),
                );
            }
        }
        return $retVal;
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @throws ILSException
     * @return array     An array with the acquisitions data on success.
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPurchaseHistory($id)
    {
        // TODO
        return array();
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @throws ILSException
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        return $this->getHolding($id);
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $idLst The array of record ids to retrieve the status for
     *
     * @throws ILSException
     * @return array       An array of getStatus() return values on success.
     */
    public function getStatuses($idLst)
    {
        $this->debug("IDs:".implode(',', $idLst));
        
        $statusLst = array();
        foreach ($idLst as $id) {
            $statusLst[] = $this->getStatus($id);
        }
        return $statusLst;
    }

    /**
     * Get suppressed records.
     *
    * @throws ILSException
     * @return array ID numbers of suppressed records in the system.
     */
    public function getSuppressedRecords()
    {
      try {
 			  if (!$this->db) {
          $this->initDB();
      	}
        $sqlStmt = $this->db->prepare($sql);
        $result = $sqlStmt->fetchAll(PDO::FETCH_COLUMN, 0);
      } catch (PDOException $e) {
        throw new ILSException($e->getMessage());
      }
      return $result;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron's password
     *
     * @throws ILSException
     * @return mixed          Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {
        $patron = array();

        $idObj = $this->makeRequest(
            "AuthenticatePatron" . "&username=" . $username
            . "&password=" . $password
        );
        if ($this->debug_enabled) {
            $this->debug("Code: " . $idObj->{'code'});
            $this->debug("ID: " . $idObj->{'id'});
        }
        $id = $this->getField($idObj->{'id'},0);
		if($id) {
            $rsp = $this->makeRequest(
                "GetPatronInfo&patron_id=$id&show_contact=1"
            );
            $profile = array(
            	'id'           => $this->getField($idObj->{'id'}),
                'firstname'    => $this->getField($rsp->{'firstname'}),
                'lastname'     => $this->getField($rsp->{'lastname'}),
                'cat_username' => $username,
                'cat_password' => $password,
                'email'        => $this->getField($rsp->{'email'}),
                'major'        => null,
                'college'      => null,
            );
            return $profile;
        } else {
            return null;
        }
    }
} 
