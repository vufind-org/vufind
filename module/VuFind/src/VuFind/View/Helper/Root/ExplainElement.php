<?php

/**
 * Explain element view helper.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2023.
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
 * @author   Dennis Schrittenlocher <Dennis.Schrittenlocher@outlook.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Renderer\RendererInterface;
use VuFind\ServiceManager\Factory\Autowire;

use function count;

/**
 * Explain element view helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Dennis Schrittenlocher <Dennis.Schrittenlocher@outlook.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ExplainElement
{
    /**
     * Constructor.
     *
     * @param RendererInterface $view            View renderer
     * @param Translate         $translate       Translate helper
     * @param LocalizedNumber   $localizedNumber LocalizedNumber helper
     */
    public function __construct(
        #[Autowire(service: 'ViewRenderer')]
        protected RendererInterface $view,
        #[Autowire(container: 'ViewHelperManager')]
        protected Translate $translate,
        #[Autowire(container: 'ViewHelperManager')]
        protected LocalizedNumber $localizedNumber
    ) {
    }

    /**
     * Render the explain element.
     *
     * @param array $explainElement Explain element
     * @param int   $decimalPlaces  Decimal places
     *
     * @return array
     */
    public function __invoke($explainElement, $decimalPlaces)
    {
        $fieldName = $explainElement['fieldName'] ?? [];
        $fieldValue = $explainElement['fieldValue'] ?? [];
        $fieldModifier = $explainElement['fieldModifier'] ?? [];
        $function = $explainElement['function'] ?? null;

        $shortLabel = '';
        if ($function !== null) {
            $shortLabel = ($this->translate)('explain_function_query_label') . ': ' . $function;
        } else {
            if (count($fieldName) > 1) {
                $shortLabel .= ($this->translate)('Synonym') . '[';
            }
            $shortLabel .= implode(
                ', ',
                array_map(function ($name, $value) {
                    return $name . '(' . $value . ')';
                }, $fieldName, $fieldValue)
            );
            if (count($fieldName) > 1) {
                $shortLabel .= ']';
            }
        }

        if ($fieldModifier) {
            $shortLabel .= '^' . ($this->localizedNumber)($fieldModifier, $decimalPlaces);
        }

        $shortValue = $explainElement['value'];
        $completeLine = $this->view->render(
            'RecordDriver/DefaultRecord/explain-line.phtml',
            compact('explainElement', 'fieldName', 'fieldValue', 'fieldModifier', 'decimalPlaces', 'function')
        );
        return compact('shortLabel', 'shortValue', 'completeLine');
    }
}
