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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */
namespace VuFind\Service;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Laminas\Config\Config;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

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
        'Autolink', 'DisallowedRawHtml', 'Strikethrough', 'Table', 'TaskList'
    ];

    /**
     * Markdown processor configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Enabled extensions
     *
     * @var array
     */
    protected $extensions;

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
            $environment->addExtension(new $extensionClass());
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
            'max_nesting_level' => $mainConfig['max_nesting_level'] ?? \PHP_INT_MAX,
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
        $extensionClass = sprintf(
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
            return [
                self::$configKeys[$extension] => $this->config[$extension],
            ];
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
            'use_underscore'
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
            = $config['commonmark']['unordered_list_markers']
                ?? $markdown['unordered_list_markers']
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
        if (isset($config['external_link']['open_in_new_window'])) {
            $config['external_link']['open_in_new_window']
                = (bool)$config['external_link']['open_in_new_window'];
        }
        if (isset($config['footnote']['container_add_hr'])) {
            $config['footnote']['container_add_hr']
                = (bool)$config['footnote']['container_add_hr'];
        }
        if (isset($config['table']['wrap']['enabled'])) {
            $config['table']['wrap']['enabled']
                = (bool)$config['table']['wrap']['enabled'];
        }
        if (isset($config['heading_permalink']['aria_hidden'])) {
            $config['heading_permalink']['aria_hidden']
                = (bool)$config['heading_permalink']['aria_hidden'];
        }
        $tableWrapAttributes = [];
        if (isset($config['table']['wrap']['attributes'])) {
            $tableWrapAttributes = array_map(
                'trim',
                explode(',', $config['table']['wrap']['attributes'])
            );
            $config['table']['wrap']['attributes'] = [];
        }
        foreach ($tableWrapAttributes as $attribute) {
            $parts = array_map('trim', explode(':', $attribute));
            if (2 === count($parts)) {
                $config['table']['wrap']['attributes'][$parts[0]] = $parts[1];
            }
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
        $markdownConfig = $this->config['Markdown'] ?? [];
        $config = array_merge($markdownConfig, $baseConfig, $coreConfig);
        foreach ($this->extensions as $extension) {
            $extConfig = $this->getConfigForExtension($extension);
            $config = array_merge($config, $extConfig);
        }
        return $this->sanitizeConfig($config);
    }
}
