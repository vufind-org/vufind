<?php

/**
 * Covers content loader plugin manager.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Content\Covers;

use Laminas\ServiceManager\Factory\InvokableFactory;
use VuFind\Content\ObalkyKnihContentFactory;
use VuFind\ServiceManager\AbstractPluginFactory;

/**
 * Covers content loader plugin manager.
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
        Amazon::class => Deprecated::class,
        Booksite::class => Deprecated::class,
        'amazon' => Deprecated::class,
        'bokinfo' => Bokinfo::class,
        'booksite' => Deprecated::class,
        'buchhandel' => Buchhandel::class,
        'browzine' => BrowZine::class,
        'contentcafe' => ContentCafe::class,
        'demo' => Demo::class,
        'google' => Google::class,
        'koha' => Koha::class,
        'librarything' => LibraryThing::class,
        'localfile' => LocalFile::class,
        'obalkyknih' => ObalkyKnih::class,
        'openlibrary' => OpenLibrary::class,
        'orb' => Orb::class,
        'summon' => Summon::class,
        'syndetics' => Syndetics::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        ObalkyKnih::class => ObalkyKnihContentFactory::class,
    ];

    /**
     * Constructor.
     *
     * Make sure plugins are properly initialized.
     *
     * @param mixed $configOrContainerInstance Configuration or container instance
     * @param array $v3config                  If $configOrContainerInstance is a
     * container, this value will be passed to the parent constructor.
     */
    public function __construct(
        $configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->addAbstractFactory(AbstractPluginFactory::class);
        parent::__construct($configOrContainerInstance, $v3config);
    }

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return \VuFind\Content\AbstractCover::class;
    }
}
