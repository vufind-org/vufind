<?php
/**
 * Factory for FinnaTheme view helpers.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016-2017.
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
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace FinnaTheme\View\Helper;

use Zend\ServiceManager\ServiceManager;

/**
 * Factory for VuFindTheme view helpers.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @codeCoverageIgnore
 */
class Factory extends \VuFindTheme\View\Helper\Factory
{
    /**
     * Construct the HeadLink helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HeadLink
     */
    public static function getHeadLink(ServiceManager $sm)
    {
        $locator = $sm->getServiceLocator();
        return new HeadLink(
            $locator->get('VuFindTheme\ThemeInfo'),
            Factory::getPipelineConfig($sm),
            $locator->get('Request'),
            $locator->get('VuFind\Cache\Manager'),
            $locator->get('VuFind\DbTablePluginManager')->get('FinnaCache')
        );
    }

    /**
     * Construct the HeadScript helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HeadScript
     */
    public static function getHeadScript(ServiceManager $sm)
    {
        $locator = $sm->getServiceLocator();
        return new HeadScript(
            $sm->getServiceLocator()->get('VuFindTheme\ThemeInfo'),
            Factory::getPipelineConfig($sm),
            $locator->get('Request'),
            $locator->get('VuFind\DbTablePluginManager')->get('FinnaCache')
        );
    }
}
