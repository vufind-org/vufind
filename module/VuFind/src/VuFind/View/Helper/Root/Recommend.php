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

use VuFind\Recommend\Helper\Between;
use VuFind\Recommend\RecommendInterface;

/**
 * Recommendation module view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Recommend extends \Laminas\View\Helper\AbstractHelper
{
    use ClassBasedTemplateRendererTrait;

    /**
     * Render the output of a recommendation module.
     *
     * @param ?RecommendInterface $recommend The recommendation object to render
     * @param ?string             $location  Recommendation location (side, top)
     * @param ?int                $index     Index of the recommendation configuration
     *
     * @return string|Recommend The recommend module output if $recommend is non-null;
     * otherwise returns this object itself.
     */
    public function __invoke(
        ?RecommendInterface $recommend = null,
        ?string $location = null,
        ?int $index = null
    ) {
        if (!$recommend) {
            return $this;
        }

        $template = 'Recommend/%s.phtml';
        $className = $recommend::class;
        $context = [
            'recommend' => $recommend,
            'location' => $location,
            'configIndex' => $index,
        ];
        return $this->renderClassTemplate($template, $className, $context);
    }

    /**
     * Return an instance of the Between placement helper for recommendation modules.
     *
     * @return Between
     */
    public function getBetweenHelper()
    {
        return new Between();
    }
}
