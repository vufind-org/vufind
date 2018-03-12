<?php
/**
 * VuFind YAML Configuration Reader
 *
 * PHP version 5.3
 *
 * Copyright (C) 2010 Villanova University,
 *               2018 Leipzig University Library <info@ub.uni-leipzig.de>
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
 * @category   VuFind
 * @package    Config
 * @author     Demian Katz <demian.katz@villanova.edu>
 * @author     Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link       https://vufind.org Main Site
 */
namespace VuFind\Config;

/**
 * VuFind YAML Configuration Reader
 *
 * Please use {@see \VuFind\Config\Manager} instead as this class
 * only exists for backwards compatibility.
 *
 * @category   VuFind
 * @package    Config
 * @author     Demian Katz <demian.katz@villanova.edu>
 * @author     Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link       https://vufind.org Main Site
 * @deprecated File deprecated since X.0.0
 */
class YamlReader
{
    /**
     * @var Manager
     */
    protected $manager;

    /**
     * Constructor
     *
     * @param Manager $manager
     */
    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Return a configuration
     *
     * @param string $filename config file name
     *
     * @return array
     */
    public function get($filename)
    {
        $offset = strlen(pathinfo($filename, PATHINFO_EXTENSION)) + 1;
        $key = trim(substr_replace($filename, '', -$offset), '/');
        return $this->manager->get($key)->toArray();
    }
}
