<?php
/**
 * Base class for AjaxHandler tests.
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Unit;

use Laminas\Http\Request;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Stdlib\Parameters;

/**
 * Base class for AjaxHandler tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
abstract class AjaxHandlerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Mock container
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
     * @return \VuFind\Db\Row\User
     */
    protected function getMockUser()
    {
        return $this->container->get(\VuFind\Db\Row\User::class);
    }

    /**
     * Get an auth manager with a value set for isLoggedIn.
     *
     * @param \VuFind\Db\Row\User $user Return value for isLoggedIn()
     *
     * @return \VuFind\Auth\Manager
     */
    protected function getMockAuthManager($user)
    {
        $authManager = $this->container->createMock(
            \VuFind\Auth\Manager::class,
            ['isLoggedIn']
        );
        $authManager->expects($this->any())->method('isLoggedIn')
            ->will($this->returnValue($user));
        return $authManager;
    }

    /**
     * Build a Params helper for testing.
     *
     * @param array $get  GET parameters
     * @param array $post POST parameters
     *
     * @return Params
     */
    protected function getParamsHelper($get = [], $post = [])
    {
        $params = new Params();
        $request = new Request();
        $request->setQuery(new Parameters($get));
        $request->setPost(new Parameters($post));
        $controller = $this->container->createMock(
            'Laminas\Mvc\Controller\AbstractActionController',
            ['getRequest']
        );
        $controller->expects($this->any())->method('getRequest')
            ->will($this->returnValue($request));
        $params->setController($controller);
        return $params;
    }
}
