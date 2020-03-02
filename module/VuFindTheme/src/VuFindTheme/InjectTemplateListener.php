<?php
/**
 * VuFind "Inject Template" Listener
 *
 * PHP version 7
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
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFindTheme;

/**
 * VuFind "Inject Template" Listener -- this extends the core MVC class to adjust
 * default template configurations to something more appropriate for VuFind.
 *
 * @category VuFind
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class InjectTemplateListener extends \Zend\Mvc\View\Http\InjectTemplateListener
{
    /**
     * Inflect a name to a normalized value
     *
     * @param string $name Name to inflect
     *
     * @return string
     */
    protected function inflectName($name)
    {
        // We want case-insensitive routes, so just lowercase without worrying
        // about converting camelCase:
        return strtolower($name);
    }

    /**
     * Strip namespace part off controller name for compatibility with theme
     * system.
     *
     * @param string $controller controller FQCN
     *
     * @return string|false template name or false if controller was not matched
     */
    public function mapController($controller)
    {
        $initial = parent::mapController($controller);
        $parts = explode('/', $initial);
        array_shift($parts);
        return implode('/', $parts);
    }
}
