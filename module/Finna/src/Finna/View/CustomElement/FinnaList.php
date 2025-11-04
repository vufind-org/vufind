<?php

/**
 * Finna-list custom element
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

/**
 * Finna-list custom element
 *
 * @category VuFind
 * @package  CustomElements
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class FinnaList extends AbstractBase
{
    /**
     * FinnaList constructor.
     *
     * @param string $name    Element name
     * @param array  $options Options
     */
    public function __construct(string $name, array $options = [])
    {
        parent::__construct($name, $options, true);

        $this->setTemplate(self::getTemplateName());
    }

    /**
     * Get the template name or null if a default template should be used.
     *
     * @return string|null
     */
    public static function getTemplateName(): ?string
    {
        return 'CustomElement/finna-list';
    }

    /**
     * Get default values for view model variables.
     *
     * @return array
     */
    public static function getDefaultVariables(): array
    {
        return [
            'id'           => null,
            'view'         => 'grid',
            'description'  => true,
            'title'        => true,
            'date'         => false,
            'allowCopy'    => true,
            'limit'        => 6,
            'showAllLink'  => true,
            'headingLevel' => 2,
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
            'id'            => 'id',
            'view'          => 'view',
            'description'   => 'description',
            'title'         => 'title',
            'date'          => 'date',
            'allow-copy'    => 'allowCopy',
            'limit'         => 'limit',
            'show-all-link' => 'showAllLink',
            'heading-level' => 'headingLevel',
        ];
    }
}
