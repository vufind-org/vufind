<?php

/**
 * Abstract base custom element
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

use Exception;
use Laminas\View\Model\ModelInterface;
use Laminas\View\Model\ViewModel;
use PHPHtmlParser\Dom;
use PHPHtmlParser\Dom\Node\HtmlNode;
use PHPHtmlParser\Options;

use function array_key_exists;
use function is_string;

/**
 * Abstract base custom element
 *
 * @category VuFind
 * @package  CustomElements
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
abstract class AbstractBase implements CustomElementInterface
{
    /**
     * Regex for matching valid element names
     *
     * @var string
     */
    protected $validNameRegex = '/^([A-Za-z][A-Za-z0-9]*)-[A-Za-z0-9-]+$/';

    /**
     * Element name
     *
     * @var string
     */
    protected $name;

    /**
     * Element name prefix
     *
     * @var string
     */
    protected $prefix;

    /**
     * Options
     *
     * The base class supports the following options:
     * - attributes
     *     Array of attributes for the custom element. These will overwrite
     *     attributes parsed from outerHTML.
     * - outerHTML
     *     Outer HTML of the element. This will be parsed to a DOM object and
     *     attributes.
     *
     * @var array
     */
    protected $options;

    /**
     * Attributes
     *
     * @var array
     */
    protected $attributes;

    /**
     * DOM object for the custom element if outerHTML is provided in options
     *
     * @var Dom
     */
    protected $dom = null;

    /**
     * View model for server-side rendering
     *
     * @var ModelInterface
     */
    protected $viewModel;

    /**
     * AbstractBase constructor.
     *
     * @param string $name          Element name
     * @param array  $options       Options
     * @param bool   $convertToBool Convert string true/false values to booleans
     *
     * @throws Exception If element name or outerHTML is not valid
     */
    public function __construct(
        string $name,
        array $options = [],
        bool $convertToBool = false
    ) {
        $matches = [];
        if (!preg_match($this->validNameRegex, $name, $matches)) {
            throw new Exception('Element name is not valid');
        }
        $this->name = $name;
        $this->prefix = $matches[1];

        $attributes = $options['attributes'] ?? [];

        // If outer HTML is set, set up the DOM object and process attributes.
        if (isset($options['outerHTML'])) {
            $dom = (new Dom())->loadStr(
                $options['outerHTML'],
                (new Options())
                    ->setCleanupInput(false)
                    ->setRemoveDoubleSpace(false)
            );
            if (
                $dom->countChildren() !== 1
                || $dom->firstChild()->getTag()->name() !== $this->getName()
            ) {
                throw new Exception('Element outerHTML is not valid');
            }
            $this->dom = $dom;

            // Attributes set in options overwrite attributes set in HTML.
            $attributes = array_merge(
                $dom->firstChild()->getAttributes(),
                $attributes
            );
        }

        if ($convertToBool) {
            $options = $this->convertToBool($options);
            $attributes = $this->convertToBool($attributes);
        }

        // Get default variable values.
        $variables = static::getDefaultVariables();

        // Try to set variable values from attributes, if defined by subclass.
        foreach (static::getAttributeToVariableMap() as $attributeName => $variableName) {
            if (array_key_exists($attributeName, $attributes)) {
                $variables[$variableName] = $attributes[$attributeName];
            }
        }

        // Try to set variable values from options, if defined by subclass.
        // Option values overwrite attribute values when setting variables.
        foreach (static::getOptionToVariableMap() as $optionName => $variableName) {
            if (array_key_exists($optionName, $options)) {
                $variables[$variableName] = $options[$optionName];
            }
        }

        $this->options = $options;
        $this->attributes = $attributes;
        $this->viewModel = new ViewModel($variables);
    }

    /**
     * Get the name of the element.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get information about the element.
     *
     * @return array
     */
    public static function getInfo(): array
    {
        return [
            self::TYPE => 'Block',
            self::CONTENTS => 'Flow',
            self::ATTR_COLLECTIONS => 'Common',
            self::ATTRIBUTES => array_fill_keys(
                array_keys(static::getAttributeToVariableMap()),
                'CDATA'
            ),
        ];
    }

    /**
     * Get information about child elements supported by the element.
     *
     * @return array
     */
    public static function getChildInfo(): array
    {
        return [];
    }

    /**
     * Get the template name or null if a default template should be used.
     *
     * @return string|null
     */
    public static function getTemplateName(): ?string
    {
        return null;
    }

    /**
     * Get the view model for server-side rendering the element.
     *
     * @return ModelInterface
     */
    public function getViewModel(): ModelInterface
    {
        return $this->viewModel;
    }

    /**
     * Get default values for view model variables.
     *
     * @return array
     */
    public static function getDefaultVariables(): array
    {
        return [];
    }

    /**
     * Get names of attributes to set as view model variables.
     *
     * @return array Keyed array with attribute names as keys and variable names as
     *               values
     */
    protected static function getAttributeToVariableMap(): array
    {
        return [];
    }

    /**
     * Get names of options to set as view model variables.
     *
     * @return array Keyed array with option names as keys and variable names as
     *               values
     */
    protected static function getOptionToVariableMap(): array
    {
        return array_merge(
            ['attributes' => 'attributes'],
            static::getAttributeToVariableMap()
        );
    }

    /**
     * Convert string true/false values to boolean values.
     *
     * @param array $values Array of options, attributes or variables.
     *
     * @return array
     */
    protected function convertToBool(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($value)) {
                if (strcasecmp($value, 'true') === 0) {
                    $values[$key] = true;
                } elseif (strcasecmp($value, 'false') === 0) {
                    $values[$key] = false;
                }
            }
        }
        return $values;
    }

    /**
     * Get a view model variable.
     *
     * @param string     $name    Name
     * @param mixed|null $default Default value if the variable is not present
     *
     * @return mixed
     */
    protected function getVariable(string $name, $default = null)
    {
        return $this->getViewModel()->getVariable($name, $default);
    }

    /**
     * Set a view model variable.
     *
     * @param string $name  Name
     * @param mixed  $value Value
     *
     * @return void
     */
    protected function setVariable(string $name, $value): void
    {
        $this->getViewModel()->setVariable($name, $value);
    }

    /**
     * Set the view model template.
     *
     * @param string $template Template
     *
     * @return void
     */
    protected function setTemplate(string $template): void
    {
        $this->getViewModel()->setTemplate($template);
    }

    /**
     * Remove an element from the DOM, with additional cleanup of possible unneeded
     * elements added by Markdown processing.
     *
     * @param $element HtmlNode Element
     *
     * @return void
     */
    protected function removeElement(HtmlNode $element): void
    {
        // Remove br elements immediately following the element.
        $parent = $element->getParent();
        while ($element->hasNextSibling()) {
            $sibling = $element->nextSibling();
            if ($sibling->getTag()->name() !== 'br') {
                break;
            }
            $parent->removeChild($sibling->id());
        }

        // Remove the element.
        $parent->removeChild($element->id());

        // If the parent is an empty p element, remove the parent also.
        if (
            !$parent->hasChildren()
            && $parent->getTag()->name() === 'p'
        ) {
            $parent->getParent()->removeChild($parent->id());
        }
    }
}
