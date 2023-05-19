<?php

/**
 * SOLR 4.x error listener.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Solr\V4;

use Laminas\EventManager\EventInterface;
use Laminas\Http\Response;
use VuFind\Search\Solr\AbstractErrorListener;
use VuFindSearch\Backend\Exception\HttpErrorException;

/**
 * SOLR 3.x error listener.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ErrorListener extends AbstractErrorListener
{
    /**
     * Normalized media types.
     *
     * @var string
     */
    public const TYPE_OTHER = 'other';
    public const TYPE_JSON  = 'json';
    public const TYPE_XML   = 'xml';

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
