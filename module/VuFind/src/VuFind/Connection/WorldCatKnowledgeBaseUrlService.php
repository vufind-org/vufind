<?php
/**
 * World Cat Knowledge Base URL Service
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  WorldCat
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Connection;
use VuFind\RecordDriver\AbstractBase as RecordDriver,
    VuFindHttp\HttpServiceAwareInterface;

/**
 * World Cat Utilities
 *
 * Class for accessing helpful WorldCat APIs.
 *
 * @category VuFind2
 * @package  WorldCat
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class WorldCatKnowledgeBaseUrlService implements HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Array of record drivers to look up (keyed by ID).
     *
     * @var array
     */
    protected $queue = [];

    /**
     * URLs looked up from record drivers (keyed by ID).
     *
     * @var array
     */
    protected $cache = [];

    /**
     * String for API Key to WorldCat Knowledge base
     * @var string
     */
    protected $worldcatKnowledgeBaseWskey;

    /**
     * Constructor
     *
     * @param VuFind/Config $config                  General config data
     * @param VuFind/Config $worldcatDiscoveryConfig Worldcat Disc. config data
     */
    public function __construct($config, $worldcatDiscoveryConfig)
    {
        if ($worldcatDiscoveryConfig) {
            $this->worldcatKnowledgeBaseWskey
                = $worldcatDiscoveryConfig->General->wskey;
        } else {
            $this->worldcatKnowledgeBaseWskey
                = $config->OpenURL->worldcatKnowledgeBaseWskey;
        }
    }

    /**
     * Add a record driver to the queue of records we should look up (this allows
     * us to save HTTP requests by looking up many URLs at once on a "just in case"
     * basis).
     *
     * @param RecordDriver $record Record driver
     *
     * @return array
     */
    public function addToQueue(RecordDriver $record)
    {
        $id = $record->getUniqueId();
        if (!isset($this->cache[$id])) {
            $this->queue[$id] = $record;
        }
    }

    /**
     * Retrieve an array of URLs for the provided record driver.
     *
     * @param RecordDriver $record Record driver
     *
     * @return array
     */
    public function getUrls(RecordDriver $record)
    {
        $id = $record->getUniqueId();
        if (!isset($this->cache[$id])) {
            $this->addToQueue($record);
            $this->processQueue();
        }
        return $this->cache[$id];
    }

    /**
     * Support method: process the queue of records waiting to be looked up.
     *
     * @return void
     */
    protected function processQueue()
    {
        // Load URLs for queue
        $kbrequest = "http://worldcat.org/webservices/kb/openurl/mresolve?queries=";
        $queries = [];
        foreach ($this->queue as $id => $record) {
            $queries[$id] = $this->_openURLToArray($record->getOpenURL());
        }
        $kbrequest .= json_encode($queries);
        $kbrequest .= '&wskey=' . $this->worldcatKnowledgeBaseWskey;

        $client = $this->httpService
            ->createClient($kbrequest);
        $adapter = new \Zend\Http\Client\Adapter\Curl();
        $client->setAdapter($adapter);
        $result = $client->setMethod('GET')->send();

        if ($result->isSuccess()) {
            $kbresponse = json_decode($result->getBody(), true);
            foreach ($kbresponse as $id => $result) {
                if (isset($result['result'][0]['url'])
                    && isset($result['result'][0]['collection_name'])
                ) {
                    $this->cache[$id] = [
                        [
                            'url' => $result['result'][0]['url'],
                            'desc' => $result['result'][0]['collection_name']
                        ]
                    ];
                } else {
                    $this->cache[$id] = [];
                }
            }
        } else {
            throw new \Exception(
                'WorldCat Knowledge Base API error - ' . $result->getStatusCode()
                . ' - ' . $result->getReasonPhrase()
            );
        }



        // Clear queue
        $this->queue = [];
    }

    /**
     * Parses a url into an associative array of GET parameters
     *
     * @param string $openURL URL to be parsed
     *
     * @return array
     */
    private function _openURLToArray($openURL)
    {
        $parametersPairs = explode('&', $openURL);

        $parameters = [];
        foreach ($parametersPairs as $parametersPair) {
            $pairArray = explode('=', $parametersPair);
            $parameters[$pairArray[0]] = $pairArray[1];
        }
        return $parameters;
    }
}
