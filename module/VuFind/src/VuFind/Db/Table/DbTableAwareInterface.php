<?php

/**
 * Marker interface for classes that depend on the \VuFind\Db\Table\PluginManager
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Table;

/**
 * Marker interface for classes that depend on the \VuFind\Db\Table\PluginManager
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface DbTableAwareInterface
{
    /**
     * Get the plugin manager. Throw an exception if it is missing.
     *
     * @throws \Exception
     * @return PluginManager
     */
    public function getDbTableManager();

    /**
     * Set the plugin manager.
     *
     * @param PluginManager $manager Plugin manager
     *
     * @return void
     */
    public function setDbTableManager(PluginManager $manager);
}
