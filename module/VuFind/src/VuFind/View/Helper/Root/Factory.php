<?php
/**
 * Factory for Root view helpers.
 *
 * PHP version 7
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
     * Construct the AuthorNotes helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return AuthorNotes
     */
    public static function getAuthorNotes(ServiceManager $sm)
    {
        $loader = $sm->get('VuFind\Content\PluginManager')
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
        return new Cart($sm->get('VuFind\Cart'));
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
        return new Citation($sm->get('VuFind\Date\Converter'));
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
        return new DateTime($sm->get('VuFind\Date\Converter'));
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
            \VuFind\Service\Factory::getTranslator($sm)
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
        return new Export($sm->get('VuFind\Export'));
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
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        $enabled = $config->Feedback->tab_enabled ?? false;
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
        $messenger = $sm->get('ControllerPluginManager')
            ->get('Zend\Mvc\Plugin\FlashMessenger\FlashMessenger');
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
        $config = $sm->get('VuFind\GeoFeatures\MapSelectionConfig')
            ->getMapSelectionOptions();
        return new GeoCoords($config['default_coordinates']);
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
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        $key = $config->GoogleAnalytics->apiKey ?? false;
        $universal = $config->GoogleAnalytics->universal ?? false;
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
            $sm->get('VuFind\Role\PermissionManager'),
            $sm->get('VuFind\Role\PermissionDeniedManager')
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
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        $url = $config->Piwik->url ?? false;
        $options = [
            'siteId' => $config->Piwik->site_id ?? 1,
            'searchPrefix' => $config->Piwik->searchPrefix ?? null
        ];
        $customVars = $config->Piwik->custom_variables ?? false;
        $request = $sm->get('Request');
        $router = $sm->get('Router');
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
        $lang = $sm->has('Zend\Mvc\I18n\Translator')
            ? $sm->get('Zend\Mvc\I18n\Translator')->getLocale()
            : 'en';
        $helpers = $sm->get('ViewHelperManager');
        return new HelpText($helpers->get('context'), $lang);
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
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        $config = isset($config->SearchHistoryLabels)
            ? $config->SearchHistoryLabels->toArray() : [];
        $helpers = $sm->get('ViewHelperManager');
        return new HistoryLabel($config, $helpers->get('transEsc'));
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
        return new Ils($sm->get('VuFind\ILS\Connection'));
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
        $helpers = $sm->get('ViewHelperManager');
        return new JsTranslations($helpers->get('transEsc'));
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
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        return new KeepAlive($config->Session->keepAlive ?? 0);
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
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        $openUrlRules = json_decode(
            file_get_contents(
                \VuFind\Config\Locator::getConfigPath('OpenUrlRules.json')
            ),
            true
        );
        $resolverPluginManager = $sm
            ->get('VuFind\Resolver\Driver\PluginManager');
        $helpers = $sm->get('ViewHelperManager');
        return new OpenUrl(
            $helpers->get('context'),
            $openUrlRules,
            $resolverPluginManager,
            $config->OpenURL ?? null
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
            $sm->get('VuFind\Config\PluginManager')->get('config')
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
            $sm->get('VuFind\Service\ReCaptcha'),
            $sm->get('VuFind\Config\PluginManager')->get('config')
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
            $sm->get('VuFind\Config\PluginManager')->get('config')
        );
        $helper->setCoverRouter(
            $sm->get('VuFind\Cover\Router')
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
        return new RecordLink($sm->get('VuFind\Record\Router'));
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
        return new Related($sm->get('VuFind\Related\PluginManager'));
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
        $helper = new ResultFeed();
        $helper->registerExtensions($sm);
        return $helper;
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
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        $defaultCurrency = $config->Site->defaultCurrency ?? null;
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
        $config = $sm->get('VuFind\Config\PluginManager');
        $mainConfig = $config->get('config');
        $searchboxConfig = $config->get('searchbox')->toArray();
        $includeAlphaOptions
            = $searchboxConfig['General']['includeAlphaBrowse'] ?? false;
        return new SearchBox(
            $sm->get('VuFind\Search\Options\PluginManager'),
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
            $sm->get('VuFind\Search\Memory')
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
            $sm->get('VuFind\Search\Options\PluginManager')
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
            $sm->get('VuFind\Search\Params\PluginManager')
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
        $helpers = $sm->get('ViewHelperManager');
        return new SearchTabs(
            $sm->get('VuFind\Search\Results\PluginManager'),
            $helpers->get('url'), $sm->get('VuFind\Search\SearchTabsHelper')
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
        $loader = $sm->get('VuFind\Content\PluginManager')
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
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        return new SyndeticsPlus($config->Syndetics ?? null);
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
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        return new SystemEmail($config->Site->email ?? '');
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
        $sessionManager = $sm->get('Zend\Session\SessionManager');
        $session = new \Zend\Session\Container('List', $sessionManager);
        $capabilities = $sm->get('VuFind\Config\AccountCapabilities');
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
        $capabilities = $sm->get('VuFind\Config\AccountCapabilities');
        return new UserTags($capabilities->getTagSetting());
    }
}
