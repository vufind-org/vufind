<?php
/**
 * Factory for instantiating content loaders
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Content\Reviews;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for instantiating content loaders
 *
 * @category VuFind2
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Create either a Syndetics or SyndeticsPlus loader
     *
     * @param ServiceManager $sm   Service manager
     * @param bool           $plus Instantiate in Syndetics Plus mode?
     *
     * @return mixed
     */
    public static function getAbstractSyndetics(ServiceManager $sm, $plus)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        return new Syndetics(
            isset($config->Syndetics->use_ssl) && $config->Syndetics->use_ssl,
            $plus,
            isset($config->Syndetics->timeout) ? $config->Syndetics->timeout : 10
        );
    }

    /**
     * Create Amazon loader
     *
     * @param ServiceManager $sm Service manager
     *
     * @return mixed
     */
    public static function getAmazon(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $associate = isset($config->Content->amazonassociate)
            ? $config->Content->amazonassociate : null;
        $secret = isset($config->Content->amazonsecret)
            ? $config->Content->amazonsecret : null;
        $label = $sm->getServiceLocator()->get('VuFind\Translator')->translate(
            'Supplied by Amazon'
        );
        return new Amazon($associate, $secret, $label);
    }

    /**
     * Create AmazonEditorial loader
     *
     * @param ServiceManager $sm Service manager
     *
     * @return mixed
     */
    public static function getAmazonEditorial(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $associate = isset($config->Content->amazonassociate)
            ? $config->Content->amazonassociate : null;
        $secret = isset($config->Content->amazonsecret)
            ? $config->Content->amazonsecret : null;
        $label = $sm->getServiceLocator()->get('VuFind\Translator')->translate(
            'Supplied by Amazon'
        );
        return new AmazonEditorial($associate, $secret, $label);
    }

    /**
     * Create Booksite loader
     *
     * @param ServiceManager $sm Service manager
     *
     * @return mixed
     */
    public static function getBooksite(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $url = isset($config->Booksite->url)
            ? $config->Booksite->url  : 'https://api.booksite.com';
        if (!isset($config->Booksite->key)) {
            throw new \Exception("Booksite 'key' not set in VuFind config");
        }
        return new Booksite($url, $config->Booksite->key);
    }

    /**
     * Create Syndetics loader
     *
     * @param ServiceManager $sm Service manager
     *
     * @return mixed
     */
    public static function getSyndetics(ServiceManager $sm)
    {
        return static::getAbstractSyndetics($sm, false);
    }

    /**
     * Create SyndeticsPlus loader
     *
     * @param ServiceManager $sm Service manager
     *
     * @return mixed
     */
    public static function getSyndeticsPlus(ServiceManager $sm)
    {
        return static::getAbstractSyndetics($sm, true);
    }
}
