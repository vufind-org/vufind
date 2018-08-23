<?php
/**
 * Reviews content loader plugin manager
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
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
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
namespace VuFind\Content\Reviews;

/**
 * Reviews content loader plugin manager
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'amazon' => 'VuFind\Content\Reviews\Amazon',
        'amazoneditorial' => 'VuFind\Content\Reviews\AmazonEditorial',
        'booksite' => 'VuFind\Content\Reviews\Booksite',
        'demo' => 'VuFind\Content\Reviews\Demo',
        'guardian' => 'VuFind\Content\Reviews\Guardian',
        'syndetics' => 'VuFind\Content\Reviews\Syndetics',
        'syndeticsplus' => 'VuFind\Content\Reviews\SyndeticsPlus',
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        'VuFind\Content\Reviews\Amazon' => 'VuFind\Content\AbstractAmazonFactory',
        'VuFind\Content\Reviews\AmazonEditorial' =>
            'VuFind\Content\AbstractAmazonFactory',
        'VuFind\Content\Reviews\Booksite' =>
            'VuFind\Content\Reviews\BooksiteFactory',
        'VuFind\Content\Reviews\Demo' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Content\Reviews\Guardian' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Content\Reviews\Syndetics' =>
            'VuFind\Content\AbstractSyndeticsFactory',
        'VuFind\Content\Reviews\SyndeticsPlus' =>
            'VuFind\Content\AbstractSyndeticsFactory',
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return 'VuFind\Content\AbstractBase';
    }
}
