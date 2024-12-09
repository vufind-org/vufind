<?php

/**
 * Obalky knih TOC content loader.
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2024.
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
 * @package  Content
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

declare(strict_types=1);

namespace VuFind\Content\TOC;

use VuFind\Content\ObalkyKnihService;

use function sprintf;

/**
 * Class ObalkyKnih
 *
 * @category VuFind
 * @package  Content
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ObalkyKnih extends \VuFind\Content\AbstractBase
{
    /**
     * Constructor
     *
     * @param ObalkyKnihService $service Service for getting metadata from obalkyknih.cz
     */
    public function __construct(protected ObalkyKnihService $service)
    {
    }

    /**
     * Load TOC for a particular ISBN.
     *
     * @param string           $key     API key
     * @param \VuFindCode\ISBN $isbnObj ISBN object
     *
     * @throws \Exception
     * @return array|string    Returns HTML string with preview image and link to TOC PDF file
     */
    public function loadByIsbn($key, \VuFindCode\ISBN $isbnObj)
    {
        $ids = [
            'isbn' => $isbnObj,
        ];
        $data = $this->service->getData($ids);
        $toc = '';
        if (isset($data->toc_thumbnail_url) && isset($data->toc_pdf_url)) {
            $toc = '<p><a href="%s" target="_blank" ><img src="%s"></a></p>';
            $toc = sprintf($toc, htmlspecialchars($data->toc_pdf_url), htmlspecialchars($data->toc_thumbnail_url));
        }
        return $toc;
    }
}
