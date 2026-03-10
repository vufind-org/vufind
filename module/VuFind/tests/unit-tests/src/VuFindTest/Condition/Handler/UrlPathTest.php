<?php

/**
 * UrlPath handler test
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Condition\Handler;

use Laminas\Uri\Http;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Condition\Handler\UrlPath;
use VuFind\Http\PhpEnvironment\Request;

/**
 * UrlPath handler test
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class UrlPathTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get request mock.
     *
     * @return MockObject&Request
     */
    protected function getRequestMock(): MockObject&Request
    {
        $httpUriMock = $this->createMock(Http::class);
        $httpUriMock->expects($this->once())->method('getPath')
            ->willReturn('/test/path');

        $requestMock = $this->createMock(Request::class);
        $requestMock->expects($this->once())->method('getUri')
            ->willReturn($httpUriMock);

        return $requestMock;
    }

    /**
     * Test true condition.
     *
     * @return void
     */
    public function testTrueMatching(): void
    {
        $urlPathHandler = new UrlPath($this->getRequestMock());
        $this->assertTrue($urlPathHandler->checkCondition([
            'type' => 'url_path',
            'comparator' => '=',
            'checkedValues' => '/test/path',
        ]));
    }

    /**
     * Test false condition.
     *
     * @return void
     */
    public function testFalseMatching(): void
    {
        $urlPathHandler = new UrlPath($this->getRequestMock());
        $this->assertFalse($urlPathHandler->checkCondition([
            'type' => 'url_path',
            'comparator' => '=',
            'checkedValues' => '/other/path',
        ]));
    }
}
