<?php

/**
 * Highlight view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;

use function is_array;

/**
 * Highlight view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Highlight extends AbstractHelper
{
    /**
     * Start tag for highlighting
     *
     * @var string
     */
    protected $startTag = '<span class="highlight">';

    /**
     * End tag for highlighting
     *
     * @var string
     */
    protected $endTag = '</span>';

    /**
     * Adds a span tag with class "highlight" around a specific phrase for
     * highlighting
     *
     * @param string $haystack String to highlight
     * @param mixed  $needle   Array of words to highlight (null for none)
     * @param bool   $clear    Should we dehighlight (true) rather than highlight
     * (false)?
     * @param bool   $escape   Should we HTML encode the results?
     *
     * @return string          Highlighted, HTML encoded string
     */
    public function __invoke(
        $haystack,
        $needle = null,
        $clear = false,
        $escape = true
    ) {
        // Normalize value to an array so we can loop through it; this saves us from
        // writing the highlighting code twice, once for arrays, once for non-arrays.
        // Also make sure our generated array is empty if needle itself is empty --
        // if $haystack already has highlighting markers in it, we may want to send
        // in a blank needle.
        if (!is_array($needle)) {
            $needle = empty($needle) ? [] : [$needle];
        }

        // Highlight search terms one phrase at a time; we just put in placeholders
        // for the start and end span tags at this point so we can do proper URL
        // encoding later.
        foreach ($needle as $phrase) {
            $phrase = trim(str_replace(['"', '*', '?'], '', $phrase));
            if ($phrase != '') {
                $phrase = preg_quote($phrase, '/');
                $haystack = preg_replace(
                    "/($phrase)/iu",
                    '{{{{START_HILITE}}}}$1{{{{END_HILITE}}}}',
                    $haystack
                );
            }
        }

        // URL encode the string, then put in the highlight spans:
        $haystack = str_replace(
            ['{{{{START_HILITE}}}}', '{{{{END_HILITE}}}}'],
            $clear ? '' : [$this->startTag, $this->endTag],
            $escape ? htmlspecialchars($haystack) : $haystack
        );

        return $haystack;
    }
}
