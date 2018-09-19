<?php
/**
 * Record Tab Factory Class
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
namespace VuFind\RecordTab;

use Zend\ServiceManager\ServiceManager;

/**
 * Record Tab Factory Class
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Factory for CollectionHierarchyTree tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return CollectionHierarchyTree
     */
    public static function getCollectionHierarchyTree(ServiceManager $sm)
    {
        return new CollectionHierarchyTree(
            $sm->get('VuFind\Config\PluginManager')->get('config'),
            $sm->get('VuFind\Record\Loader')
        );
    }

    /**
     * Factory for CollectionList tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return CollectionList
     */
    public static function getCollectionList(ServiceManager $sm)
    {
        return new CollectionList(
            $sm->get('VuFind\Search\SearchRunner'),
            $sm->get('VuFind\Recommend\PluginManager')
        );
    }

    /**
     * Factory for Excerpt tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Excerpt
     */
    public static function getExcerpt(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        // Only instantiate the loader if the feature is enabled:
        if (isset($config->Content->excerpts)) {
            $loader = $sm->get('VuFind\Content\PluginManager')
                ->get('excerpts');
        } else {
            $loader = null;
        }
        return new Excerpt($loader, static::getHideSetting($config, 'excerpts'));
    }

    /**
     * Support method for construction of AbstractContent objects -- should we
     * hide this tab if it is empty?
     *
     * @param \Zend\Config\Config $config VuFind configuration
     * @param string              $tab    Name of tab to check config for
     *
     * @return bool
     */
    protected static function getHideSetting(\Zend\Config\Config $config, $tab)
    {
        // TODO: can we move this code out of the factory so it's more easily reused?
        $setting = isset($config->Content->hide_if_empty)
            ? $config->Content->hide_if_empty : false;
        if ($setting === true || $setting === false
            || $setting === 1 || $setting === 0
        ) {
            return (bool)$setting;
        }
        if ($setting === 'true' || $setting === '1') {
            return true;
        }
        $hide = array_map('trim', array_map('strtolower', explode(',', $setting)));
        return in_array(strtolower($tab), $hide);
    }

    /**
     * Factory for HierarchyTree tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HierarchyTree
     */
    public static function getHierarchyTree(ServiceManager $sm)
    {
        return new HierarchyTree(
            $sm->get('VuFind\Config\PluginManager')->get('config')
        );
    }

    /**
     * Factory for HoldingsILS tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HoldingsILS
     */
    public static function getHoldingsILS(ServiceManager $sm)
    {
        // If VuFind is configured to suppress the holdings tab when the
        // ILS driver specifies no holdings, we need to pass in a connection
        // object:
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        $catalog = ($config->Site->hideHoldingsTabWhenEmpty ?? false)
            ? $sm->get('VuFind\ILS\Connection') : null;
        return new HoldingsILS(
            $catalog,
            (string)($config->Site->holdingsTemplate ?? 'standard')
        );
    }

    /**
     * Factory for HoldingsWorldCat tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HoldingsWorldCat
     */
    public static function getHoldingsWorldCat(ServiceManager $sm)
    {
        $bm = $sm->get('VuFind\Search\BackendManager');
        return new HoldingsWorldCat($bm->get('WorldCat')->getConnector());
    }

    /**
     * Factory for Map tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Map
     */
    public static function getMap(ServiceManager $sm)
    {
        // get Map Tab config options
        $mapTabConfig = $sm->get('VuFind\GeoFeatures\MapTabConfig');
        $mapTabOptions = $mapTabConfig->getMapTabOptions();
        $mapTabDisplay = $mapTabOptions['recordMap'];

        // add basemap options
        $basemapConfig = $sm->get('VuFind\GeoFeatures\BasemapConfig');
        $basemapOptions = $basemapConfig->getBasemap('MapTab');

        return new Map($mapTabDisplay, $basemapOptions, $mapTabOptions);
    }

    /**
     * Factory for Preview tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Preview
     */
    public static function getPreview(ServiceManager $sm)
    {
        $cfg = $sm->get('VuFind\Config\PluginManager')->get('config');
        // currently only active if config [content] [previews] contains google
        // and googleoptions[tab] is not empty.
        $active = false;
        if (isset($cfg->Content->previews)) {
            $previews = array_map(
                'trim', explode(',', strtolower($cfg->Content->previews))
            );
            if (in_array('google', $previews)
                && isset($cfg->Content->GoogleOptions['tab'])
                && strlen(trim($cfg->Content->GoogleOptions['tab'])) > 0
            ) {
                $active = true;
            }
        }
        return new Preview($active);
    }

    /**
     * Factory for SimilarItems tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SimilarItemsCarousel
     */
    public static function getSimilarItemsCarousel(ServiceManager $sm)
    {
        return new SimilarItemsCarousel($sm->get('VuFindSearch\Service'));
    }

    /**
     * Factory for Reviews tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Reviews
     */
    public static function getReviews(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        // Only instantiate the loader if the feature is enabled:
        if (isset($config->Content->reviews)) {
            $loader = $sm->get('VuFind\Content\PluginManager')
                ->get('reviews');
        } else {
            $loader = null;
        }
        return new Reviews($loader, static::getHideSetting($config, 'reviews'));
    }

    /**
     * Factory for TOC tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return TablesOfContents
     */
    public static function getTOC(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        // Only instantiate the loader if the feature is enabled:
        if (isset($config->Content->toc)) {
            $loader = $sm->get('VuFind\Content\PluginManager')
                ->get('toc');
        } else {
            $loader = null;
        }
        return new TOC($loader, static::getHideSetting($config, 'toc'));
    }

    /**
     * Factory for UserComments tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return UserComments
     */
    public static function getUserComments(ServiceManager $sm)
    {
        $capabilities = $sm->get('VuFind\Config\AccountCapabilities');
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        $useRecaptcha = isset($config->Captcha) && isset($config->Captcha->forms)
            && (trim($config->Captcha->forms) === '*'
            || strpos($config->Captcha->forms, 'userComments') !== false);
        return new UserComments(
            'enabled' === $capabilities->getCommentSetting(),
            $useRecaptcha
        );
    }
}
