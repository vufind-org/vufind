<?php
/**
 * Factory for Root view helpers.
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
namespace VuFind\View\Helper\Root;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for Root view helpers.
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
     * Construct the AddThis helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return AddThis
     */
    public static function getAddThis(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        return new AddThis(
            isset($config->AddThis->key) ? $config->AddThis->key : false
        );
    }

    /**
     * Construct the AccountCapabilities helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return AccountCapabilities
     */
    public static function getAccountCapabilities(ServiceManager $sm)
    {
        return new AccountCapabilities(
            $sm->getServiceLocator()->get('VuFind\AccountCapabilities')
        );
    }

    /**
     * Construct the AlphaBrowse helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return AlphaBrowse
     */
    public static function getAlphaBrowse(ServiceManager $sm)
    {
        return new AlphaBrowse($sm->get('url'));
    }

    /**
     * Construct the Auth helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Auth
     */
    public static function getAuth(ServiceManager $sm)
    {
        return new Auth($sm->getServiceLocator()->get('VuFind\AuthManager'));
    }

    /**
     * Construct the AuthorNotes helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return AuthorNotes
     */
    public static function getAuthorNotes(ServiceManager $sm)
    {
        $loader = $sm->getServiceLocator()->get('VuFind\ContentPluginManager')
            ->get('authornotes');
        return new ContentLoader($loader);
    }

    /**
     * Construct the Cart helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Cart
     */
    public static function getCart(ServiceManager $sm)
    {
        return new Cart($sm->getServiceLocator()->get('VuFind\Cart'));
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
        return new Citation($sm->getServiceLocator()->get('VuFind\DateConverter'));
    }

    /**
     * Construct the DateTime helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return DateTime
     */
    public static function getDateTime(ServiceManager $sm)
    {
        return new DateTime($sm->getServiceLocator()->get('VuFind\DateConverter'));
    }

    /**
     * Construct the DisplayLanguageOption helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return DisplayLanguageOption
     */
    public static function getDisplayLanguageOption(ServiceManager $sm)
    {
        // We want to construct a separate translator instance for this helper,
        // since it configures different language/locale than the core shared
        // instance!
        return new DisplayLanguageOption(
            \VuFind\Service\Factory::getTranslator($sm->getServiceLocator())
        );
    }

    /**
     * Construct the Export helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Export
     */
    public static function getExport(ServiceManager $sm)
    {
        return new Export($sm->getServiceLocator()->get('VuFind\Export'));
    }

    /**
     * Construct the Feedback helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Feedback
     */
    public static function getFeedback(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $enabled = isset($config->Feedback->tab_enabled)
            ? $config->Feedback->tab_enabled : false;
        return new Feedback($enabled);
    }

    /**
     * Construct the Flashmessages helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Flashmessages
     */
    public static function getFlashmessages(ServiceManager $sm)
    {
        $messenger = $sm->getServiceLocator()->get('ControllerPluginManager')
            ->get('FlashMessenger');
        return new Flashmessages($messenger);
    }

    /**
     * Construct the GeoCoords helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return GeoCoords
     */
    public static function getGeoCoords(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('searches');
        $coords = isset($config->MapSelection->default_coordinates)
            ? $config->MapSelection->default_coordinates : false;
        return new GeoCoords($coords);
    }

    /**
     * Construct the GoogleAnalytics helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return GoogleAnalytics
     */
    public static function getGoogleAnalytics(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $key = isset($config->GoogleAnalytics->apiKey)
            ? $config->GoogleAnalytics->apiKey : false;
        $universal = isset($config->GoogleAnalytics->universal)
            ? $config->GoogleAnalytics->universal : false;
        return new GoogleAnalytics($key, $universal);
    }

    /**
     * Construct the Permission helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Permission
     */
    public static function getPermission(ServiceManager $sm)
    {
        $ld = new Permission(
            $sm->getServiceLocator()->get('VuFind\Role\PermissionManager'),
            $sm->getServiceLocator()->get('VuFind\Role\PermissionDeniedManager')
        );
        return $ld;
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
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $url = isset($config->Piwik->url) ? $config->Piwik->url : false;
        $options = [
            'siteId' => isset($config->Piwik->site_id) ? $config->Piwik->site_id : 1,
            'searchPrefix' => isset($config->Piwik->searchPrefix)
                ? $config->Piwik->searchPrefix : null
        ];
        $customVars = isset($config->Piwik->custom_variables)
            ? $config->Piwik->custom_variables
            : false;
        $request = $sm->getServiceLocator()->get('Request');
        $router = $sm->getServiceLocator()->get('Router');
        return new Piwik($url, $options, $customVars, $router, $request);
    }

    /**
     * Construct the HelpText helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HelpText
     */
    public static function getHelpText(ServiceManager $sm)
    {
        $lang = $sm->getServiceLocator()->has('VuFind\Translator')
            ? $sm->getServiceLocator()->get('VuFind\Translator')->getLocale()
            : 'en';
        return new HelpText($sm->get('context'), $lang);
    }

    /**
     * Construct the HistoryLabel helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HistoryLabel
     */
    public static function getHistoryLabel(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $config = isset($config->SearchHistoryLabels)
            ? $config->SearchHistoryLabels->toArray() : [];
        return new HistoryLabel($config, $sm->get('transesc'));
    }

    /**
     * Construct the Ils helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Ils
     */
    public static function getIls(ServiceManager $sm)
    {
        return new Ils($sm->getServiceLocator()->get('VuFind\ILSConnection'));
    }

    /**
     * Construct the JsTranslations helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return JsTranslations
     */
    public static function getJsTranslations(ServiceManager $sm)
    {
        return new JsTranslations($sm->get('transesc'));
    }

    /**
     * Construct the KeepAlive helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return KeepAlive
     */
    public static function getKeepAlive(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        return new KeepAlive(
            isset($config->Session->keepAlive) ? $config->Session->keepAlive : 0
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
        $openUrlRules = json_decode(
            file_get_contents(
                \VuFind\Config\Locator::getConfigPath('OpenUrlRules.json')
            ),
            true
        );
        $resolverPluginManager = $sm->getServiceLocator()
            ->get('VuFind\ResolverDriverPluginManager');
        return new OpenUrl(
            $sm->get('context'),
            $openUrlRules,
            $resolverPluginManager,
            isset($config->OpenURL) ? $config->OpenURL : null
        );
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
        return new ProxyUrl(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
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
            $sm->getServiceLocator()->get('VuFind\Recaptcha'),
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
        $helper = new Record(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
        $helper->setCoverRouter(
            $sm->getServiceLocator()->get('VuFind\Cover\Router')
        );
        return $helper;
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
        return new RecordLink($sm->getServiceLocator()->get('VuFind\RecordRouter'));
    }

    /**
     * Construct the Related helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Related
     */
    public static function getRelated(ServiceManager $sm)
    {
        return new Related(
            $sm->getServiceLocator()->get('VuFind\RelatedPluginManager')
        );
    }

    /**
     * Construct the SafeMoneyFormat helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SafeMoneyFormat
     */
    public static function getSafeMoneyFormat(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $defaultCurrency = isset($config->Site->defaultCurrency)
            ? $config->Site->defaultCurrency : null;
        return new SafeMoneyFormat($defaultCurrency);
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
        $config = $sm->getServiceLocator()->get('VuFind\Config');
        $mainConfig = $config->get('config');
        $searchboxConfig = $config->get('searchbox')->toArray();
        $includeAlphaOptions
            = isset($searchboxConfig['General']['includeAlphaBrowse'])
            && $searchboxConfig['General']['includeAlphaBrowse'];
        return new SearchBox(
            $sm->getServiceLocator()->get('VuFind\SearchOptionsPluginManager'),
            $searchboxConfig,
            isset($mainConfig->SearchPlaceholder)
                ? $mainConfig->SearchPlaceholder->toArray() : [],
            $includeAlphaOptions && isset($mainConfig->AlphaBrowse_Types)
                ? $mainConfig->AlphaBrowse_Types->toArray() : []
        );
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
        return new SearchMemory(
            $sm->getServiceLocator()->get('VuFind\Search\Memory')
        );
    }

    /**
     * Construct the SearchOptions helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SearchOptions
     */
    public static function getSearchOptions(ServiceManager $sm)
    {
        return new SearchOptions(
            $sm->getServiceLocator()->get('VuFind\SearchOptionsPluginManager')
        );
    }

    /**
     * Construct the SearchParams helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SearchParams
     */
    public static function getSearchParams(ServiceManager $sm)
    {
        return new SearchParams(
            $sm->getServiceLocator()->get('VuFind\SearchParamsPluginManager')
        );
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
        return new SearchTabs(
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager'),
            $sm->get('url'), $sm->getServiceLocator()->get('VuFind\SearchTabsHelper')
        );
    }

    /**
     * Construct the Summary helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Summaries
     */
    public static function getSummaries(ServiceManager $sm)
    {
        $loader = $sm->getServiceLocator()->get('VuFind\ContentPluginManager')
            ->get('summaries');
        return new ContentLoader($loader);
    }

    /**
     * Construct the SyndeticsPlus helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SyndeticsPlus
     */
    public static function getSyndeticsPlus(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        return new SyndeticsPlus(
            isset($config->Syndetics) ? $config->Syndetics : null
        );
    }

    /**
     * Construct the SystemEmail helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SystemEmail
     */
    public static function getSystemEmail(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        return new SystemEmail(
            isset($config->Site->email) ? $config->Site->email : ''
        );
    }

    /**
     * Construct the UserList helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return UserList
     */
    public static function getUserList(ServiceManager $sm)
    {
        $sessionManager = $sm->getServiceLocator()->get('VuFind\SessionManager');
        $session = new \Zend\Session\Container('List', $sessionManager);
        $capabilities = $sm->getServiceLocator()->get('VuFind\AccountCapabilities');
        return new UserList($session, $capabilities->getListSetting());
    }

    /**
     * Construct the UserTags helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return UserTags
     */
    public static function getUserTags(ServiceManager $sm)
    {
        $capabilities = $sm->getServiceLocator()->get('VuFind\AccountCapabilities');
        return new UserTags($capabilities->getTagSetting());
    }
}
