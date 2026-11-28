<?php

/**
 * Helper class for displaying search-related HTML chunks.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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

namespace VuFind\View\Helper;

use Laminas\View\Helper\EscapeHtml;
use VuFind\Search\Base\Results;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Helper\Root\TransEsc;

/**
 * Helper class for displaying search-related HTML chunks.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractSearch
{
    /**
     * Constructor.
     *
     * @param EscapeHtml $escapeHtml EscapeHtml helper
     * @param TransEsc   $transEsc   TransEsc helper
     */
    public function __construct(
        #[Autowire(container: 'ViewHelperManager')]
        protected EscapeHtml $escapeHtml,
        #[Autowire(container: 'ViewHelperManager')]
        protected TransEsc $transEsc,
    ) {
    }

    /**
     * Get the CSS classes for the container holding the suggestions.
     *
     * @return string
     */
    abstract protected function getContainerClass();

    /**
     * Render an expand link.
     *
     * @param string $url Link href
     *
     * @return string
     */
    abstract protected function renderExpandLink(string $url): string;

    /**
     * Support function to display spelling suggestions.
     *
     * @param string  $msg     HTML to display at the top
     *                         of the spelling section.
     * @param Results $results Results object
     *
     * @return string
     */
    public function renderSpellingSuggestions(string $msg, Results $results): string
    {
        $spellingSuggestions = $results->getSpellingSuggestions();
        if (empty($spellingSuggestions)) {
            return '';
        }

        $html = '<div class="spellingSuggestions ' . $this->getContainerClass() . '">';
        $html .= '<h2>' . $msg . '</h2><ul class="terms">';
        $normalizer = $results->getOptions()->getSpellingNormalizer();
        foreach ($spellingSuggestions as $term => $details) {
            $html .= '<li>' . ($this->escapeHtml)($term) . ' &raquo; <ul class="suggestions">';
            foreach ($details['suggestions'] as $word => $data) {
                $href = $results->getUrlQuery()
                    ->replaceTerm(
                        $term,
                        $data['new_term'],
                        $normalizer
                    )->getParams();
                $html .= '<li><a href="' . $href . '">' . ($this->escapeHtml)($word)
                    . '</a>';
                if (isset($data['expand_term']) && !empty($data['expand_term'])) {
                    $url = $results->getUrlQuery()
                        ->replaceTerm(
                            $term,
                            $data['expand_term'],
                            $normalizer
                        )->getParams();
                    $html .= $this->renderExpandLink($url);
                }
                $html .= '</li>';
            }
            $html .= '</ul></li>';
        }
        $html .= '</ul></div>';
        return $html;
    }

    /**
     * Make helper invokable.
     *
     * @return static
     */
    public function __invoke(): static
    {
        return $this;
    }
}
