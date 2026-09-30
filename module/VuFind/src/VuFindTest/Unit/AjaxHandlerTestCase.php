<?php

/**
 * Base class for AjaxHandler tests.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Unit;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Base class for AjaxHandler tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
abstract class AjaxHandlerTestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Mock container.
     *
     * @var \VuFindTest\Container\MockContainer
     */
    protected $container;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->container = new \VuFindTest\Container\MockContainer($this);
    }

    /**
     * Create mock user object.
     *
     * @return MockObject&UserEntityInterface
     */
    protected function getMockUser(): MockObject&UserEntityInterface
    {
        return $this->container->get(UserEntityInterface::class);
    }

    /**
     * Get an auth manager with a value set for getUserObject.
     *
     * @param ?UserEntityInterface $user Return value for getUserObject()
     *
     * @return MockObject&AuthManager
     */
    protected function getMockAuthManager(?UserEntityInterface $user = null): MockObject&AuthManager
    {
        $authManager = $this->container->createMock(
            \VuFind\Auth\Manager::class,
            ['getUserObject', 'loginEnabled']
        );
        $authManager->method('getUserObject')->willReturn($user);
        $authManager->method('loginEnabled')->willReturn(true);
        return $authManager;
    }

    /**
     * Build a request for testing.
     *
     * @param array   $get     GET parameters
     * @param array   $post    POST parameters
     * @param ?string $content Body content
     *
     * @return ServerRequestInterface
     */
    protected function getRequest(array $get = [], array $post = [], ?string $content = null): ServerRequestInterface
    {
        $request = (new ServerRequest($post ? 'POST' : 'GET', 'http://localhost'))
            ->withQueryParams($get)
            ->withParsedBody($post);
        if (null !== $content) {
            $request->getBody()->write($content);
        }
        return $request;
    }
}
