<?php

/**
 * Recommendation module view helper
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

use Laminas\View\Renderer\RendererInterface;
use Laminas\View\Resolver\ResolverInterface;
use VuFind\Recommend\RecommendInterface;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Recommendation module view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Recommend
{
    use ClassBasedTemplateRendererTrait;

    /**
     * Constructor
     *
     * @param RendererInterface $viewRenderer View renderer
     * @param ResolverInterface $viewResolver View resolver
     */
    public function __construct(
        #[Autowire(service: 'ViewRenderer')]
        RendererInterface $viewRenderer,
        #[Autowire(service: 'ViewResolver')]
        ResolverInterface $viewResolver
    ) {
        $this->viewRenderer = $viewRenderer;
        $this->viewResolver = $viewResolver;
    }

    /**
     * Render the output of a recommendation module.
     *
     * @param RecommendInterface $recommend The recommendation object to render
     * @param string             $location  Recommendation location (side, top)
     * @param int                $index     Index of the recommendation configuration
     *
     * @return string
     */
    public function __invoke(
        RecommendInterface $recommend,
        $location = null,
        $index = null
    ) {
        $template = 'Recommend/%s.phtml';
        $className = $recommend::class;
        $context = [
            'recommend' => $recommend,
            'location' => $location,
            'configIndex' => $index,
        ];
        return $this->renderClassTemplate($template, $className, $context);
    }
}
