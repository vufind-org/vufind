<?php

/**
 * Finna-panel custom element
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
 * @package  CustomElements
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace Finna\View\CustomElement;

use function in_array;

/**
 * Finna-panel custom element
 *
 * @category VuFind
 * @package  CustomElements
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class FinnaPanel extends AbstractBase
{
    /**
     * FinnaPanel constructor.
     *
     * @param string $name    Element name
     * @param array  $options Options
     */
    public function __construct(string $name, array $options = [])
    {
        parent::__construct($name, $options, true);

        // The 'headingId' and 'collapseId' variables are only set if the
        // 'collapsible' attribute is not set to false.
        if ($this->attributes['collapsible'] ?? true) {
            $this->setVariable(
                'headingId',
                $this->attributes['heading-id'] ?? uniqid('heading-')
            );
            $this->setVariable(
                'collapseId',
                $this->attributes['collapse-id'] ?? uniqid('collapse-')
            );
        }

        if ($this->dom) {
            $heading = $this->dom->find('[slot="heading"]');
            if ($heading = $heading[0] ?? false) {
                $this->setVariable('heading', strip_tags($heading->innerHTML()));

                // The 'heading-level' attribute takes precedence over a heading
                // level set in a h-tag.
                if (!isset($this->attributes['heading-level'])) {
                    $hName = $heading->getTag()->name();
                    if (in_array($hName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                        $this->setVariable('headingLevel', substr($hName, 1));
                    }
                }

                $this->removeElement($heading);
            }

            $this->setVariable('content', $this->dom->firstChild()->innerHTML());
        }

        $this->setTemplate(self::getTemplateName());
    }

    /**
     * Get information about the element.
     *
     * @return array Array of HTMLPurifier_HTMLDefinition::addElement() arguments
     *     excluding the name of the element.
     */
    public static function getInfo(): array
    {
        return [
            self::TYPE => 'Block',
            self::CONTENTS => 'Flow',
            self::ATTR_COLLECTIONS => 'Common',
            self::ATTRIBUTES => array_fill_keys(
                array_merge(
                    ['collapse-id', 'collapsible', 'heading-id'],
                    array_keys(self::getAttributeToVariableMap())
                ),
                'CDATA'
            ),
        ];
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
        $hArgs = [
            self::TYPE => 'Heading',
            self::CONTENTS => 'Inline',
            self::ATTR_COLLECTIONS => 'Common',
            self::ATTRIBUTES => ['slot' => 'CDATA'],
        ];
        return [
            'h1' => $hArgs,
            'h2' => $hArgs,
            'h3' => $hArgs,
            'h4' => $hArgs,
            'h5' => $hArgs,
            'h6' => $hArgs,
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
        return '_ui/components/finna-panel';
    }

    /**
     * Get default values for view model variables.
     *
     * @return array
     */
    public static function getDefaultVariables(): array
    {
        return [
            'attributes'   => ['class' => 'finna-panel-default'],
            'headingLevel' => 3,
            'headingTag'   => true,
        ];
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
            'collapsed'     => 'collapsed',
            'heading-level' => 'headingLevel',
            'heading-tag'   => 'headingTag',
        ];
    }
}
