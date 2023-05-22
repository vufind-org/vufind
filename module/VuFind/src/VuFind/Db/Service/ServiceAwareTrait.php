<?php
/**
 * Default implementation of ServiceAwareInterface.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2023.
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
 * @package  Db_Service
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Db\Service;

/**
 * Default implementation of ServiceAwareInterface.
 *
 * @category VuFind
 * @package  Db_Service
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait ServiceAwareTrait
{
    /**
     * Database service plugin manager
     *
     * @var \VuFind\Db\Service\PluginManager
     */
    protected $serviceManager;

    /**
     * Set the service plugin manager.
     *
     * @param \VuFind\Db\Service\PluginManager $manager Plugin manager
     *
     * @return void
     */
    public function setDbServiceManager(\VuFind\Db\Service\PluginManager $manager)
    {
        $this->serviceManager = $manager;
    }

    /**
     * Get the service plugin manager. Throw an exception if it is missing.
     *
     * @throws \Exception
     * @return \VuFind\Db\Service\PluginManager
     */
    public function getDbServiceManager()
    {
        if (null === $this->serviceManager) {
            throw new \Exception('service manager missing.');
        }
        return $this->serviceManager;
    }

    /**
     * Get a database service object.
     *
     * @param string $name Name of service to retrieve
     *
     * @return \VuFind\Db\Service\AbstractService
     */
    public function getDbService(string $name)
    {
        return $this->getDbServiceManager()->get($name);
    }
}
