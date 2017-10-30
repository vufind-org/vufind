<?php
/**
 * Factory for DB rows.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2014.
 * Copyright (C) The National Library of Finland 2017.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\Db\Row;

use Zend\ServiceManager\ServiceManager;

/**
 * Factory for DB tables.
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @codeCoverageIgnore
 */
class Factory extends \VuFind\Db\Row\Factory
{
    /**
     * Construct a generic row object.
     *
     * @param string         $name Name of row to construct (fully qualified
     * class name, or else a class name within the current namespace)
     * @param ServiceManager $sm   Service manager
     * @param array          $args Extra constructor arguments for row object
     *
     * @return object
     */
    public static function getGenericRow($name, ServiceManager $sm, $args = [])
    {
        // Prepend the current namespace unless we receive a FQCN:
        $class = (strpos($name, '\\') === false)
            ? __NAMESPACE__ . '\\' . $name : $name;
        if (!class_exists($class) && strpos($name, '\\') === false) {
            $class = "\\VuFind\\Db\\Row\\$name";
        }
        if (!class_exists($class)) {
            throw new \Exception('Cannot construct ' . $class);
        }
        $adapter = $sm->getServiceLocator()->get('VuFind\DbAdapter');
        return new $class($adapter, ...$args);
    }
}
