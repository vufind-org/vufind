<?php
/**
 * VuFind Theme Support Methods
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
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Theme;
use Zend\Session\Container as SessionContainer;

/**
 * VuFind Theme Support Methods
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Tools
{
    /**
     * Get the base directory for themes.
     *
     * @return string
     */
    public static function getBaseDir()
    {
        return APPLICATION_PATH . '/themes/vufind';
    }

    /**
     * Get the container used for persisting session-related settings.
     *
     * @return SessionContainer
     */
    public static function getPersistenceContainer()
    {
        static $container = false;
        if (!$container) {
            $container = new SessionContainer('Theme');
        }
        return $container;
    }
}