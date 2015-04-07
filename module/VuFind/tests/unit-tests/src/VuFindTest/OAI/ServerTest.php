<?php

/**
 * OAI-PMH server unit test.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @category Search
 * @package  Service
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/dmj/vf2-proxy
 */
namespace VuFindTest\OAI;

use VuFind\OAI\Server;

/**
 * OAI-PMH server unit test.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @category Search
 * @package  Service
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/dmj/vf2-proxy
 */
class ServerTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test an empty input.
     *
     * @return void
     */
    public function testEmptyInput()
    {
        $server = $this->getServer();
        $this->assertTrue(false !== strpos($server->getResponse(), '<error code="badVerb">Missing Verb Argument</error>'));
    }

    /**
     * Get a server object.
     *
     * @param array  $config  Server configuration
     * @param string $baseURL Server base URL
     * @param array  $params  Incoming query parameters
     *
     * @return Server
     */
    protected function getServer($config = [], $baseURL = 'http://foo',
        $params = []
    ) {
        // Force an email into the configuration if missing; this is required by the
        // server.
        if (!isset($config['Site']['email'])) {
            $config['Site']['email'] = 'fake@example.com';
        }

        return new Server(
            $this->getMockResultsManager(),
            $this->getMockRecordLoader(),
            $this->getMockTableManager(),
            new \Zend\Config\Config($config),
            $baseURL,
            $params
        );
    }

    /**
     * Get a mock results manager
     *
     * @return \VuFind\Search\Results\PluginManager
     */
    protected function getMockResultsManager()
    {
        return $this->getMockBuilder('VuFind\Search\Results\PluginManager')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get a mock record loader
     *
     * @return \VuFind\Record\Loader
     */
    protected function getMockRecordLoader()
    {
        return $this->getMockBuilder('VuFind\Record\Loader')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get a mock table manager
     *
     * @return \VuFind\Db\Table\PluginManager
     */
    protected function getMockTableManager()
    {
        return $this->getMockBuilder('VuFind\Db\Table\PluginManager')
            ->disableOriginalConstructor()
            ->getMock();
    }
}