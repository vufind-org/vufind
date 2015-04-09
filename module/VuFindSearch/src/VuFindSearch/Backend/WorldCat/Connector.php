<?php
/**
 * Class for accessing OCLC WorldCat search API
 *
 * PHP version 5
 *
 * Copyright (C) Andrew Nagy 2008.
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
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFindSearch\Backend\WorldCat;
use VuFindSearch\ParamBag;

/**
 * WorldCat SRU Search Interface
 *
 * @category VuFind2
 * @package  WorldCat
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Connector extends \VuFindSearch\Backend\SRU\Connector
{
    /**
     * OCLC API key
     *
     * @var string
     */
    protected $wskey;

    /**
     * Constructor
     *
     * @param string            $wsKey  Web services key
     * @param \Zend\Http\Client $client An HTTP client object
     */
    public function __construct($wsKey, \Zend\Http\Client $client)
    {
        parent::__construct(
            'http://www.worldcat.org/webservices/catalog/search/sru', $client
        );
        $this->wskey = $wsKey;
    }

    /**
     * Get holdings information for the specified record.
     *
     * @param string $id Record to obtain holdings for.
     *
     * @throws \Exception
     * @return SimpleXMLElement
     */
    public function getHoldings($id)
    {
        $this->client->resetParameters();
        $uri = "http://www.worldcat.org/webservices/catalog/content/libraries/{$id}";
        $uri .= "?wskey={$this->wskey}&servicelevel=full";
        $this->client->setUri($uri);
        $this->debug('Connect: ' . $uri);
        $result = $this->client->setMethod('POST')->send();
        $this->checkForHttpError($result);

        return simplexml_load_string($result->getBody());
    }

    /**
     * Retrieve a specific record.
     *
     * @param string   $id     Record ID to retrieve
     * @param ParamBag $params Parameters
     *
     * @throws \Exception
     * @return string    MARC XML
     */
    public function getRecord($id, ParamBag $params = null)
    {
        $params = $params ?: new ParamBag();
        $params->set('servicelevel', 'full');
        $params->set('wskey', $this->wskey);

        $this->client->resetParameters();
        $uri = 'http://www.worldcat.org/webservices/catalog/content/' . $id;
        $uri .= '?' . implode('&', $params->request());
        $this->client->setUri($uri);
        $this->debug('Connect: ' . $uri);
        $result = $this->client->setMethod('POST')->send();
        $this->checkForHttpError($result);

        // Check for error message in response:
        $body = $result->getBody();
        $xml = simplexml_load_string($body);
        $error = isset($xml->diagnostic);

        return [
            'docs' => $error ? [] : [$body],
            'offset' => 0,
            'total' => $error ? 0 : 1
        ];
    }

    /**
     * Execute a search.
     *
     * @param ParamBag $params Parameters
     * @param integer  $offset Search offset
     * @param integer  $limit  Search limit
     *
     * @return string
     */
    public function search(ParamBag $params, $offset, $limit)
    {
        $params->set('startRecord', $offset);
        $params->set('maximumRecords', $limit);
        $params->set('servicelevel', 'full');
        $params->set('wskey', $this->wskey);

        $response = $this->call('POST', $params->getArrayCopy(), false);

        $xml = simplexml_load_string($response);
        $docs = isset($xml->records->record) ? $xml->records->record : [];
        $finalDocs = [];
        foreach ($docs as $doc) {
            $finalDocs[] = $doc->recordData->asXML();
        }
        return [
            'docs' => $finalDocs,
            'offset' => $offset,
            'total' => isset($xml->numberOfRecords) ? (int)$xml->numberOfRecords : 0
        ];
    }
}