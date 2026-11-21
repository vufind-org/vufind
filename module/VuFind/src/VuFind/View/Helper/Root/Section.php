<?php

/**
 * Section view helper.
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

use Laminas\View\Renderer\RendererInterface;
use Laminas\View\Resolver\ResolverInterface;
use VuFind\Section\PageRegion;
use VuFind\Section\Plugin\SectionInterface;
use VuFind\Section\SectionServiceInterface;
use VuFind\ServiceManager\Factory\Autowire;

use function call_user_func_array;
use function is_callable;
use function is_string;

/**
 * Section view helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Section
{
    use ClassBasedTemplateRendererTrait;

    /**
     * Section context key.
     */
    public const SECTION_PLUGIN_KEY = '__sectionPlugin';

    /**
     * Section context key.
     */
    public const SECTION_CONTEXT_KEY = '__sectionContext';

    /**
     * Additional context key.
     */
    public const ADDITIONAL_CONTEXT_KEY = '__additionalContext';

    /**
     * Default template directory.
     *
     * @var string
     */
    protected string $defaultTemplateDir = 'Section';

    /**
     * Key.
     *
     * @var string
     */
    protected string $key;

    /**
     * Section.
     *
     * @var SectionInterface
     */
    protected SectionInterface $section;

    /**
     * Template to use for the section.
     *
     * @var ?string
     */
    protected ?string $template;

    /**
     * Constructor.
     *
     * @param SectionServiceInterface $sectionService Section service
     * @param Context                 $contextHelper  Context helper
     * @param RendererInterface       $viewRenderer   View renderer
     * @param ResolverInterface       $viewResolver   View resolver
     */
    public function __construct(
        protected SectionServiceInterface $sectionService,
        #[Autowire(container: 'ViewHelperManager')]
        Context $contextHelper,
        RendererInterface $viewRenderer,
        ResolverInterface $viewResolver
    ) {
        $this->setClassBasedTemplateRendererDependencies($viewRenderer, $viewResolver, $contextHelper);
    }

    /**
     * Store a section object and return this object.
     *
     * @param string       $key      Section key
     * @param array|string $config   Configuration or configuration path (optional)
     * @param ?string      $template File name of template used to render section (persistent, optional)
     *
     * @return static
     */
    public function __invoke(
        string $key,
        array|string $config = SectionServiceInterface::DEFAULT_CONFIG_NAME,
        ?string $template = null
    ): static {
        $this->key = $key;
        // Always call section service as the configuration might be different.
        if (is_string($config)) {
            $config = $this->sectionService->getSectionConfig($key, $config);
        }
        $this->section = $this->sectionService->getSection($key, $config);
        $this->template = $template;
        return $this;
    }

    /**
     * By default, proxy method calls to the section class.
     *
     * @param string $methodName The name of the called method.
     * @param array  $params     Array of passed parameters.
     *
     * @return mixed             Varies by method (null if undefined method)
     */
    public function __call($methodName, $params)
    {
        $method = [$this->section, $methodName];
        if (is_callable($method)) {
            return call_user_func_array($method, $params);
        }
        return null;
    }

    /**
     * Render a section.
     *
     * @param array       $context    Additional context to be merged with section context (optional)
     * @param ?PageRegion $pageRegion Page region in which the section is used (optional)
     * @param ?string     $template   File name of template used to render section (call specific, optional)
     *
     * @return string
     */
    public function render(
        array $context = [],
        ?PageRegion $pageRegion = null,
        ?string $template = null
    ): string {
        if (null === ($sectionContext = $this->section->getSectionContext($pageRegion))) {
            return '';
        }
        $mergedContext = array_merge($sectionContext, $context);
        $mergedContext[self::SECTION_PLUGIN_KEY] = $this->section;
        $mergedContext[self::SECTION_CONTEXT_KEY] = $sectionContext;
        $mergedContext[self::ADDITIONAL_CONTEXT_KEY] = $context;
        $template ??= $this->template ?? ($this->defaultTemplateDir . '/' . $this->key . '.phtml');
        if ($template && $this->viewResolver->resolve($template)) {
            return $this->viewRenderer->render($template, $mergedContext);
        }
        $className = $this->section::class;
        // If a page region is specified, try to use a region specific template.
        if (null !== $pageRegion) {
            $briefClass = $this->getBriefClass($className);
            $template = "{$this->defaultTemplateDir}/$briefClass/$briefClass-{$pageRegion->value}.phtml";
            if ($this->viewResolver->resolve($template)) {
                return $this->viewRenderer->render($template, $mergedContext);
            }
        }
        // Finally try regular class-based template rendering.
        $template = $this->defaultTemplateDir . '/%s.phtml';
        return $this->renderClassTemplate($template, $className, $mergedContext);
    }
}
