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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for Root view helpers.
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
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
        return new AuthorNotes(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
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
        return new DisplayLanguageOption(
            $sm->getServiceLocator()->get('VuFind\Translator')
        );
    }

    /**
     * Construct the Excerpt helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Excerpt
     */
    public static function getExcerpt(ServiceManager $sm)
    {
        return new Excerpt(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
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
        return new GoogleAnalytics($key);
    }

    /**
     * Construct the GetLastSearchLink helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return GetLastSearchLink
     */
    public static function getGetLastSearchLink(ServiceManager $sm)
    {
        return new GetLastSearchLink(
            $sm->getServiceLocator()->get('VuFind\Search\Memory')
        );
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
            ? $config->SearchHistoryLabels->toArray() : array();
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
     * Construct the Reviews helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Reviews
     */
    public static function getReviews(ServiceManager $sm)
    {
        return new Reviews(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
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
        return new SearchBox(
            $sm->getServiceLocator()->get('VuFind\SearchOptionsPluginManager'),
            $config->get('searchbox')->toArray()
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
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $config = isset($config->SearchTabs)
            ? $config->SearchTabs->toArray() : array();
        return new SearchTabs(
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager'),
            $config, $sm->get('url')
        );
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
     * Construct the VideoClips helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return VideoClips
     */
    public static function getVideoClips(ServiceManager $sm)
    {
        return new VideoClips(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Construct the WorldCat helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return WorldCat
     */
    public static function getWorldCat(ServiceManager $sm)
    {
        $bm = $sm->getServiceLocator()->get('VuFind\Search\BackendManager');
        return new WorldCat($bm->get('WorldCat')->getConnector());
    }
}