<?php

/**
 * RelaisAvailability test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\AjaxHandler;

use VuFind\AjaxHandler\RelaisAvailability;

/**
 * RelaisAvailability test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RelaisAvailabilityTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test authorization failure.
     *
     * @return void
     */
    public function testAuthorizationFailure(): void
    {
        $handler = new RelaisAvailability(
            $this->createMock(\VuFind\Session\Settings::class),
            $this->createMock(\VuFind\Connection\Relais::class),
            null
        );
        $params = $this->createMock(\Laminas\Mvc\Controller\Plugin\Params::class);
        $this->assertEquals(['Failed', 403], $handler->handleRequest($params));
    }

    /**
     * Data provider for testSearchResponse()
     *
     * @return array[]
     */
    public static function searchResponseProvider(): array
    {
        return [
            'error type 1' => ['error: foo', [['result' => 'no']]],
            'error type 2' => ['ErrorMessage: foo', [['result' => 'no']]],
            'error type 3' => ['false', [['result' => 'no']]],
            'success' => ['happy day!', [['result' => 'ok']]],
        ];
    }

    /**
     * Test search response.
     *
     * @param string $response Relais search response
     * @param array  $expected Expected handler response
     *
     * @return void
     *
     * @dataProvider searchResponseProvider
     */
    public function testSearchResponse(string $response, array $expected): void
    {
        $params = $this->createMock(\Laminas\Mvc\Controller\Plugin\Params::class);
        $params->expects($this->once())->method('fromQuery')->with('oclcNumber')
            ->willReturn('oclcnum');
        $relais = $this->createMock(\VuFind\Connection\Relais::class);
        $relais->expects($this->once())->method('authenticatePatron')
            ->willReturn('authorization-id');
        $relais->expects($this->once())->method('search')
            ->with('oclcnum', 'authorization-id')
            ->willReturn($response);
        $handler = new RelaisAvailability(
            $this->createMock(\VuFind\Session\Settings::class),
            $relais,
            null
        );
        $this->assertEquals($expected, $handler->handleRequest($params));
    }
}
