<?php

/**
 * Central class for connecting to Pazpar2 resources used by VuFind.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  Connection
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture Wiki
 */

namespace VuFindSearch\Backend\Pazpar2;

use Laminas\Http\Client;
use Laminas\Http\Request;
use VuFindSearch\Backend\Exception\HttpErrorException;
use VuFindSearch\ParamBag;

use function sprintf;

/**
 * Central class for connecting to resources used by VuFind.
 *
 * @category VuFind
 * @package  Connection
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture Wiki
 */
class Connector implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Base url for searches
     *
     * @var string
     */
    protected $base;

    /**
     * The HTTP_Request object used for REST transactions
     *
     * @var Client
     */
    protected $client;

    /**
     * Session ID
     *
     * @var string
     */
    protected $session = false;

    /**
     * Constructor
     *
     * @param string $base     Base URL for Pazpar2
     * @param Client $client   An HTTP client object
     * @param bool   $autoInit Should we auto-initialize the Pazpar2 connection?
     */
    public function __construct($base, Client $client, $autoInit = false)
    {
        $this->base = $base;
        if (empty($this->base)) {
            throw new \Exception('Missing Pazpar2 base URL.');
        }

        $this->client = $client;
        $this->client->setMethod(Request::METHOD_GET);  // always use GET

        if ($autoInit) {
            $this->init();
        }
    }

    /**
     * Initializes a session. Returns session ID to be used in subsequent requests.
     * Adds session to the base
     *
     * @return session id
     */
    public function init()
    {
        $this->session = false; // clear any existing session
        $session = $this->query('init');
        if (!isset($session->session)) {
            throw new \Exception('Session initialization failed.');
        }
        $this->session = $session->session;
        return $session;
    }

    /**
     * Requests and receives information from pazpar
     *
     * @param string   $command the command to be executed
     * @param ParamBag $data    optional extra data
     *
     * @return SimpleXMLElement Response
     */
    protected function query($command, ParamBag $data = null)
    {
        // If we don't have a session as long as we're not being explicit
        if (!$this->session && $command !== 'init') {
            $this->init();
        }

        // Don't change input when manipulating parameters:
        $params = (null === $data) ? new ParamBag() : clone $data;

        // Add session and command:
        if ($this->session) {
            $params->set('session', $this->session);
        }
        $params->set('command', $command);

        $this->client->setUri($this->base . '?' . implode('&', $params->request()));
        $xmlStr = $this->send($this->client);
        $xml = simplexml_load_string($xmlStr);

        // If our session has expired, start a new session
        if (
            $command !== 'init'
            && $xml->session == $this->session && isset($this->session)
        ) {
            $this->init();
            return $this->query($command, $data);
        }
        return $xml;
    }

    /**
     * Send a request and return the response.
     *
     * @param Client $client Prepare HTTP client
     *
     * @return string Response body
     *
     * @throws \VuFindSearch\Backend\Exception\RemoteErrorException  Server
     * signaled a server error (HTTP 5xx)
     * @throws \VuFindSearch\Backend\Exception\RequestErrorException Server
     * signaled a client error (HTTP 4xx)
     */
    protected function send(Client $client)
    {
        $this->debug(
            sprintf('=> %s %s', $client->getMethod(), $client->getUri())
        );

        $time     = microtime(true);
        $response = $client->send();
        $time     = microtime(true) - $time;

        $this->debug(
            sprintf(
                '<= %s %s',
                $response->getStatusCode(),
                $response->getReasonPhrase()
            ),
            ['time' => $time]
        );

        if (!$response->isSuccess()) {
            throw HttpErrorException::createFromResponse($response);
        }
        return $response->getBody();
    }

    /**
     * Keeps a session alive. An idle session will time out after one minute.
     * The ping command can be used to keep the session alive absent other activity.
     * It is suggested that any browser client have a simple alarm handler
     * which sends a ping every 50 seconds or so once a session has been initialized
     *
     * @return void
     */
    public function ping()
    {
        $this->query('ping');
    }

    /**
     * Retrieves a detailed record.
     * Unlike the show command, this command returns
     * metadata records before merging takes place.
     *
     * @param string $id array of options as described above
     *
     * @return associative array of XML data
     */
    public function record($id)
    {
        return $this->query('record', new ParamBag(['id' => $id]));
    }

    /**
     * Launches a search.
     *
     * Option (default):
     *  - query     : search string ('')
     *  - filter    : setting+operator+args pairs, such as 'pz:id=4|17, pz:id~3'
     *  - limit     : Narrows the search by one or more fields (typically facets)
     *                as name=arg1|arg2| pairs separated by comma (none)
     *  - startrecs : int (0)
     *  - maxrecs   : int (100)
     *
     * TODO: Make the array more useful to get the correct format?
     *
     * @param ParamBag $options array of options as described above
     *
     * @return associative array of XML data
     */
    public function search(ParamBag $options = null)
    {
        return $this->query('search', $options);
    }

    /**
     * Return session id
     *
     * @return session id
     */
    public function session()
    {
        return $this->session;
    }

    /**
     * Applies settings to this session
     * Each setting parameter has the form name[target]=value
     *
     * TODO: Make the array more useful to get the correct format?
     *
     * @param string $settings settings to be sets
     *
     * @return bool Success/failure status
     */
    public function settings($settings = false)
    {
        if ($settings === false) {
            return false;
        }
        $set = $this->query('settings', $settings);
        return $set->status == 'OK';
    }

    /**
     * Proper alias of results
     *
     * Options (default):
     *  - start : int (0)
     *  - num   : int (20)
     *  - block : 1 = wait until enough records are found (0)
     *  - sort  : column:1 [increasing] or 0 [decreasing] (none)
     *
     * @param ParamBag $options array of options as described above
     *
     * @return array Associative array of XML data
     */
    public function show(ParamBag $options = null)
    {
        return $this->query('show', $options);
    }

    /**
     * Provides status information about an ongoing search.
     *
     * @return associative array of XML data
     */
    public function stat()
    {
        return $this->query('stat');
    }

    /**
     * Retrieves term list(s).
     *
     * Options (default):
     *  - name : comma-separated list of termlist names (all termlists)
     *  - num  : maximum number of entries to return (15)
     *
     * @param ParamBag $options array of options as described above
     *
     * @return array Associative array of XML data
     */
    public function termlist(ParamBag $options = null)
    {
        return $this->query('termlist', $options);
    }

    /**
     * Returns information about the status of each active client.
     *
     * @param string $id client id
     *
     * @return array Associative array of XML data
     */
    public function bytarget($id)
    {
        return $this->query('bytarget', new ParamBag(['id' => $id]));
    }
}
