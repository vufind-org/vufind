<?php
/**
 * KohaRESTful ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) Josef Moravec, 2016.
 * Copyright (C) Jiri Kozlovsky, 2016.
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
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Josef Moravec <josef.moravec@gmail.com>
 * @author   Jiri Kozlovsky <@>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace VuFind\ILS\Driver;

use PDO, PDOException;
use VuFind\Exception\ILS as ILSException,
    VuFindHttp\HttpServiceAwareInterface as HttpServiceAwareInterface,
    Zend\Log\LoggerAwareInterface as LoggerAwareInterface,
    VuFind\Exception\Date as DateException;

//todo: will extend \VuFind\ILS\Driver\AbstractBase, this is just for testing and developing purposes
class KohaRESTful extends \VuFind\ILS\Driver\KohaILSDI implements
    HttpServiceAwareInterface, LoggerAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * REST API base URL
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * REST API user userid/login
     *
     * @var string
     */
    protected $apiUserid;

    /**
     * REST API user password
     *
     * @var string
     */
    protected $apiPassword;

    /**
     * Location codes
     *
     * @var array
     */
    protected $locations;

    /**
     * Codes of locations available for pickup
     *
     * @var array
     */
    protected $pickupEnableBranchcodes;

    /**
     * Codes of locations always should be available
     *   - For example reference material or material
     *     not for loan
     *
     * @var array
     */
    protected $availableLocationsDefault;

    /**
     * Default location code
     *
     * @var string
     */
    protected $defaultLocation;

    /**
     * Date converter object
     *
     * @var \VuFind\Date\Converter
     */
    //protected $dateConverter;


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

        // Storing the base URL of ILS
        $this->apiUrl = isset($this->config['Catalog']['apiurl'])
            ? $this->config['Catalog']['url'] : "";

        // Storing the base URL of ILS
        $this->apiUserid = isset($this->config['Catalog']['apiuserid'])
            ? $this->config['Catalog']['apiuserid'] : null;

        // Storing the base URL of ILS
        $this->apiPassword = isset($this->config['Catalog']['apiuserpassword'])
            ? $this->config['Catalog']['apiuserpassword'] : null;


        // Default location defined in 'KohaRESTful.ini'
        $this->defaultLocation
            = isset($this->config['Holds']['defaultPickUpLocation'])
            ? $this->config['Holds']['defaultPickUpLocation'] : null;

        $this->pickupEnableBranchcodes
            = isset($this->config['Holds']['pickupLocations'])
            ? $this->config['Holds']['pickupLocations'] : [];

        // Locations that should default to available, defined in 'KohaRESTful.ini'
        $this->availableLocationsDefault
            = isset($this->config['Other']['availableLocations'])
            ? $this->config['Other']['availableLocations'] : [];

        // Create a dateConverter
        //$this->dateConverter = new \VuFind\Date\Converter;
    }

   /**
     * Make Request
     *
     * Makes a request to the Koha ILSDI API
     *
     * @param string $apiQuery   Query string for request (starts with "/")
     * @param string $httpMethod HTTP method (default = GET)
     * @param array  $data        If method is PUT or POST, provide needed data i this paramater (default = null)
     *
     * @throws ILSException
     * @return array
     */
    protected function makeRequest($apiQuery, $httpMethod = "GET", $data = null)
    {
        $kohaDate = date("r"), // RFC 1123/2822
        $signature = implode(" ", [(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? "HTTPS" : "HTTP",
                                   $this->apiUserid,
                                   $kohaDate
                     ]);

        $hashedSignature = hash_hmac("sha256", $signature, $this->apiPassword);

        $httpHeaders = [
            "Accept" => "application/json",
            "X-Koha-Date" => $kohaDate,
            "Authorization" => "Koha " . $this->apiUserid . ":" . $hashedSignature ;
        ];

        $client = $this->httpService->createClient($this->apiUrl . $apiQuery, $httpMethod);
        $client->setHeaders($httpHeaders);
        if($data !== null) {
            $client->setRawBody(http_build_query($data));
        }

        try {
            $response = $client->send();
        } catch (\Exception $e) {
            throw new ILSEException($e->getMessage());
        }

        if (!$response->isSuccess()) {
            $this->debug(
                'HTTP status ' . $response->getStatusCode() .
                ' received, accessing Koha RESTful API: ' . $apiQuery
            );
            return false;
        }

        return json_decode($response->getBody(), true);
    }

   /**
    * https://vufind.org/wiki/development:plugins:ils_drivers#getpickuplocations
    */
    public function getPickupLocations($patron = false, $holdDetails = null)
    {
        $libraries = $this->makeRequest("/libraries");
        $locations = [];
        foreach($libraries => $library)
        {
            $locations[] = [
                "locationID" => $library["branchcode"],
                "locationDisplay" => $library["branchname"]
            ];
        }
        return $locations;
    }

}

