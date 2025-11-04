<?php

/**
 * National Library of Finland description provider.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @package  Content
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Content\Description;

use VuFind\RecordDriver\DefaultRecord;
use VuFindHttp\HttpServiceAwareInterface;
use VuFindHttp\HttpServiceAwareTrait;

/**
 * National Library of Finland description provider.
 *
 * @category VuFind
 * @package  Content
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class NatLibFi extends AbstractDescriptionProvider implements HttpServiceAwareInterface
{
    use HttpServiceAwareTrait;

    /**
     * Get description URL for a particular API key and record.
     *
     * @param string        $key    API key
     * @param DefaultRecord $record Record driver
     *
     * @return string Ready-to-display HTML or an empty string on error
     */
    public function get(string $key, DefaultRecord $record): string
    {
        if (!($isbn = $record->getCleanISBN())) {
            return '';
        }
        $url = 'https://kansikuvat.finna.fi/getText.php?query=' . urlencode($isbn);
        $response = $this->httpService->get($url, [], 60);
        if (!$response->isSuccess()) {
            return '';
        }
        return $this->processResponse($response);
    }
}
