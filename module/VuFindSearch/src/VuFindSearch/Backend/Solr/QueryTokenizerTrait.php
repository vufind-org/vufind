<?php

/**
 * Solr query tokenizer trait.
 *
 * PHP version 7
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFindSearch\Backend\Solr;

/**
 * Solr query tokenizer trait.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait QueryTokenizerTrait
{
    /**
     * Input Tokenizer - Specifically for spelling purposes
     *
     * Because of its focus on spelling, these tokens are unsuitable
     * for actual searching. They are stripping important search data
     * such as joins and groups, simply because they don't need to be
     * spellchecked.
     *
     * @param string $input Query to tokenize
     *
     * @return array        Tokenized array
     */
    public function tokenize($input)
    {
        // Exclusion list of useless tokens:
        $joins = ["AND", "OR", "NOT"];

        // Strip out parentheses -- irrelevant for tokenization:
        $paren = ["(" => " ", ")" => " "];
        $input = trim(strtr($input, $paren));

        // Base of this algorithm comes straight from PHP doc example by
        // benighted at gmail dot com: http://php.net/manual/en/function.strtok.php
        $tokens = [];
        $token = strtok($input, " \t");
        while ($token !== false) {
            // find double quoted tokens
            if (substr($token, 0, 1) == '"' && substr($token, -1) != '"') {
                $token .= ' ' . strtok('"') . '"';
            }
            // skip boolean operators
            if (!in_array($token, $joins)) {
                $tokens[] = $token;
            }
            $token = strtok(" \t");
        }

        // If the last token ends in a double quote but the input string does not,
        // the tokenization process added the quote, which will break spelling
        // replacements.  We need to strip it back off again:
        $last = count($tokens) > 0 ? $tokens[count($tokens) - 1] : null;
        if ($last && substr($last, -1) == '"' && substr($input, -1) != '"') {
            $tokens[count($tokens) - 1] = substr($last, 0, strlen($last) - 1);
        }
        return $tokens;
    }
}
