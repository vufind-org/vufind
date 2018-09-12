<?php
/**
 * Factory for instantiating content loaders
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2009.
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
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Content;

use Zend\ServiceManager\ServiceManager;

/**
 * Factory for instantiating content loaders
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Create Author Notes loader
     *
     * @param ServiceManager $sm Service manager
     *
     * @return mixed
     */
    public static function getAuthorNotes(ServiceManager $sm)
    {
        $loader = $sm->get('VuFind\Content\AuthorNotes\PluginManager');
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        $providers = isset($config->Content->authorNotes)
            ? $config->Content->authorNotes : '';
        return new Loader($loader, $providers);
    }

    /**
     * Create Excerpts loader
     *
     * @param ServiceManager $sm Service manager
     *
     * @return mixed
     */
    public static function getExcerpts(ServiceManager $sm)
    {
        $loader = $sm->get('VuFind\Content\Excerpts\PluginManager');
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        $providers = isset($config->Content->excerpts)
            ? $config->Content->excerpts : '';
        return new Loader($loader, $providers);
    }

    /**
     * Create Reviews loader
     *
     * @param ServiceManager $sm Service manager
     *
     * @return mixed
     */
    public static function getReviews(ServiceManager $sm)
    {
        $loader = $sm->get('VuFind\Content\Reviews\PluginManager');
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        $providers = isset($config->Content->reviews)
            ? $config->Content->reviews : '';
        return new Loader($loader, $providers);
    }

    /**
     * Create Summaries loader
     *
     * @param ServiceManager $sm Service manager
     *
     * @return mixed
     */
    public static function getSummaries(ServiceManager $sm)
    {
        $loader = $sm->get('VuFind\Content\Summaries\PluginManager');
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        $providers = isset($config->Content->summaries)
            ? $config->Content->summaries : '';
        return new Loader($loader, $providers);
    }

    /**
     * Create TOC loader
     *
     * @param ServiceManager $sm Service manager
     *
     * @return mixed
     */
    public static function getTOC(ServiceManager $sm)
    {
        $loader = $sm->get('VuFind\Content\TOC\PluginManager');
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        $providers = isset($config->Content->toc)
            ? $config->Content->toc : '';
        return new Loader($loader, $providers);
    }
}
