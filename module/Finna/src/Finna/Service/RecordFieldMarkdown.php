<?php

/**
 * Finna record field Markdown service
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021-2022.
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
 * @package  VuFind\Service
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Service;

use Finna\CommonMark\Extension\RecordFieldMarkdownExtension;
use League\CommonMark\ConverterInterface;
use League\CommonMark\Environment\Environment;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Output\RenderedContentInterface;
use League\CommonMark\Util\HtmlFilter;

/**
 * Finna record field Markdown service
 *
 * @category VuFind
 * @package  VuFind\Service
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RecordFieldMarkdown implements ConverterInterface
{
    /**
     * Default string for rendering soft breaks.
     *
     * @var string
     */
    public const DEFAULT_SOFT_BREAK = '<br>';

    /**
     * Markdown converter factory.
     *
     * @var callable
     */
    protected $converterFactory;

    /**
     * Converter for default soft breaks.
     *
     * @var ?MarkdownConverter
     */
    protected ?MarkdownConverter $defaultConverter = null;

    /**
     * Array of converters for different soft breaks.
     *
     * @var array
     */
    protected array $converters = [];

    /**
     * RecordFieldMarkdown constructor.
     */
    public function __construct()
    {
        $this->converterFactory = function ($softBreak = null) {
            $config = [
                'html_input' => HtmlFilter::ESCAPE,
                'allow_unsafe_links' => false,
            ];
            $config['renderer']['soft_break']
                = $softBreak ?? self::DEFAULT_SOFT_BREAK;

            $environment = new Environment($config);
            $environment->addExtension(new RecordFieldMarkdownExtension());
            return new MarkdownConverter($environment);
        };
    }

    /**
     * Converts Markdown to HTML.
     *
     * @param string  $input     The Markdown to convert
     * @param ?string $softBreak String to use for rendering soft breaks (optional)
     *
     * @return RenderedContentInterface
     */
    public function convert(string $input, ?string $softBreak = null): RenderedContentInterface
    {
        $converter = $this->getConverter($softBreak);
        return $converter->convert($input);
    }

    /**
     * Get a Markdown converter for the given soft break.
     *
     * @param ?string $softBreak Soft break
     *
     * @return MarkdownConverter
     */
    protected function getConverter(?string $softBreak): MarkdownConverter
    {
        if (null === $softBreak) {
            if (null === $this->defaultConverter) {
                $this->defaultConverter = ($this->converterFactory)();
            }
            return $this->defaultConverter;
        }
        if (!isset($this->converters[$softBreak])) {
            $this->converters[$softBreak] = ($this->converterFactory)($softBreak);
        }
        return $this->converters[$softBreak];
    }
}
