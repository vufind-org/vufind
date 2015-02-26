<?php
/**
 * Hierarchy Renderer Factory Class
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
 * @package  HierarchyTree_Renderer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace VuFind\Hierarchy\TreeRenderer;
use Zend\ServiceManager\ServiceManager;

/**
 * Hierarchy Renderer Factory Class
 *
 * This is a factory class to build objects for rendering hierarchies.
 *
 * @category VuFind2
 * @package  HierarchyTree_DataSource
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Factory for JSTree renderer.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return JSTree
     */
    public static function getJSTree(ServiceManager $sm)
    {
        return new JSTree(
            $sm->getServiceLocator()->get('ControllerPluginManager')->get('Url')
        );
    }
}