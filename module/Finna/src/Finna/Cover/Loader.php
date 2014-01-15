<?php
/**
 * Cover Image Generator
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Cover_Generator
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/use_of_external_content Wiki
 */
namespace Finna\Cover;

/**
 * Cover Image Generator
 *
 * @category VuFind2
 * @package  Cover_Generator
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/use_of_external_content Wiki
 */
class Loader extends \VuFind\Cover\Loader
{
    /**
     * Retrieve an NLF cover.
     *
     * @return bool True if image loaded, false otherwise.
     */
    protected function nlf()
    {
        $isn = $this->isn->get13();
        if (!$isn) {
            return false;
        }
        $url = 'http://siilo-kk.lib.helsinki.fi/getImage.php?query=' . $isn . '&return_error=true';
        return $this->processImageURLForSource($url, 'NLF');
    }
}
