<?php
/**
 * Factory for Root view helpers.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2018.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

use Zend\ServiceManager\ServiceManager;

/**
 * Factory for Root view helpers.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Construct the Auth helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Auth
     */
    public static function getAuth(ServiceManager $sm)
    {
        return new Auth(
            $sm->get('VuFind\AuthManager'),
            $sm->get('VuFind\ILSAuthenticator'),
            $sm->get('Request')
        );
    }

    /**
     * Construct the authorization notification helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return AuthorizationNotification
     */
    public static function getAuthorizationNote(ServiceManager $sm)
    {
        $authService = $sm->get('ZfcRbac\Service\AuthorizationService');
        return new AuthorizationNotification($authService);
    }

    /**
     * Construct the Autocomplete helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Autocomplete
     */
    public static function getAutocomplete(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config\PluginManager')->get('searches');
        return new Autocomplete($config);
    }

    /**
     * Construct the Barcode helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Barcode
     */
    public static function getBarcode(ServiceManager $sm)
    {
        return new Barcode();
    }

    /**
     * Construct Browse view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Browse
     */
    public static function getBrowse(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('browse');
        return new Browse($config);
    }

    /**
     * Construct the holding callnumber helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Callnumber
     */
    public static function getCallnumber(ServiceManager $sm)
    {
        return new Callnumber($sm->get('Finna\LocationService'));
    }

    /**
     * Construct the CheckboxFacetCounts helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return CheckboxFacetCounts
     */
    public static function getCheckboxFacetCounts(ServiceManager $sm)
    {
        $configReader = $sm->get('VuFind\Config');
        return new CheckboxFacetCounts($configReader);
    }

    /**
     * Construct the Citation helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Citation
     */
    public static function getCitation(ServiceManager $sm)
    {
        return new Citation($sm->get('VuFind\DateConverter'));
    }

    /**
     * Construct combined results view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Combined
     */
    public static function getCombined(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('combined');
        return new Combined($config);
    }

    /**
     * Construct content page view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Content
     */
    public static function getContent(ServiceManager $sm)
    {
        return new Content();
    }

    /**
     * Construct cookie view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Cookie
     */
    public static function getCookie(ServiceManager $sm)
    {
        return new Cookie(
            $sm->get('VuFind\CookieManager')
        );
    }

    /**
     * Construct EDS view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return EDS
     */
    public static function getEDS(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('EDS');
        return new EDS($config);
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
            $sm->get('VuFind\Config')->get('rss')
        );
    }

    /**
     * Construct the FileSrc helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ImageSrc
     */
    public static function getFileSrc(ServiceManager $sm)
    {
        return new FileSrc(
            $sm->get('VuFindTheme\ThemeInfo')
        );
    }

    /**
     * Construct the Finna survey helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return FinnaSurvey
     */
    public static function getFinnaSurvey(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('config');
        return new FinnaSurvey($config);
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
            $sm->get('VuFindTheme\ThemeInfo'),
            $sm->get('Request'),
            $sm->get('VuFind\Cache\Manager')
        );
    }

    /**
     * Construct Headtitle helper
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HeadTitle
     */
    public static function getHeadTitle(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('config');
        return new HeadTitle($config);
    }

    /**
     * Construct the Holdings Details Mode helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HoldingsDetailsMode
     */
    public static function getHoldingsDetailsMode(ServiceManager $sm)
    {
        return new HoldingsDetailsMode($sm->get('VuFind\Config')->get('config'));
    }

    /**
     * Construct the Holdings Details Mode helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HoldingsSettings
     */
    public static function getHoldingsSettings(ServiceManager $sm)
    {
        return new HoldingsSettings($sm->get('VuFind\Config')->get('config'));
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
            $sm->get('VuFindTheme\ThemeInfo')
        );
    }

    /**
     * Construct the LayoutClass helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return LayoutClass
     */
    public static function getLayoutClass(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('config');
        $sidebarOnLeft = !isset($config->Site->sidebarOnLeft)
            ? false : $config->Site->sidebarOnLeft;
        $mirror = !isset($config->Site->mirrorSidebarInRTL)
            ? true : $config->Site->mirrorSidebarInRTL;
        $offcanvas = !isset($config->Site->offcanvas)
            ? false : $config->Site->offcanvas;
        // The right-to-left setting is injected into the layout by the Bootstrapper;
        // pull it back out here to avoid duplicate effort, then use it to apply
        // the mirror setting appropriately.
        $layout = $sm->get('ViewManager')->getViewModel();
        if ($layout->rtl && !$mirror) {
            $sidebarOnLeft = !$sidebarOnLeft;
        }
        return new \Finna\View\Helper\Bootstrap3\LayoutClass(
            $sidebarOnLeft, $offcanvas
        );
    }

    /**
     * Construct the Markdown helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Markdown
     */
    public static function getMarkdown(ServiceManager $sm)
    {
        return new Markdown();
    }

    /**
     * Construct a dummy MetaLib view helper (for legacy code).
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return MetaLib
     */
    public static function getMetaLib(ServiceManager $sm)
    {
        return new MetaLib();
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
        $locator = $sm;
        $menuConfig = $locator->get('VuFind\Config')->get('navibar');
        $organisationInfo = $locator->get('Finna\OrganisationInfo\OrganisationInfo');

        return new Navibar($menuConfig, $organisationInfo);
    }

    /**
     * Construct the OnlinePayment helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Record
     */
    public static function getOnlinePayment(ServiceManager $sm)
    {
        return new OnlinePayment();
    }

    /**
     * Construct the OrganisationDisplayName helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return OrganisationDisplayName
     */
    public static function getOrganisationDisplayName(ServiceManager $sm)
    {
        return new OrganisationDisplayName();
    }

    /**
     * Construct the organisation info helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return OrganisationInfo
     */
    public static function getOrganisationInfo(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('OrganisationInfo');
        return new OrganisationInfo($config);
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
        $config = $sm->get('VuFind\Config')->get('config');
        $file = \VuFind\Config\Locator::getLocalConfigPath('OpenUrlRules.json');
        if ($file === null) {
            $file = \VuFind\Config\Locator::getLocalConfigPath(
                'OpenUrlRules.json', 'config/finna'
            );
            if ($file === null) {
                $file = \VuFind\Config\Locator::getConfigPath('OpenUrlRules.json');
            }
        }
        $openUrlRules = json_decode(file_get_contents($file), true);
        $resolverPluginManager = $sm
            ->get('VuFind\ResolverDriverPluginManager');
        $helpers = $sm->get('ViewHelperManager');
        return new OpenUrl(
            $helpers->get('context'),
            $openUrlRules,
            $resolverPluginManager,
            isset($config->OpenURL) ? $config->OpenURL : null
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
        $locator = $sm;
        $cache = $locator->get('VuFind\CacheManager')->getCache('object');
        $facetHelper = $locator->get('VuFind\HierarchicalFacetHelper');
        $resultsManager = $locator->get('VuFind\SearchResultsPluginManager');
        $organisationInfo = $locator->get('Finna\OrganisationInfo\OrganisationInfo');
        return new OrganisationsList(
            $cache, $facetHelper, $resultsManager, $organisationInfo
        );
    }

    /**
     * Construct the PersonaAuth helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return PersonaAuth
     */
    public static function getPersonaAuth(ServiceManager $sm)
    {
        return new PersonaAuth();
    }

    /**
     * Construct the Piwik helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Piwik
     */
    public static function getPiwik(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('config');
        $url = isset($config->Piwik->url) ? $config->Piwik->url : false;
        $options = [
            'siteId' => isset($config->Piwik->site_id) ? $config->Piwik->site_id : 1,
            'searchPrefix' => isset($config->Piwik->searchPrefix)
                ? $config->Piwik->searchPrefix : null
        ];
        $customVars = isset($config->Piwik->custom_variables)
            ? $config->Piwik->custom_variables
            : false;
        $request = $sm->get('Request');
        $router = $sm->get('Router');
        $translator = $sm->get('VuFind\Translator');
        return new Piwik(
            $url, $options, $customVars, $router, $request, $translator
        );
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
        $config = $sm->get('VuFind\Config')->get('Primo');
        return new Primo($config);
    }

    /**
     * Construct the ProxyUrl helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ProxyUrl
     */
    public static function getProxyUrl(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config');
        return new ProxyUrl(
            $sm->get('VuFind\IpAddressUtils'),
            $config->get('permissions'),
            $config->get('config')
        );
    }

    /**
     * Construct the Recaptcha helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Recaptcha
     */
    public static function getRecaptcha(ServiceManager $sm)
    {
        return new Recaptcha(
            $sm->get('VuFind\Recaptcha'),
            $sm->get('VuFind\Config')->get('config')
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
        $helper = new Record(
            $sm->get('VuFind\RecordLoader'),
            $sm->get('VuFind\Config')->get('config')
        );
        if ('cli' !== php_sapi_name()) {
            $helper->setCoverRouter(
                $sm->get('VuFind\Cover\Router')
            );
        }
        return $helper;
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
     * Construct the RecordLink helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return RecordLink
     */
    public static function getRecordLink(ServiceManager $sm)
    {
        return new RecordLink($sm->get('VuFind\RecordRouter'));
    }

    /**
     * Construct the ResultFeed helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ResultFeed
     */
    public static function getResultFeed(ServiceManager $sm)
    {
        return new ResultFeed();
    }

    /**
     * Construct the ScriptSrc helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ImageSrc
     */
    public static function getScriptSrc(ServiceManager $sm)
    {
        return new ScriptSrc($sm->get('VuFindTheme\ThemeInfo'));
    }

    /**
     * Construct the Search helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Search
     */
    public static function getSearch(ServiceManager $sm)
    {
        return new Search();
    }

    /**
     * Construct the SearchBox helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SearchBox
     */
    public static function getSearchBox(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config');
        $mainConfig = $config->get('config');
        $searchbox = new SearchBox(
            $sm->get('VuFind\SearchOptionsPluginManager'),
            $config->get('searchbox')->toArray(),
            isset($mainConfig->SearchPlaceholder)
                ? $mainConfig->SearchPlaceholder->toArray() : []
        );
        $searchbox->setTabConfig($config->get('config'));
        return $searchbox;
    }

    /**
     * Construct the SearchMemory helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SearchMemory
     */
    public static function getSearchMemory(ServiceManager $sm)
    {
        return new SearchMemory($sm->get('Finna\Search\Memory'));
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
        $helpers = $sm->get('ViewHelperManager');
        return new SearchTabs(
            $sm->get('VuFind\SearchResultsPluginManager'),
            $helpers->get('url'),
            $sm->get('VuFind\SearchTabsHelper'),
            $sm->get('VuFind\SessionManager'),
            $sm->get('VuFind\DbTablePluginManager')
        );
    }

    /**
     * Construct the SearchTabs helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SearchTabs
     */
    public static function getSearchTabsRecommendations(ServiceManager $sm)
    {
        $locator = $sm;
        $config = $locator->get('VuFind\Config')->get('config');
        $recommendationConfig = isset($config->SearchTabsRecommendations)
            ? $config->SearchTabsRecommendations->toArray() : [];
        return new SearchTabsRecommendations($recommendationConfig);
    }

    /**
     * Construct the StreetSearch helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return StreetSearch
     */
    public static function getStreetSearch(ServiceManager $sm)
    {
        return new StreetSearch();
    }

    /**
     * Construct Summon view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Summon
     */
    public static function getSummon(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('Summon');
        return new Summon($config);
    }

    /**
     * Construct the SystemMessages view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\View\Helper\Root\SystemMessage
     */
    public static function getSystemMessages(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('config');
        $localConfig = $sm->get('VuFind\Config')->get('system');
        return new SystemMessages($config, $localConfig);
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
        return new TotalIndexed(
            $sm->get('VuFind\Cache\Manager'),
            $sm->get('VuFind\Search\Results\PluginManager')
        );
    }

    /**
     * Construct the translation view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\View\Helper\Root\Translation
     */
    public static function getTranslation(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('config');
        return new Translation(
            isset($config['Site']['language']) ? $config['Site']['language'] : 'en'
        );
    }

    /**
     * Construct the TranslationEmpty helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return TranslationEmpty
     */
    public static function getTranslationEmpty(ServiceManager $sm)
    {
        return new TranslationEmpty();
    }

    /**
     * Construct the TruncateUrl helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return TruncateUrl
     */
    public static function getTruncateUrl(ServiceManager $sm)
    {
        return new TruncateUrl();
    }

    /**
     * Construct the UserPublicName helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return UserPublicName
     */
    public static function getUserPublicName(ServiceManager $sm)
    {
        return new UserPublicName();
    }
}
