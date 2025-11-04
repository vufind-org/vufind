<?php

/**
 * Custom element interface
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

use Laminas\View\Model\ModelInterface;

/**
 * Custom element interface
 *
 * @category VuFind
 * @package  CustomElements
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
interface CustomElementInterface
{
    /**
     * Element information array key for the content set type.
     * See HTML Purifier documentation for possible values.
     *
     * @var string
     */
    public const TYPE = 'type';

    /**
     * Element information array key for allowed children.
     * See HTML Purifier documentation for possible values.
     *
     * @var string
     */
    public const CONTENTS = 'contents';

    /**
     * Element information array key for common attributes collections.
     * See HTML Purifier documentation for possible values.
     *
     * @var string
     */
    public const ATTR_COLLECTIONS = 'attrCollections';

    /**
     * Element information array key for attributes.
     * See HTML Purifier documentation for possible values.
     *
     * @var string
     */
    public const ATTRIBUTES = 'attributes';

    /**
     * Get the name of the element.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get information about the element.
     *
     * @return array
     */
    public static function getInfo(): array;

    /**
     * Get information about child elements supported by the element.
     *
     * @return array
     */
    public static function getChildInfo(): array;

    /**
     * Get the template name or null if a default template should be used.
     *
     * @return ?string
     */
    public static function getTemplateName(): ?string;

    /**
     * Get the view model for server-side rendering the element.
     *
     * @return ModelInterface
     */
    public function getViewModel(): ModelInterface;
}
