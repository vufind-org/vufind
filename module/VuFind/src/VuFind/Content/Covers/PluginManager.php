<?php
/**
 * Covers content loader plugin manager
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
namespace VuFind\Content\Covers;

/**
 * Covers content loader plugin manager
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
        'amazon' => 'VuFind\Content\Covers\Amazon',
        'booksite' => 'VuFind\Content\Covers\Booksite',
        'buchhandel' => 'VuFind\Content\Covers\Buchhandel',
        'browzine' => 'VuFind\Content\Covers\BrowZine',
        'contentcafe' => 'VuFind\Content\Covers\ContentCafe',
        'google' => 'VuFind\Content\Covers\Google',
        'librarything' => 'VuFind\Content\Covers\LibraryThing',
        'localfile' => 'VuFind\Content\Covers\LocalFile',
        'openlibrary' => 'VuFind\Content\Covers\OpenLibrary',
        'summon' => 'VuFind\Content\Covers\Summon',
        'syndetics' => 'VuFind\Content\Covers\Syndetics',
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        'VuFind\Content\Covers\Amazon' => 'VuFind\Content\Covers\Factory::getAmazon',
        'VuFind\Content\Covers\Booksite' =>
            'VuFind\Content\Covers\Factory::getBooksite',
        'VuFind\Content\Covers\BrowZine' => 'VuFind\Content\Covers\BrowZineFactory',
        'VuFind\Content\Covers\Buchhandel' =>
            'VuFind\Content\Covers\Factory::getBuchhandel',
        'VuFind\Content\Covers\ContentCafe' =>
            'VuFind\Content\Covers\Factory::getContentCafe',
        'VuFind\Content\Covers\Google' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Content\Covers\LibraryThing' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Content\Covers\LocalFile' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Content\Covers\OpenLibrary' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Content\Covers\Summon' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Content\Covers\Syndetics' =>
            'VuFind\Content\Covers\Factory::getSyndetics',
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return 'VuFind\Content\AbstractCover';
    }
}
