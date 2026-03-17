<?php

/**
 * Section plugin manager.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Section
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Section\Plugin;

use VuFind\Navigation\AccountMenu;
use VuFind\Navigation\AccountMenuFactory;
use VuFind\Navigation\AdminMenu;
use VuFind\Navigation\AdminMenuFactory;
use VuFind\Navigation\FooterMenu;
use VuFind\Navigation\FooterMenuFactory;
use VuFind\Navigation\HeaderBar;
use VuFind\Navigation\HeaderBarFactory;
use VuFind\Navigation\SiteMap;
use VuFind\Navigation\SiteMapFactory;

/**
 * Section plugin manager.
 *
 * @category VuFind
 * @package  Section
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'accountMenu' => AccountMenu::class,
        'adminMenu' => AdminMenu::class,
        'footerMenu' => FooterMenu::class,
        'headerBar' => HeaderBar::class,
        'siteMap' => SiteMap::class,
        // Reserved for future plugins.
        // 'container' => Container::class,
        // 'tabs' => Tabs::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        AccountMenu::class => AccountMenuFactory::class,
        AdminMenu::class => AdminMenuFactory::class,
        FooterMenu::class => FooterMenuFactory::class,
        HeaderBar::class => HeaderBarFactory::class,
        SiteMap::class => SiteMapFactory::class,
        // Reserved for future plugins.
        // Container::class => InvokableFactory::class,
        // Tabs::class => InvokableFactory::class,
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
        // These objects are not meant to be shared -- every time we retrieve one,
        // we are building a brand new object.
        $this->sharedByDefault = false;

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
        return SectionInterface::class;
    }
}
