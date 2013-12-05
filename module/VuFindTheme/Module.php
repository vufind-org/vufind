<?php
/**
 * ZF2 module definition for the VuFind theme system.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2013.
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
 * @link     https://github.com/dmj/vf2-proxy
 */
namespace VuFindTheme;

/**
 * ZF2 module definition for the VuFind theme system.
 *
 * @category VuFind2
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/dmj/vf2-proxy
 */
class Module
{
    /**
     * Get autoloader configuration
     *
     * @return void
     */
    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    /**
     * Return service configuration.
     *
     * @return array
     */
    public function getServiceConfig()
    {
        // @codingStandardsIgnoreStart
        return array(
            'factories' => array(
                'VuFindTheme\ThemeInfo' => function () {
                    return new \VuFindTheme\ThemeInfo(
                        realpath(__DIR__ . '/../../themes'), 'blueprint'
                    );
                }
            ),
            'invokables' => array(
                'VuFindTheme\Mobile' => 'VuFindTheme\Mobile',
                'VuFindTheme\ResourceContainer' => 'VuFindTheme\ResourceContainer',
            ),
        );
        // @codingStandardsIgnoreEnd
    }

    /**
     * Get view helper configuration.
     *
     * @return array
     */
    public function getViewHelperConfig()
    {
        // @codingStandardsIgnoreStart
        return array(
            'factories' => array(
                'headlink' => function ($sm) {
                    return new \VuFindTheme\View\Helper\HeadLink(
                        $sm->getServiceLocator()->get('VuFindTheme\ThemeInfo')
                    );
                },
                'headscript' => function ($sm) {
                    return new \VuFindTheme\View\Helper\HeadScript(
                        $sm->getServiceLocator()->get('VuFindTheme\ThemeInfo')
                    );
                },
                'headthemeresources' => function ($sm) {
                    return new \VuFindTheme\View\Helper\HeadThemeResources(
                        $sm->getServiceLocator()->get('VuFindTheme\ResourceContainer')
                    );
                },
                'imagelink' => function ($sm) {
                    return new \VuFindTheme\View\Helper\ImageLink(
                        $sm->getServiceLocator()->get('VuFindTheme\ThemeInfo')
                    );
                },
                'inlinescript' => function ($sm) {
                    return new \VuFindTheme\View\Helper\InlineScript(
                        $sm->getServiceLocator()->get('VuFindTheme\ThemeInfo')
                    );
                },
                'mobileurl' => function ($sm) {
                    return new \VuFindTheme\View\Helper\MobileUrl(
                        $sm->getServiceLocator()->get('VuFindTheme\Mobile')
                    );
                },
            ),
        );
        // @codingStandardsIgnoreEnd
    }

    /**
     * Perform initialization
     *
     * @return void
     */
    public function init()
    {
    }
}
