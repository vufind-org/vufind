<?php
/**
 * Instance store for obtaining default search options objects.
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
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search;

/**
 * Instance store for obtaining default search options objects.
 *
 * @category VuFind2
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Options
{
    /**
     * Basic get
     *
     * @param string $type The search type of the object to retrieve
     *
     * @return \VuFind\Search\Base\Options
     */
    public static function getInstance($type)
    {
        static $store = array();

        if (!isset($store[$type])) {
            $class = 'VuFind\\Search\\' . $type . '\\Options';
            $store[$type] = new $class();
        }
        return $store[$type];
    }

    /**
     * Extract the name of the search class family from a class name.
     *
     * @param string $className Class name to examine.
     *
     * @return string
     */
    public static function extractSearchClassId($className)
    {
        // Parse identifier out of class name of format VuFind\Search\[id]\Params:
        $class = explode('\\', $className);
        return $class[2];
    }
}