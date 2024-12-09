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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper;

use Laminas\View\Helper\AbstractHelper;

/**
 * Helper class for displaying search-related HTML chunks.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractSearch extends AbstractHelper
{
    /**
     * Get the CSS classes for the container holding the suggestions.
     *
     * @return string
     */
    abstract protected function getContainerClass();

    /**
     * Render an expand link.
     *
     * @param string                             $url  Link href
     * @param \Laminas\View\Renderer\PhpRenderer $view View renderer object
     *
     * @return string
     */
    abstract protected function renderExpandLink($url, $view);

    /**
     * Support function to display spelling suggestions.
     *
     * @param string                             $msg     HTML to display at the top
     * of the spelling section.
     * @param \VuFind\Search\Base\Results        $results Results object
     * @param \Laminas\View\Renderer\PhpRenderer $view    View renderer object
     *
     * @return string
     */
    public function renderSpellingSuggestions($msg, $results, $view)
    {
        $spellingSuggestions = $results->getSpellingSuggestions();
        if (empty($spellingSuggestions)) {
            return '';
        }

        $html = '<div class="spellingSuggestions ' . $this->getContainerClass() . '">';
        $html .= '<h2>' . $msg . '</h2><ul class="terms">';
        $normalizer = $results->getOptions()->getSpellingNormalizer();
        foreach ($spellingSuggestions as $term => $details) {
            $html .= '<li>' . $view->escapeHtml($term) . ' &raquo; <ul class="suggestions">';
            foreach ($details['suggestions'] as $word => $data) {
                $href = $results->getUrlQuery()
                    ->replaceTerm(
                        $term,
                        $data['new_term'],
                        $normalizer
                    )->getParams();
                $html .= '<li><a href="' . $href . '">' . $view->escapeHtml($word)
                    . '</a>';
                if (isset($data['expand_term']) && !empty($data['expand_term'])) {
                    $url = $results->getUrlQuery()
                        ->replaceTerm(
                            $term,
                            $data['expand_term'],
                            $normalizer
                        )->getParams();
                    $html .= $this->renderExpandLink($url, $view);
                }
                $html .= '</li>';
            }
            $html .= '</ul></li>';
        }
        $html .= '</ul></div>';
        return $html;
    }
}
