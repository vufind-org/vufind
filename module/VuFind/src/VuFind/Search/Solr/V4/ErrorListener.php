<?php

/**
 * SOLR 4.x error listener.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search\Solr\V4;

use VuFindSearch\Backend\Exception\HttpErrorException;
use VuFind\Search\Solr\AbstractErrorListener;

use Zend\Http\Response;
use Zend\EventManager\EventInterface;

/**
 * SOLR 3.x error listener.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class ErrorListener extends AbstractErrorListener
{

    /**
     * Normalized media types.
     *
     * @var string
     */
    const TYPE_OTHER = 'other';
    const TYPE_JSON  = 'json';
    const TYPE_XML   = 'xml';

    /**
     * VuFindSearch.error
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchError(EventInterface $event)
    {
        $backend = $event->getParam('backend_instance');
        if ($this->listenForBackend($backend)) {
            $error = $event->getTarget();
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
        $tags = array();
        if (isset($body->error->msg)) {
            $reason = $body->error->msg;
            if (stristr($reason, 'org.apache.solr.search.SyntaxError')
                || stristr($reason, 'undefined field')
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
            if (strpos($type, 'application/json') === 0) {
                return self::TYPE_JSON;
            }
            if (strpos($type, 'application/xml') === 0) {
                return self::TYPE_XML;
            }
        }
        return self::TYPE_OTHER;
    }

}