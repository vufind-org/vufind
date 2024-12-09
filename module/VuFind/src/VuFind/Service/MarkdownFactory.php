<?php

/**
 * Class MarkdownFactory
 *
 * PHP version 8
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */

namespace VuFind\Service;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

use function count;
use function sprintf;

/**
 * VuFind Markdown Service factory.
 *
 * @category VuFind
 * @package  Service
 * @author   Josef Moravec <moravec@mzk.cz>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
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
        'CommonMarkCore' => 'commonmark',
        'DefaultAttributes' => 'default_attributes',
        'DisallowedRawHtml' => 'disallowed_raw_html',
        'ExternalLink' => 'external_link',
        'Footnote' => 'footnote',
        'HeadingPermalink' => 'heading_permalink',
        'Mention' => 'mentions',
        'SmartPunct' => 'smartpunct',
        'Table' => 'table',
        'TableOfContents' => 'table_of_contents',
    ];

    /**
     * Default set of extensions
     *
     * @var string[]
     */
    protected static $defaultExtensions = [
        'Autolink', 'DisallowedRawHtml', 'Strikethrough', 'Table', 'TaskList',
    ];

    /**
     * Markdown processor configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Enabled extensions
     *
     * @var array
     */
    protected $extensions;

    /**
     * Dependency injection container
     *
     * @var ContainerInterface
     */
    protected $container;

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
     * @throws ContainerException&\Throwable if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        $this->config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('markdown')->toArray();
        $this->extensions = isset($this->config['Markdown']['extensions'])
            ? array_map(
                'trim',
                explode(',', $this->config['Markdown']['extensions'])
            )
            : self::$defaultExtensions;
        $this->extensions = array_filter($this->extensions);
        $this->container = $container;

        return new MarkdownConverter($this->getEnvironment());
    }

    /**
     * Get Markdown environment.
     *
     * @return EnvironmentBuilderInterface
     */
    protected function getEnvironment(): EnvironmentBuilderInterface
    {
        $environment = new Environment($this->createConfig());
        $environment->addExtension(new CommonMarkCoreExtension());
        foreach ($this->extensions as $extension) {
            $extensionClass = $this->getExtensionClass($extension);
            // For case, somebody needs to create extension using custom factory, we
            // try to get the object from DI container if possible
            $extensionObject = $this->container->has($extensionClass)
                ? $this->container->get($extensionClass)
                : new $extensionClass();
            $environment->addExtension($extensionObject);
        }
        return $environment;
    }

    /**
     * Get Markdown base config.
     *
     * @return array
     */
    protected function getBaseConfig(): array
    {
        $mainConfig = $this->config['Markdown'] ?? [];
        return [
            'html_input' => $mainConfig['html_input'] ?? 'strip',
            'allow_unsafe_links'
                => (bool)($mainConfig['allow_unsafe_links'] ?? false),
            'max_nesting_level'
                => (int)($mainConfig['max_nesting_level'] ?? \PHP_INT_MAX),
            'renderer' => [
                'block_separator'
                    => $mainConfig['renderer']['block_separator'] ?? "\n",
                'inner_separator'
                    => $mainConfig['renderer']['inner_separator'] ?? "\n",
                'soft_break' => $mainConfig['renderer']['soft_break'] ?? "\n",
            ],
        ];
    }

    /**
     * Get full class name for given extension
     *
     * @param string $extension Extension name
     *
     * @return string
     */
    protected function getExtensionClass(string $extension): string
    {
        $extensionClass = (str_contains($extension, '\\'))
            ? $extension
            : sprintf(
                'League\CommonMark\Extension\%s\%sExtension',
                $extension,
                $extension
            );
        if (!class_exists($extensionClass)) {
            throw new ServiceNotCreatedException(
                sprintf(
                    "Could not create markdown service. Extension '%s' not found",
                    $extension
                )
            );
        }
        return $extensionClass;
    }

    /**
     * Get config for given extension
     *
     * @param string $extension Extension name
     *
     * @return array
     */
    protected function getConfigForExtension(string $extension): array
    {
        if (isset($this->config[$extension])) {
            $configKey = self::$configKeys[$extension]
                ?? $this->config[$extension]['config_key']
                ?? '';
            unset($this->config[$extension]['config_key']);
            return $configKey !== ''
                ? [ $configKey => $this->config[$extension] ]
                : [];
        }
        return [];
    }

    /**
     * Get config for core extension
     *
     * @return array
     */
    protected function getConfigForCoreExtension(): array
    {
        $config = $this->getConfigForExtension('CommonMarkCore');
        $configOptions = [
            'enable_em',
            'enable_strong',
            'use_asterisk',
            'use_underscore',
        ];
        foreach ($configOptions as $option) {
            $config['commonmark'][$option]
                = (bool)($config['commonmark'][$option]
                    ?? $this->config['Markdown'][$option]
                    ?? true);
            unset($this->config['Markdown'][$option]);
        }
        $markdown = $this->config['Markdown'] ?? [];
        $config['commonmark']['unordered_list_markers']
            ??= $markdown['unordered_list_markers']
            ?? ['-', '*', '+'];
        unset($this->config['Markdown']['unordered_list_markers']);

        return $config;
    }

    /**
     * Sanitize some config options
     *
     * @param array $config Full config
     *
     * @return array
     */
    protected function sanitizeConfig(array $config): array
    {
        $boolSettingKeys = [
            ['external_link', 'open_in_new_window'],
            ['footnote', 'container_add_hr'],
            ['heading_permalink', 'aria_hidden'],
            ['heading_permalink', 'apply_id_to_heading'],
        ];
        foreach ($boolSettingKeys as $key) {
            if (isset($config[$key[0]][$key[1]])) {
                $config[$key[0]][$key[1]] = (bool)$config[$key[0]][$key[1]];
            }
        }
        if (isset($config['table']['wrap']['enabled'])) {
            $config['table']['wrap']['enabled']
                = (bool)$config['table']['wrap']['enabled'];
        }
        $intSettingKeys = [
            ['table_of_contents', 'min_heading_level'],
            ['table_of_contents', 'max_heading_level'],
            ['heading_permalink', 'min_heading_level'],
            ['heading_permalink', 'max_heading_level'],
        ];
        foreach ($intSettingKeys as $key) {
            if (isset($config[$key[0]][$key[1]])) {
                $config[$key[0]][$key[1]] = (int)$config[$key[0]][$key[1]];
            }
        }

        $parseAttributes = function (string $attributes): array {
            $attributes = array_map(
                'trim',
                explode(',', $attributes)
            );
            $attributesArray = [];
            foreach ($attributes as $attribute) {
                $parts = array_map('trim', explode(':', $attribute));
                if (2 === count($parts)) {
                    $attributesArray[$parts[0]] = $parts[1];
                }
            }
            return $attributesArray;
        };
        $attributesConfigKeys = [
            ['wrap', 'attributes'],
            ['alignment_attributes', 'left'],
            ['alignment_attributes', 'center'],
            ['alignment_attributes', 'right'],
        ];
        foreach ($attributesConfigKeys as $keys) {
            $config['table'][$keys[0]][$keys[1]] = $parseAttributes($config['table'][$keys[0]][$keys[1]] ?? '');
        }

        return $config;
    }

    /**
     * Create full config for markdown converter
     *
     * @return array
     */
    protected function createConfig(): array
    {
        $baseConfig = $this->getBaseConfig();
        $coreConfig = $this->getConfigForCoreExtension();
        $config = array_merge($baseConfig, $coreConfig);
        foreach ($this->extensions as $extension) {
            $extConfig = $this->getConfigForExtension($extension);
            $config = array_merge($config, $extConfig);
        }
        return $this->sanitizeConfig($config);
    }
}
