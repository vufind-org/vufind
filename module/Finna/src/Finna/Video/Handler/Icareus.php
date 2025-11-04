<?php

/**
 * Class for Icareus video handling.
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
 * @package  Video
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Video\Handler;

/**
 * Class for Icareus video handling.
 *
 * @category VuFind
 * @package  Video
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Icareus extends \Finna\Video\Handler\AbstractBase
{
    /**
     * Get url for icareus video.
     *
     * @param string $id ID of the video.
     *
     * @return array
     */
    protected function getIcareusUrl(string $id): string
    {
        return str_replace(
            '{videoid}',
            $id,
            $this->config['url']
        );
    }

    /**
     * Convert given array into array containing videos.
     *
     * @param array $data To convert.
     *
     * @return array
     */
    public function getData(array $data): array
    {
        $results = parent::getData($data);
        foreach ($data as $media) {
            if (empty($media['id'])) {
                continue;
            }
            $results[] = [
                'url' => $this->getIcareusUrl($media['id']),
                'posterUrl' => $this->getPosterUrl($media['posterName']),
                'text' => $media['type'],
                'desc' => $media['type'],
                'source' => $this->source,
                'embed' => 'iframe',
                'warnings' => $media['warnings'],
            ];
        }
        return $results;
    }
}
