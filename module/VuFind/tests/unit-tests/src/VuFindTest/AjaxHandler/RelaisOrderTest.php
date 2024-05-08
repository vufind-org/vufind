<?php

/**
 * RelaisOrder test class.
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

use VuFind\AjaxHandler\RelaisOrder;

/**
 * RelaisOrder test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RelaisOrderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test authorization failure.
     *
     * @return void
     */
    public function testAuthorizationFailure(): void
    {
        $handler = new RelaisOrder(
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
    public static function authenticatedBehaviorProvider(): array
    {
        return [
            'failure' => ['error: it is broken', ['error: it is broken', 500]],
            'success' => ['success', [['result' => 'success']]],
        ];
    }

    /**
     * Test search response.
     *
     * @param string $response Relais placeRequest response
     * @param array  $expected Expected handler response
     *
     * @return void
     *
     * @dataProvider authenticatedBehaviorProvider
     */
    public function testAuthenticatedBehavior(string $response, array $expected): void
    {
        $user = $this->createMock(\VuFind\Db\Entity\UserEntityInterface::class);
        $user->expects($this->once())->method('getCatUsername')->willReturn('user');
        $params = $this->createMock(\Laminas\Mvc\Controller\Plugin\Params::class);
        $params->expects($this->once())->method('fromQuery')->with('oclcNumber')
            ->willReturn('oclcnum');
        $relais = $this->createMock(\VuFind\Connection\Relais::class);
        $relais->expects($this->once())->method('authenticatePatron')
            ->with('user')
            ->willReturn('authenticated');
        $relais->expects($this->once())->method('placeRequest')
            ->with('oclcnum', 'authenticated', 'user')
            ->willReturn($response);
        $handler = new RelaisOrder(
            $this->createMock(\VuFind\Session\Settings::class),
            $relais,
            $user
        );
        $this->assertEquals($expected, $handler->handleRequest($params));
    }
}
