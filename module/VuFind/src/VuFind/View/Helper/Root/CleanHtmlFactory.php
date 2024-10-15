<?php

/**
 * CleanHtml helper factory.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2020-2024.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Closure;
use HTMLPurifier;
use HTMLPurifier_Config;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

/**
 * CleanHtml helper factory.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CleanHtmlFactory implements FactoryInterface
{
    /**
     * Service manager
     *
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * Create an object
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }

        $this->container = $container;
        return new $requestedName(Closure::fromCallable([$this, 'createPurifier']));
    }

    /**
     * Create a purifier instance.
     *
     * N.B. This is a relatively slow method.
     *
     * @param array $options Additional options. Currently supported:
     *   targetBlank  true/false  Whether to add target="_blank" to external links
     *
     * @return HTMLPurifier
     */
    protected function createPurifier(array $options): HTMLPurifier
    {
        $config = \HTMLPurifier_Config::createDefault();
        // Set cache path to the object cache
        $cacheDir
            = $this->container->get(\VuFind\Cache\Manager::class)->getCache('object')->getOptions()->getCacheDir();
        if ($cacheDir) {
            $config->set('Cache.SerializerPath', $cacheDir);
        }
        if ($options['targetBlank'] ?? false) {
            $config->set('HTML.Nofollow', 1);
            $config->set('HTML.TargetBlank', 1);
        }

        // Setting the following option makes purifier’s DOMLex pass the
        // LIBXML_PARSEHUGE option to DOMDocument::loadHtml method. This in turn
        // ensures that PHP calls htmlCtxtUseOptions (see
        // github.com/php/php-src/blob/PHP-8.1.14/ext/dom/document.c#L1870),
        // which ensures that the libxml2 options (namely keepBlanks) are set up
        // properly, and whitespace nodes are preserved. This should not be an
        // issue from libxml2 version 2.9.5, but during testing the issue was
        // still intermittently present. Regardless of that, CentOS 7.x have an
        // older libxml2 that exhibits the issue.
        $config->set('Core.AllowParseManyTags', true);

        $this->setAdditionalConfiguration($config);
        return new \HTMLPurifier($config);
    }

    /**
     * Sets additional configuration
     *
     * @param HTMLPurifier_Config $config Configuration
     *
     * @return void
     */
    protected function setAdditionalConfiguration(HTMLPurifier_Config $config)
    {
        // Add support for details and summary elements:
        $definition = $config->getHTMLDefinition(true);
        $definition->addElement(
            'details',
            'Block',
            'Flow',
            'Common',
            ['open' => new \HTMLPurifier_AttrDef_HTML_Bool(true)]
        );
        $definition->addElement('summary', 'Block', 'Flow', 'Common');
    }
}
