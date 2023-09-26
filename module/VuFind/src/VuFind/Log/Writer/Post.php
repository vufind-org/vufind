<?php

/**
 * HTTP POST log writer
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Log\Writer;

use Laminas\Http\Client;

use function is_array;

/**
 * This class extends the Laminas Logging to sent POST messages over HTTP
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Post extends \Laminas\Log\Writer\AbstractWriter
{
    use VerbosityTrait;

    /**
     * Holds the verbosity level
     *
     * @var int
     */
    protected $url = null;

    /**
     * Pre-configured http client
     *
     * @var \Laminas\Http\Client
     */
    protected $client = null;

    /**
     * Content type
     *
     * @var string
     */
    protected $contentType = 'application/x-www-form-urlencoded';

    /**
     * Constructor
     *
     * @param string $url    URL to open as a stream
     * @param Client $client Pre-configured http client
     */
    public function __construct($url, Client $client)
    {
        $this->url = $url;
        $this->client = $client;
    }

    /**
     * Set verbosity
     *
     * @param int $type content type string
     *
     * @return void
     */
    public function setContentType($type)
    {
        $this->contentType = $type;
    }

    /**
     * Get data for raw body
     *
     * @param array $event event data
     *
     * @return string
     */
    protected function getBody($event)
    {
        return json_encode(
            ['message' => $this->formatter->format($event) . PHP_EOL]
        );
    }

    /**
     * Write a message to the log.
     *
     * @param array $event event data
     *
     * @return void
     * @throws \Laminas\Log\Exception\RuntimeException
     */
    protected function doWrite(array $event)
    {
        // Apply verbosity filter:
        if (is_array($event['message'])) {
            $event['message'] = $event['message'][$this->verbosity];
        }

        // Create request
        $this->client->setUri($this->url);
        $this->client->setMethod('POST');
        $this->client->setEncType($this->contentType);
        $this->client->setRawBody($this->getBody($this->applyVerbosity($event)));
        // Send
        $this->client->send();
    }
}
