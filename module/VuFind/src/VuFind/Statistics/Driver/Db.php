<?php
/**
 * Database Statistics Driver
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
 * @category VuFind2
 * @package  Statistics
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Statistics\Driver;

/**
 * Writer to put statistics into the DB
 *
 * @category VuFind2
 * @package  Statistics
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Db extends AbstractBase implements \VuFind\Db\Table\DbTableAwareInterface
{
    /**
     * Database table plugin manager
     *
     * @var \VuFind\Db\Table\PluginManager
     */
    protected $tableManager;

    /**
     * Write a message to the log.
     *
     * @param array $data     Data specific to what we're saving
     * @param array $userData Browser, IP, urls, etc
     *
     * @return void
     */
    public function write($data, $userData)
    {
        $this->getTable('UserStatsFields')->save($data, $userData);
    }

    /**
     * Get all the instances of a field.
     *
     * @param string $field What field of data are we researching?
     * @param array  $value Extra options for search. Value => match this value
     *
     * @return array
     */
    public function getFullList($field, $value = array())
    {
        // Use the model
        return $this->getTable('UserStatsFields')->getFields($field, $value)
            ->toArray();
    }

    /**
     * Returns browser usage statistics
     *
     * @param bool    $version Include the version numbers in the list
     * @param integer $limit   How many items to return
     *
     * @return array
     */
    public function getBrowserStats($version, $limit)
    {
        $userStats = $this->getTable('UserStats');
        return $userStats->getBrowserStats($version, $limit);
    }

    /**
     * Get the table plugin manager.  Throw an exception if it is missing.
     *
     * @throws \Exception
     * @return \VuFind\Db\Table\PluginManager
     */
    public function getDbTableManager()
    {
        if (null === $this->tableManager) {
            throw new \Exception('DB table manager missing.');
        }
        return $this->tableManager;
    }

    /**
     * Set the table plugin manager.
     *
     * @param \VuFind\Db\Table\PluginManager $manager Plugin manager
     *
     * @return void
     */
    public function setDbTableManager(\VuFind\Db\Table\PluginManager $manager)
    {
        $this->tableManager = $manager;
    }

    /**
     * Get a database table object.
     *
     * @param string $table Name of table to retrieve
     *
     * @return \VuFind\Db\Table\Gateway
     */
    protected function getTable($table)
    {
        return $this->getDbTableManager()->get($table);
    }
}
