<?php

/**
 * Class MarkdownFactory
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2020.
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
 * @package  VuFind\Service
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */
namespace VuFind\Service;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;

/**
 * VuFind HTTP Service factory.
 *
 * @category VuFind
 * @package  Service
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class MarkdownFactory implements FactoryInterface
{
    /**
     * Array of config keys for extensions classes
     *
     * @var string[]
     */
    protected static $configKeys = [
        'ExternalLink' => 'external_link',
        'Footnote' => 'footnote',
        'HeadingPermalink' => 'heading_permalink',
        'Mention' => 'mentions',
        'SmartPunct' => 'smartpunct',
        'TableOfContents' => 'table_of_contents',
    ];

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
        ContainerInterface $container, $requestedName, array $options = null
    ) {
        $markdownConfig = $container->get(\VuFind\Config\PluginManager::class)
            ->get('markdown');
        $markdownSection = $markdownConfig->Markdown;
        $environment = Environment::createCommonMarkEnvironment();
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $config = [
            'html_input' => $config->html_input ?? 'strip',
            'allow_unsafe_links' => $config->allow_unsafe_links ?? false,
            'enable_em' => $config->enable_em ?? true,
            'enable_strong' => $config->enable_strong ?? true,
            'use_asterisk' => $config->use_asterisk ?? true,
            'use_underscore' => $config->use_underscore ?? true,
            'unordered_list_markers' => isset($config->unordered_list_markers)
                && $config->unordered_list_markers instanceof \ArrayAccess
                    ? $config->unordered_list_markers->toArray()
                    : ['-', '*', '+'],
            'max_nesting_level' => $config->max_nesting_level ?? \INF,
            'renderer' => [
                'block_separator'
                    => $config->renderer['block_separator'] ?? "\n",
                'inner_separator'
                    => $config->renderer['inner_separator'] ?? "\n",
                'soft_break' => $config->renderer['soft_break'] ?? "\n",
            ],
        ];
        $extensions = isset($markdownSection->extensions)
            ? array_map('trim', explode(',', $markdownSection->extensions)) : [];

        foreach ($extensions as $ext) {
            $extClass = sprintf(
                'League\CommonMark\Extension\%s\%sExtension', $ext, $ext
            );
            $environment->addExtension(new $extClass());
            if (isset($markdownConfig[$ext])) {
                $config[self::$configKeys[$ext]] = $markdownConfig->$ext->toArray();
            }
        }
        return new CommonMarkConverter($config, $environment);
    }
}
