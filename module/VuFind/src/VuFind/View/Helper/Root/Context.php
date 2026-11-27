<?php

/**
 * Context manager.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Renderer\Template;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Renderer\TemplateRendererInterface;

/**
 * Context manager.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Context
{
    /**
     * Current template.
     *
     * @var ?Template
     */
    protected ?Template $template = null;

    /**
     * Constructor.
     *
     * @param TemplateRendererInterface $templateRenderer Template renderer
     */
    #[Autowire]
    public function __construct(
        protected TemplateRendererInterface $templateRenderer,
    ) {
    }

    /**
     * Set current template.
     *
     * @param ?Template $template Template
     *
     * @return static
     */
    public function setTemplate(?Template $template): static
    {
        $this->template = $template;
        return $this;
    }

    /**
     * Get template variables.
     *
     * @param array $extra Extra variables
     *
     * @return array
     */
    public function getVars(array $extra = []): array
    {
        return $extra + ($this->template ? iterator_to_array($this->template) : []);
    }

    /**
     * Render a template using a context that includes the variables from current template.
     *
     * @param string $templateName Template to render
     * @param array  $context      Array of context variables to add
     *
     * @return string Rendered template output
     */
    public function renderInContext(string $templateName, array $context = [])
    {
        if ($this->template) {
            $context += iterator_to_array($this->template);
        }
        return $this->templateRenderer->renderTemplateAsString(template: $templateName, params: $context);
    }

    /**
     * Return this helper instance.
     *
     * @param ?Template $template Template
     *
     * @return static
     */
    public function __invoke(?Template $template = null): static
    {
        if (null !== $template) {
            $result = clone $this;
            $result->setTemplate($template);
            return $result;
        }
        return $this;
    }
}
