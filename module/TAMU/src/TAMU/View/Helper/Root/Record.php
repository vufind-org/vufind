<?php
/**
 * Record driver view helper
 *
 * PHP version 7
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
namespace TAMU\View\Helper\Root;

/**
 * Record driver view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Record extends \VuFind\View\Helper\Root\Record
{
    /**
     * TAMU Customization
     *
     * Get the full text button
     *
     * @param array  $issnParent   The marc parent field of the ISSN
     * @param string $recordFormat The format of the record
     *
     * @return stdClass
     */
    public function getFullTextButton($issnParent, $recordFormat)
    {
        $fullTextData = new \stdClass();
        $fullTextData->issn = null;
        $fullTextData->title = null;
        $fullTextData->isValid = false;

        if ($issnParent && $recordFormat) {
            $validFormats = isset($this->config->TAMU->full_text_formats) ?
                explode(":", $this->config->TAMU->full_text_formats):[];
            $isValidFormat = false;
            foreach ($validFormats as $validFormat) {
                if (str_contains($recordFormat, $validFormat)) {
                    $fullTextData->isValid = true;
                    break;
                }
            }

            $issnSubField = $issnParent[0]->getSubField("a") ?
                $issnParent[0]->getSubField("a"):$issnParent[0]->getSubField("y");
            if ($issnSubField) {
                $fullTextData->issn = $issnSubField->getData();
                $escapeHtml = $this->getView()->plugin('escapeHtml');
                $fullTextData->title = $escapeHtml(
                    $this->driver->getShortTitle() . ' ' .
                    $this->driver->getSubtitle() . ' ' .
                    $this->driver->getTitleSection()
                );
            }
        }
        return $fullTextData;
    }
}
