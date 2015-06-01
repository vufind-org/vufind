<?php
/**
 * Factory for Root view helpers.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for Root view helpers.
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 *
 * @codeCoverageIgnore
 */
class Factory extends \VuFind\View\Helper\Root\Factory
{
    /**
     * Construct the Holdings Details Mode helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HoldingsDetailsMode
     */
    public static function getHoldingsDetailsMode(ServiceManager $sm)
    {
        return new HoldingsDetailsMode(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Construct the Record helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Record
     */
    public static function getRecord(ServiceManager $sm)
    {
        return new Record(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Construct the Navibar view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\View\Helper\Root\Navibar
     */
    public static function getNavibar(ServiceManager $sm)
    {
        $locator = $sm->getServiceLocator();
        $menuConfig = $locator->get('VuFind\Config')->get('navibar');

        return new Navibar($menuConfig);
    }

    /**
     * Construct content page view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Header
     */
    public static function getContent(ServiceManager $sm)
    {
        return new Content();
    }

    /**
     * Construct Primo view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Primo
     */
    public static function getPrimo(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('Primo');
        return new Primo($config);
    }

    /**
     * Construct record image view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Header
     */
    public static function getRecordImage(ServiceManager $sm)
    {
        return new RecordImage();
    }

    /**
     * Construct the SearchTabs helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SearchTabs
     */
    public static function getSearchTabs(ServiceManager $sm)
    {
        $locator = $sm->getServiceLocator();
        $config = $locator->get('VuFind\Config')->get('config');
        $config = isset($config->SearchTabs)
            ? $config->SearchTabs->toArray() : [];
        return new SearchTabs(
            $locator->get('VuFind\SessionManager'),
            $locator->get('VuFind\DbTablePluginManager'),
            $locator->get('VuFind\SearchResultsPluginManager'),
            $config, $sm->get('url')
        );
    }

    /**
     * Construct the OpenUrl helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return OpenUrl
     */
    public static function getOpenUrl(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        return new OpenUrl(
            $sm->get('context'), isset($config->OpenURL) ? $config->OpenURL : null
        );
    }

    /**
     * Construct the Total indexed count view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\View\Helper\Root\TotalIndexed
     */
    public static function getTotalIndexed(ServiceManager $sm)
    {
        $locator = $sm->getServiceLocator();
        return new TotalIndexed($locator);
    }

    /**
     * Construct the PersonaAuth view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\View\Helper\Root\PersonaAuth
     */
    public static function getPersonaAuth(ServiceManager $sm)
    {
        $locator = $sm->getServiceLocator();
        return new PersonaAuth($locator);
    }

    /**
     * Construct the Logout message view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\View\Helper\Root\LogoutMessage
     */
    public static function getLogoutMessage(ServiceManager $sm)
    {
        $authManager = $sm->getServiceLocator()->get('VuFind\AuthManager');
        return new LogoutMessage($authManager);
    }

    /**
     * Construct the Feed component helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Record
     */
    public static function getFeed(ServiceManager $sm)
    {
        return new Feed(
            $sm->getServiceLocator()->get('VuFind\Config')->get('rss')
        );
    }

    /**
     * Construct the Organisations list view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\View\Helper\Root\OrganisationsList
     */
    public static function getOrganisationsList(ServiceManager $sm)
    {
        $locator = $sm->getServiceLocator();
        $cache = $locator->get('VuFind\CacheManager')->getCache('object');
        $facetHelper = $locator->get('VuFind\HierarchicalFacetHelper');
        $resultsManager = $locator->get('VuFind\SearchResultsPluginManager');

        return new OrganisationsList($cache, $facetHelper, $resultsManager);
    }

    /**
     * Construct the ImageSrc helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ImageSrc
     */
    public static function getImageSrc(ServiceManager $sm)
    {
        return new ImageSrc(
            $sm->getServiceLocator()->get('VuFindTheme\ThemeInfo')
        );
    }
}
