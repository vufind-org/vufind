<?php

/**
 * Custom element view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021.
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

namespace Finna\View\Helper\Root;

use Finna\View\CustomElement\CustomElementRendererInterface;
use Finna\View\CustomElement\PluginManager as CustomElementManager;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\View\Helper\AbstractHelper;

/**
 * Custom element view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CustomElement extends AbstractHelper implements CustomElementRendererInterface
{
    /**
     * Custom element plugin manager
     *
     * @var CustomElementManager
     */
    protected $pluginManager;

    /**
     * Constructor
     *
     * @param CustomElementManager $pm Custom element plugin manager
     */
    public function __construct(CustomElementManager $pm)
    {
        $this->pluginManager = $pm;
    }

    /**
     * Server-side render a custom element.
     *
     * @param string $name    The name of the custom element to render
     * @param array  $options Options to use when creating the element instance
     *
     * @return ?string Rendered element, or null if element does not exist
     */
    public function __invoke(string $name, array $options = []): ?string
    {
        return $this->render($name, $options);
    }

    /**
     * Server-side render a custom element.
     *
     * @param string $name    The name of the custom element to render
     * @param array  $options Options to use when creating the element instance
     *
     * @return ?string Rendered element, or null if element does not exist
     */
    public function render(string $name, array $options = []): ?string
    {
        try {
            $options['__element'] = $name;
            $element = $this->pluginManager->get($name, $options);
        } catch (ServiceNotFoundException $e) {
            return null;
        }
        $viewModel = $element->getViewModel();
        if (empty($viewModel->getTemplate())) {
            $viewModel->setTemplate('CustomElement/' . $element->getName());
        }
        return $this->getView()->render(
            $viewModel->getTemplate(),
            $viewModel->getVariables()
        );
    }
}
