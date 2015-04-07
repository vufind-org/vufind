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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFindTheme\View\Helper;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for VuFindTheme view helpers.
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
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
            $sm->getServiceLocator()->get('VuFindTheme\ThemeInfo')
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
            $sm->getServiceLocator()->get('VuFindTheme\ThemeInfo')
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
            $sm->getServiceLocator()->get('VuFindTheme\ThemeInfo')
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