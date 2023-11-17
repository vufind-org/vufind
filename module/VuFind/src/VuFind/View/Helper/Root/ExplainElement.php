<?php

/**
 * Explain element view helper
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Dennis Schrittenlocher <Dennis.Schrittenlocher@outlook.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use function count;
use function is_array;

/**
 * Explain element view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Dennis Schrittenlocher <Dennis.Schrittenlocher@outlook.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ExplainElement extends \Laminas\View\Helper\AbstractHelper
{
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
        $view = $this->getView();
        $fieldName = $explainElement['fieldName'] ?? '';
        $fieldModifier = $explainElement['fieldModifier'] ?? false;
        $fieldValue = $explainElement['fieldValue'] ?? false;
        $shortLabel = '';
        if (is_array($fieldName) && is_array($fieldValue) && count($fieldName) == count($fieldValue)) {
            $shortLabel = 'Synonym [' . implode(', ', array_map(function ($name, $value) {
                return $name . '(' . $value . ')';
            }, $fieldName, $fieldValue)) . ']';
        } else {
            if ($fieldName) {
                $shortLabel .= $fieldName;
            }
            if ($fieldValue) {
                $shortLabel .= ' (' . $explainElement['fieldValue'] . ')';
            }
        }
        if ($fieldModifier) {
            $shortLabel .= '^' . $view->localizedNumber($fieldModifier, $decimalPlaces);
        }

        $shortValue = $explainElement['value'];
        $completeLine = $view->render('RecordDriver/DefaultRecord/explain-line.phtml', [
            'explainElement' => $explainElement,
            'fieldName' => $fieldName,
            'fieldValue' => $fieldValue,
            'fieldModifier' => $fieldModifier,
            'decimalPlaces' => $decimalPlaces,
        ]);
        return [
            'shortLabel' => $shortLabel,
            'shortValue' => $shortValue,
            'completeLine' => $completeLine,
        ];
    }
}
