<?php

/**
 * Finna-truncate custom element
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021-2023.
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
 * @package  CustomElements
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace Finna\View\CustomElement;

/**
 * Finna-truncate custom element
 *
 * @category VuFind
 * @package  CustomElements
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class FinnaTruncate extends AbstractBase
{
    /**
     * FinnaTruncate constructor.
     *
     * @param string $name    Element name
     * @param array  $options Options
     */
    public function __construct(string $name, array $options = [])
    {
        parent::__construct($name, $options);

        if ($this->dom) {
            $labelElement = $this->dom->find('[slot="label"]');
            if ($labelElement = $labelElement[0] ?? false) {
                $label = trim(strip_tags($labelElement->innerHtml()));
                if (!empty($label)) {
                    $this->setVariable('label', $label);
                }
                $this->removeElement($labelElement);
            }

            $this->viewModel->setVariable(
                'content',
                $this->dom->firstChild()->innerHTML()
            );
        }

        $this->setTemplate(self::getTemplateName());
    }

    /**
     * Get information about child elements supported by the element.
     *
     * @return array Array containing element names as keys and arrays of
     *     HTMLPurifier_HTMLDefinition::addElement() arguments excluding the name of
     *     the element as values.
     */
    public static function getChildInfo(): array
    {
        return [
            'span' => [
                self::TYPE => 'Inline',
                self::CONTENTS => 'Inline',
                self::ATTR_COLLECTIONS => 'Common',
                self::ATTRIBUTES => ['slot' => 'CDATA'],
            ],
        ];
    }

    /**
     * Get the template name or null if a default template should be used.
     *
     * @return string|null
     */
    public static function getTemplateName(): ?string
    {
        return '_ui/components/finna-truncate';
    }

    /**
     * Get names of attributes to set as view model variables.
     *
     * @return array Keyed array with attribute names as keys and variable names as
     *               values
     */
    protected static function getAttributeToVariableMap(): array
    {
        return [
            'rows' => 'rows',
        ];
    }
}
