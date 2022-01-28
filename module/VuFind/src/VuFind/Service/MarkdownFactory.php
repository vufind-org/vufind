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
        'DefaultAttributes' => 'default_attributes',
        'ExternalLink' => 'external_link',
        'Footnote' => 'footnote',
        'HeadingPermalink' => 'heading_permalink',
        'Mention' => 'mentions',
        'SmartPunct' => 'smartpunct',
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
        $markdownConfig = $container->get(\VuFind\Config\PluginManager::class)
            ->get('markdown');

        $environment = $this->getEnvironment($markdownConfig);
        $this->addExtensions($environment, $markdownConfig);

        return new MarkdownConverter($environment);
    }

    /**
     * Get Markdown environment.
     *
     * @param $markdownConfig Config VuFind Markdown config
     *
     * @return EnvironmentBuilderInterface
     */
    protected function getEnvironment(Config $markdownConfig):
        EnvironmentBuilderInterface
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->mergeConfig($this->getEnvironmentConfig($markdownConfig));

        return $environment;
    }

    /**
     * Get Markdown environment config.
     *
     * @param $markdownConfig Config VuFind Markdown config
     *
     * @return array
     */
    protected function getEnvironmentConfig(Config $markdownConfig): array
    {
        $mainConfig = $markdownConfig->Markdown;

        return [
            'html_input' => $mainConfig->html_input ?? 'strip',
            'allow_unsafe_links' => $mainConfig->allow_unsafe_links ?? false,
            'max_nesting_level' => $mainConfig->max_nesting_level ?? \PHP_INT_MAX,
            'commonmark' => [
                'enable_em' => $mainConfig->enable_em ?? true,
                'enable_strong' => $mainConfig->enable_strong ?? true,
                'use_asterisk' => $mainConfig->use_asterisk ?? true,
                'use_underscore' => $mainConfig->use_underscore ?? true,
                'unordered_list_markers' =>
                    isset($mainConfig->unordered_list_markers)
                    && $mainConfig->unordered_list_markers instanceof \ArrayAccess
                        ? $mainConfig->unordered_list_markers->toArray()
                        : ['-', '*', '+'],
            ],
            'renderer' => [
                'block_separator'
                    => $mainConfig->renderer['block_separator'] ?? "\n",
                'inner_separator'
                    => $mainConfig->renderer['inner_separator'] ?? "\n",
                'soft_break' => $mainConfig->renderer['soft_break'] ?? "\n",
            ],
        ];
    }

    /**
     * Add extensions to Markdown environment.
     *
     * @param $environment    EnvironmentBuilderInterface Markdown environment
     * @param $markdownConfig Config                      VuFind Markdown config
     *
     * @return void
     */
    protected function addExtensions(
        EnvironmentBuilderInterface $environment,
        Config $markdownConfig
    ): void {
        $mainConfig = $markdownConfig->Markdown;
        $extensions = isset($mainConfig->extensions)
            ? array_map('trim', explode(',', $mainConfig->extensions))
            : self::$defaultExtensions;

        foreach ($extensions as $ext) {
            if ($ext === '') {
                continue;
            }
            $extClass = sprintf(
                'League\CommonMark\Extension\%s\%sExtension',
                $ext,
                $ext
            );
            if (!class_exists($extClass)) {
                throw new ServiceNotCreatedException(
                    "Could not create markdown service. Extension '$ext' not found"
                );
            }
            $environment->addExtension(new $extClass());
            if (isset($markdownConfig[$ext])) {
                $environment->mergeConfig(
                    [self::$configKeys[$ext] => $markdownConfig->$ext->toArray()]
                );
            }
        }
    }
}
