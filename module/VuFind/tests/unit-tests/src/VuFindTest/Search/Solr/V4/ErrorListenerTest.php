<?php

/**
 * Unit tests for SOLR 3.x error listener.
 *
 * PHP version 7
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
namespace VuFindTest\Search\Solr\V4;

use PHPUnit\Framework\TestCase;

use RuntimeException;

use VuFind\Search\Solr\V4\ErrorListener;
use VuFindSearch\Backend\Exception\HttpErrorException;

use Zend\EventManager\Event;

use Zend\Http\Response;

/**
 * Unit tests for SOLR 3.x error listener.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ErrorListenerTest extends TestCase
{
    /**
     * Detect parser error response.
     *
     * @return void
     */
    public function testDetectParseError()
    {
        $response  = $this->createResponse('solr4-parse-error');
        $backend   = $this->getMockForAbstractClass('VuFindSearch\Backend\BackendInterface');

        $exception = HttpErrorException::createFromResponse($response);
        $params    = ['backend_instance' => $backend];
        $event     = new Event(null, $exception, $params);
        $listener  = new ErrorListener($backend);
        $listener->onSearchError($event);
        $this->assertTrue($exception->hasTag('VuFind\Search\ParserError'));
    }

    /**
     * Detect parser error response.
     *
     * @return void
     */
    public function testDetectUndefinedFieldError()
    {
        $response = $this->createResponse('solr4-undefined-field-error');
        $backend  = $this->getMockForAbstractClass('VuFindSearch\Backend\BackendInterface');

        $exception = HttpErrorException::createFromResponse($response);
        $params    = ['backend_instance' => $backend];
        $event     = new Event(null, $exception, $params);
        $listener  = new ErrorListener($backend);
        $listener->onSearchError($event);
        $this->assertTrue($exception->hasTag('VuFind\Search\ParserError'));
    }

    /// Internal API

    /**
     * Return response fixture
     *
     * @param string $name Name of fixture
     *
     * @return Response Response
     */
    protected function createResponse($name)
    {
        $file = realpath(
            \VUFIND_PHPUNIT_MODULE_PATH . '/fixtures/response/solr/' . $name
        );
        if (!$file) {
            throw new RuntimeException(
                sprintf('Unable to resolve fixture to fixture file: %s', $name)
            );
        }
        $response = Response::fromString(file_get_contents($file));
        return $response;
    }
}
