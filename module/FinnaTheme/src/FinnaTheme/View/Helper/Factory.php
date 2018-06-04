<?php
/**
 * Factory for FinnaTheme view helpers.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016-2018.
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
        return new HeadLink(
            $sm->get('VuFindTheme\ThemeInfo'),
            Factory::getPipelineConfig($sm),
            $sm->get('Request'),
            $sm->get('VuFind\Cache\Manager'),
            $sm->get('VuFind\DbTablePluginManager')->get('FinnaCache')
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
        return new HeadScript(
            $sm->get('VuFindTheme\ThemeInfo'),
            Factory::getPipelineConfig($sm),
            $sm->get('Request'),
            $sm->get('VuFind\DbTablePluginManager')->get('FinnaCache')
        );
    }

    /**
     * Construct the InlineScript helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return InlineScript
     */
    public static function getInlineScript(ServiceManager $sm)
    {
        return new InlineScript(
            $sm->get('VuFindTheme\ThemeInfo'),
            Factory::getPipelineConfig($sm)
        );
    }
}
