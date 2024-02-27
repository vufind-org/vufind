<?php

/**
 * Unit tests for SOLR 3.x error listener.
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

namespace VuFindTest\Search\Solr\V4;

use Laminas\EventManager\Event;
use Laminas\Http\Response;
use PHPUnit\Framework\TestCase;
use VuFind\Search\Solr\V4\ErrorListener;
use VuFindSearch\Backend\Exception\HttpErrorException;

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
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\MockSearchCommandTrait;

    /**
     * Detect parser error response.
     *
     * @return void
     */
    public function testDetectParseError()
    {
        $response  = $this->createResponse('solr4-parse-error');
        $backend   = 'foo';

        $command   = $this->getMockSearchCommand();
        $exception = HttpErrorException::createFromResponse($response);
        $params    = [
            'command'   => $command,
            'error'     => $exception,
        ];
        $event     = new Event(null, null, $params);
        $listener  = new ErrorListener($backend);
        $listener->onSearchError($event);
        $this->assertTrue($exception->hasTag(ErrorListener::TAG_PARSER_ERROR));
    }

    /**
     * Detect parser error response.
     *
     * @return void
     */
    public function testDetectUndefinedFieldError()
    {
        $response = $this->createResponse('solr4-undefined-field-error');
        $backend  = 'foo';

        $command   = $this->getMockSearchCommand();
        $exception = HttpErrorException::createFromResponse($response);
        $params    = [
            'command'   => $command,
            'error'     => $exception,
        ];
        $event     = new Event(null, null, $params);
        $listener  = new ErrorListener($backend);
        $listener->onSearchError($event);
        $this->assertTrue($exception->hasTag(ErrorListener::TAG_PARSER_ERROR));
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
        return Response::fromString(
            $this->getFixture('response/solr/' . $name)
        );
    }
}
