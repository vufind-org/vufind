<?php

/**
 * SOLR error listener.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2013.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Solr;

use Laminas\EventManager\EventInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Http\Response;
use VuFindSearch\Backend\Exception\HttpErrorException;
use VuFindSearch\Service;

use function in_array;

/**
 * SOLR error listener.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ErrorListener
{
    /**
     * Tag indicating a parser error.
     *
     * @var string
     */
    public const TAG_PARSER_ERROR = 'VuFind\Search\ParserError';

    /**
     * Backends to listen for.
     *
     * @var array
     */
    protected $backends;

    /**
     * Normalized media types.
     *
     * @var string
     */
    public const TYPE_OTHER = 'other';
    public const TYPE_JSON  = 'json';
    public const TYPE_XML   = 'xml';

    /**
     * Constructor.
     *
     * @param string $backend Identifier of backend to listen for
     *
     * @return void
     */
    public function __construct(string $backend)
    {
        $this->backends = [];
        $this->addBackend($backend);
    }

    /**
     * Add backend to listen for.
     *
     * @param string $backend Identifier of backend to listen for
     *
     * @return void
     */
    public function addBackend(string $backend)
    {
        if (!$this->listenForBackend($backend)) {
            $this->backends[] = $backend;
        }
    }

    /**
     * Return true if listeners listens for backend errors.
     *
     * @param string $backend Backend identifier
     *
     * @return bool
     */
    public function listenForBackend(string $backend)
    {
        return in_array($backend, $this->backends);
    }

    /**
     * Attach listener to shared event manager.
     *
     * @param SharedEventManagerInterface $manager Shared event manager
     *
     * @return void
     */
    public function attach(SharedEventManagerInterface $manager)
    {
        $manager->attach(
            Service::class,
            Service::EVENT_ERROR,
            [$this, 'onSearchError']
        );
    }

    /**
     * VuFindSearch.error
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchError(EventInterface $event)
    {
        $command = $event->getParam('command');
        if ($this->listenForBackend($command->getTargetIdentifier())) {
            $error = $event->getParam('error');
            if ($error instanceof HttpErrorException) {
                $response = $error->getResponse();

                $body = $response->getBody();
                $type = $this->getResponseBodyMediaType($response);

                if ($type === self::TYPE_JSON) {
                    $body = json_decode($body);
                    if (json_last_error() === \JSON_ERROR_NONE) {
                        $tags = $this->analyzeJsonErrorResponse($body);
                        foreach ($tags as $tag) {
                            $error->addTag($tag);
                        }
                    }
                }
            }
        }
        return $event;
    }

    /// Internal API

    /**
     * Analyze JSON-encoded error response and return appropriate tags.
     *
     * @param StdLib $body Deserialize JSON body
     *
     * @return array Tags
     */
    protected function analyzeJsonErrorResponse($body)
    {
        $tags = [];
        if (isset($body->error->msg)) {
            $reason = $body->error->msg;
            if (
                stristr($reason, 'org.apache.solr.search.SyntaxError')
                || stristr($reason, 'undefined field')
                || stristr($reason, 'invalid date')
            ) {
                $tags[] = self::TAG_PARSER_ERROR;
            }
        }
        return $tags;
    }

    /**
     * Return normalized media type identifier.
     *
     * @param Response $response HTTP response
     *
     * @return string One of `json', `xml', or `other'
     */
    protected function getResponseBodyMediaType(Response $response)
    {
        if ($response->getHeaders()->has('content-type')) {
            $type = $response->getHeaders()->get('content-type')->getFieldValue();
            if (str_starts_with($type, 'application/json')) {
                return self::TYPE_JSON;
            }
            if (str_starts_with($type, 'application/xml')) {
                return self::TYPE_XML;
            }
        }
        return self::TYPE_OTHER;
    }
}
