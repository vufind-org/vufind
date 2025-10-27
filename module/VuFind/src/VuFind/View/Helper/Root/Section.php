<?php

/**
 * Section view helper
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
 * @package  View_Helpers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;
use VuFind\Section\SectionInterface;
use VuFind\Section\SectionServiceInterface;

/**
 * Section view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Section extends AbstractHelper
{
    use ClassBasedTemplateRendererTrait;

    /**
     * Additional context key.
     */
    public const ADDITIONAL_CONTEXT_KEY = '__context';

    /**
     * Default template directory.
     *
     * @var string
     */
    protected string $defaultTemplateDir = 'Section';

    /**
     * Plugin classes keyed by alias.
     *
     * @var SectionInterface[]
     */
    protected array $plugins;

    /**
     * Current plugin.
     *
     * @var SectionInterface
     */
    protected SectionInterface $plugin;

    /**
     * Template to use for the current plugin.
     *
     * @var string
     */
    protected string $template;

    /**
     * Constructor.
     *
     * @param SectionServiceInterface $sectionService Section service
     */
    public function __construct(protected SectionServiceInterface $sectionService)
    {
    }

    /**
     * Store a plugin object and return this object.
     *
     * @param string       $key      Section key in configuration
     * @param array|string $config   Configuration or configuration file name
     * *                             (optional)
     * @param ?string      $template File name of template used to render menu
     *                               (optional)
     *
     * @return static
     */
    public function __invoke(
        string $key,
        array|string $config = SectionServiceInterface::DEFAULT_CONFIG_FILE,
        ?string $template = null
    ): static {
        if (!isset($this->plugins[$key])) {
            $this->plugins[$key] = $this->sectionService->getSection($key, $config);
        }
        if (null === $template) {
            $template = $this->defaultTemplateDir . '/' . $key . '.phtml';
        }
        $this->plugin = $this->plugins[$key];
        $this->template = $template;
        return $this;
    }

    /**
     * By default, proxy method calls to the plugin class.
     *
     * @param string $methodName The name of the called method.
     * @param array  $params     Array of passed parameters.
     *
     * @return mixed
     */
    public function __call($methodName, $params)
    {
        return $this->plugin->$methodName(...$params);
    }

    /**
     * Render a section.
     *
     * @param array $context Additional context to be merged with section
     *                       context (optional)
     *
     * @return string
     */
    public function render(array $context = []): string
    {
        $mergedContext = array_merge($this->plugin->getContext(), $context);
        $mergedContext[self::ADDITIONAL_CONTEXT_KEY] = $context;
        if ($this->getView()->resolver()->resolve($this->template)) {
            return $this->getView()->render($this->template, $mergedContext);
        } else {
            // Default to class-based template.
            $template = $this->defaultTemplateDir . '/%s.phtml';
            $className = strtolower($this->plugin::class);
            return $this->renderClassTemplate($template, $className, $mergedContext);
        }
    }
}
