<?php
/**
 * ChoiceAuth test class.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\Auth;
use VuFind\Auth\ChoiceAuth, VuFind\Auth\PluginManager,
    VuFind\Db\Row\User as UserRow, Zend\Config\Config;

/**
 * ChoiceAuth test class.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class ChoiceAuthTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test config validation
     *
     * @return void
     * @expectedException \VuFind\Exception\Auth
     * @expectedExceptionMessage One or more ChoiceAuth parameters are missing.
     */
    public function testBadConfiguration()
    {
        $ca = new ChoiceAuth();
        $ca->setConfig(new Config(array()));
    }

    /**
     * Test default getPluginManager behavior
     *
     * @return void
     * @expectedException \Exception
     * @expectedExceptionMessage Plugin manager missing.
     */
    public function testMissingPluginManager()
    {
        $ca = new ChoiceAuth();
        $ca->getPluginManager();
    }

    /**
     * Get a ChoiceAuth object.
     *
     * @param string $strategies Strategies setting
     *
     * @return ChoiceAuth
     */
    protected function getChoiceAuth($strategies = 'Database,Shibboleth')
    {
        $ca = new ChoiceAuth();
        $ca->setConfig(
            new Config(array('ChoiceAuth' => array('choice_order' => $strategies)))
        );
        $ca->setPluginManager($this->getMockPluginManager());
        return $ca;
    }

    /**
     * Get a mock plugin manager.
     *
     * @return PluginManager
     */
    protected function getMockPluginManager()
    {
        $pm = new PluginManager();
        $mockDb = $this->getMockBuilder('VuFind\Auth\Database')
            ->disableOriginalConstructor()
            ->getMock();
        $mockShib = $this->getMockBuilder('VuFind\Auth\Shibboleth')
            ->disableOriginalConstructor()
            ->getMock();
        $pm->setService('Database', $mockDb);
        $pm->setService('Shibboleth', $mockShib);
        return $pm;
    }

    /**
     * Get a mock user object
     *
     * @return UserRow
     */
    protected function getMockUser()
    {
        return $this->getMockBuilder('VuFind\Db\Row\User')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get a mock request object
     *
     * @return \Zend\Http\PhpEnvironment\Request
     */
    protected function getMockRequest()
    {
        return $this->getMockBuilder('Zend\Http\PhpEnvironment\Request')
            ->disableOriginalConstructor()
            ->getMock();
    }
}