<?php
/**
 * VuFind "Inject Template" Listener
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
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFindTheme;

/**
 * VuFind "Inject Template" Listener -- this extends the core ZF2 class to adjust
 * default template configurations to something more appropriate for VuFind.
 *
 * @category VuFind2
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
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
     * Determine the top-level namespace of the controller
     *
     * @param string $controller Controller name
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function deriveModuleNamespace($controller)
    {
        // Namespaces just make the theme system more confusing; ignore them:
        return '';
    }
}
