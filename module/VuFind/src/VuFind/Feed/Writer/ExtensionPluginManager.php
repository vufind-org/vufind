<?php

/**
 * Feed extension plugin manager.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  Feed_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

declare(strict_types=1);

namespace VuFind\Feed\Writer;

use Laminas\Feed\Writer\Extension\GooglePlayPodcast\Feed;
use Laminas\Feed\Writer\Extension\ITunes\Entry;
use Laminas\Feed\Writer\ExtensionManagerInterface;
use Laminas\Feed\Writer\StandaloneExtensionManager;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\ServiceManager\Factory\InvokableFactory;

use function gettype;
use function is_object;
use function sprintf;
use function substr;

/**
 * Feed extension plugin manager.
 *
 * This only exists for compatibility with Service Manage v4.
 *
 * @category VuFind
 * @package  Feed_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ExtensionPluginManager extends StandaloneExtensionManager implements ExtensionManagerInterface
{
    /**
     * Aliases for default set of extension classes.
     *
     * @var array
     */
    protected array $aliases = [];

    /**
     * Factories for default set of extension classes.
     *
     * @var array
     */
    protected array $factories = [
          \Laminas\Feed\Writer\Extension\Atom\Renderer\Feed::class => InvokableFactory::class,
          \Laminas\Feed\Writer\Extension\Content\Renderer\Entry::class => InvokableFactory::class,
          \Laminas\Feed\Writer\Extension\DublinCore\Renderer\Entry::class => InvokableFactory::class,
          \Laminas\Feed\Writer\Extension\DublinCore\Renderer\Feed::class => InvokableFactory::class,
          \Laminas\Feed\Writer\Extension\GooglePlayPodcast\Entry::class => InvokableFactory::class,
        Feed::class => InvokableFactory::class,
          \Laminas\Feed\Writer\Extension\GooglePlayPodcast\Renderer\Entry::class => InvokableFactory::class,
          \Laminas\Feed\Writer\Extension\GooglePlayPodcast\Renderer\Feed::class => InvokableFactory::class,
        Entry::class => InvokableFactory::class,
          \Laminas\Feed\Writer\Extension\ITunes\Feed::class => InvokableFactory::class,
          \Laminas\Feed\Writer\Extension\ITunes\Renderer\Entry::class => InvokableFactory::class,
          \Laminas\Feed\Writer\Extension\ITunes\Renderer\Feed::class => InvokableFactory::class,
          \Laminas\Feed\Writer\Extension\PodcastIndex\Entry::class => InvokableFactory::class,
          \Laminas\Feed\Writer\Extension\PodcastIndex\Feed::class => InvokableFactory::class,
          \Laminas\Feed\Writer\Extension\PodcastIndex\Renderer\Entry::class => InvokableFactory::class,
          \Laminas\Feed\Writer\Extension\PodcastIndex\Renderer\Feed::class => InvokableFactory::class,
          \Laminas\Feed\Writer\Extension\Slash\Renderer\Entry::class => InvokableFactory::class,
          \Laminas\Feed\Writer\Extension\Threading\Renderer\Entry::class => InvokableFactory::class,
          \Laminas\Feed\Writer\Extension\WellFormedWeb\Renderer\Entry::class => InvokableFactory::class,
    ];

    /**
     * Do not share instances.
     *
     * @var bool
     */
    protected bool $sharedByDefault = false;

    /**
     * Validate an instance.
     *
     * @param mixed $instance Instance
     *
     * @return void
     */
    public function validate(mixed $instance): void
    {
        if (
            ($instance instanceof \Laminas\Feed\Writer\Extension\AbstractRenderer)
            || (is_object($instance) && 'Feed' === substr($instance::class, -4))
            || (is_object($instance) && 'Entry' === substr($instance::class, -5))
        ) {
            // we're okay
            return;
        }

        throw new InvalidServiceException(sprintf(
            'Plugin of type %s is invalid; must implement %s\Extension\RendererInterface '
            . 'or the classname must end in "Feed" or "Entry"',
            is_object($instance) ? $instance::class : gettype($instance),
            __NAMESPACE__
        ));
    }
}
