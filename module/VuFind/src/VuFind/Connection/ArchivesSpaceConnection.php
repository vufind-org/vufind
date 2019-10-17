<?php
/**
 * ArchivesSpace connection class.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2019.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  ArchivesSpace
 * @author   Michelle Suranofsky <michelle.suranofsky@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Connection;

use Zend\Config\Config;
use Zend\Http\Client;

/**
 * ArchivesSpace connection class.
 *
 * @category VuFind
 * @package  ArchivesSpace
 * @author   Michelle Suranofsky <michelle.suranofsky@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ArchivesSpaceConnection implements \Zend\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * HTTP client
     *
     * @var Client
     */
    protected $client;

    /**
     * ArchivesSpace configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * API Session ID
     * required for all ArchivesSpace API interaction
     *
     * @var string
     */
    protected $sessionkey;

    /**
     * Constructor
     *
     * @param Client $client HTTP client
     * @param Config $config ArchivesSpace configuration
     */
    public function __construct(Client $client, Config $config)
    {
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive($findingAid)
    {
        //is the ArchivesSpace Connector enabled in config file?
        if (!$this->config['enabled']) {
            return false;
        }
        //Check the 555 fields for an archivesspace url
        //Compare it with the host name from the archivesspace config file
        $baseurl = $this->config['host'];
        //look for the baseurl in the 555 fields
        $matches = preg_grep("/" . $baseurl . "/", $findingAid);
        return empty($matches) ? false : true;
    }

    /**
     * API call requesting summary information about the finding aid
     *
     * @return httpResponse body
     */
    public function getSummaryInfo($faUrl)
    {
        //THE URL IN THE RECORD (555 FIELD) IS THE PUBLIC
        //URL FOR THIS FINDING AID.
        //EG: http://mylibrary.edu/repositories/7/resources/215
        //THIS CODE GRABS THE END OF IT (/repositories/7/resources/215)
        //& COMBINES IT WITH
        //THE BASE URL FOR THE API - TO DETERMINE THE INITIAL API
        //CALL TO RETREIVE THE FINDING AID SUMMARY
        //BETTER WAY TO DO THIS?
        $host = $this->config['host'];
        $arr = explode($host, $faUrl);
        $resourceurl = $arr[1];
        $baseurl = $this->config['baseapiurl'];
        $url = $baseurl . $resourceurl;
        $resource = $this->callAPI($url);
        return $resource;
    }

    /**
     * authentication & save session id
     *
     * @return void
     */
    public function initSession()
    {
        $baseurl = $this->config['baseapiurl'];
        $userid =  $this->config['userid'];
        $password = $this->config['password'];
        $url = $baseurl . "/users/" . $userid . "/login";
        $client = $this->client;
        $client->setUri($url);
        $client->setMethod('POST');
        $client->setParameterPost(
            [
                'password' => $password
                ]
        );
        $response = $client->send();
        if ($response->isSuccess()) {
            $phpNative = json_decode($response->getBody());
            $sessionid = $phpNative->session;
            $this->sessionkey = $sessionid;
        } else {
            $this->debug(
                'HTTP status ' . $response->getStatusCode() .
                ' received, initializing ArchivesSpace session using ID: ' . $userid
            );
            return false;
        }
    }

    /**
     * calls the ArchivesSpace API
     * using param $url
     *
     * @param string
     *
     * @return httpResponse body
     */
    public function callAPI($url)
    {
        if ($this->sessionkey == null) {
            $this->initSession();
        }
        $client = $this->client;
        $client->setUri($url);
        $client->setMethod('GET');
        $client->setHeaders(['X-ArchivesSpace-Session'=>$this->sessionkey]);
        $client->setOptions(['timeout' => 60]);
        $response = $this->client->send();
        $responseBody =  json_decode($response->getBody());
        return $responseBody;
    }

    /**
     * combines base url with param $url
     * triggers call to api
     *
     * @param string
     *
     * @return stdClass
     */
    public function makeRequestFor($url)
    {
        $baseurl = $this->config['baseapiurl'];
        $combinedUrl = $baseurl . $url;
        $response = $this->callAPI($combinedUrl);
        return $response;
    }
}
