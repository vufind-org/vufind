<?php
/**
 * Factory for VuFindTheme view helpers.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2014.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFindTheme\View\Helper;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for VuFindTheme view helpers.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Split config and return prefixed setting with current environment.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HeadLink
     */
    protected static function getPipelineConfig(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $default = false;
        if (isset($config['Site']['asset_pipeline'])) {
            $settings = array_map(
                'trim',
                explode(';', $config['Site']['asset_pipeline'])
            );
            foreach ($settings as $setting) {
                $parts = array_map('trim', explode(':', $setting));
                if (APPLICATION_ENV === $parts[0]) {
                    return $parts[1];
                } else if (count($parts) == 1) {
                    $default = $parts[0];
                } else if ($parts[0] === '*') {
                    $default = $parts[1];
                }
            }
        }
        return $default;
    }

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
            $sm->getServiceLocator()->get('VuFindTheme\ThemeInfo'),
            Factory::getPipelineConfig($sm)
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
            $sm->getServiceLocator()->get('VuFindTheme\ThemeInfo'),
            Factory::getPipelineConfig($sm)
        );
    }

    /**
     * Construct the HeadThemeResources helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HeadThemeResources
     */
    public static function getHeadThemeResources(ServiceManager $sm)
    {
        return new HeadThemeResources(
            $sm->getServiceLocator()->get('VuFindTheme\ResourceContainer')
        );
    }

    /**
     * Construct the ImageLink helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ImageLink
     */
    public static function getImageLink(ServiceManager $sm)
    {
        return new ImageLink(
            $sm->getServiceLocator()->get('VuFindTheme\ThemeInfo')
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
            $sm->getServiceLocator()->get('VuFindTheme\ThemeInfo'),
            Factory::getPipelineConfig($sm)
        );
    }

    /**
     * Construct the MobileUrl helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return MobileUrl
     */
    public static function getMobileUrl(ServiceManager $sm)
    {
        return new MobileUrl(
            $sm->getServiceLocator()->get('VuFindTheme\Mobile')
        );
    }
}
