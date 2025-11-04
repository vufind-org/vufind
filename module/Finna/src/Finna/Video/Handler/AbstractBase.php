<?php

/**
 * Base for video services.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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

use function in_array;

/**
 * Base for video services.
 *
 * @category VuFind
 * @package  Video
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractBase implements \Psr\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Datasource config as array
     *
     * @var array
     */
    protected $config;

    /**
     * Source identifier
     *
     * @var string
     */
    protected $source;

    /**
     * Array of required configuration settings
     *
     * @var array
     */
    protected $required = [];

    /**
     * Convert given array into array containing videos.
     *
     * @param array $data To convert.
     *
     * @return array
     */
    public function getData(array $data): array
    {
        $sourceConfig = $this->getSourceConfig();
        $results = [];
        foreach ($data as $media) {
            if (empty($media['url'])) {
                continue;
            }
            $videoSources = [];
            $sourceType = strtolower(pathinfo($media['url'], PATHINFO_EXTENSION));
            foreach ($sourceConfig as $conf) {
                if (!in_array($sourceType, $conf['sourceTypes'])) {
                    continue;
                }
                $videoSources[] = [
                    'src' => str_replace(
                        '{videoname}',
                        $media['url'],
                        $conf['src']
                    ),
                    'type' => $conf['mediaType'],
                    'priority' => $conf['priority'],
                ];
            }
            if (!$videoSources) {
                continue;
            }
            usort(
                $videoSources,
                function ($a, $b) {
                    return $a['priority'] - $b['priority'];
                }
            );
            $results[] = [
                'url' => $media['url'],
                'posterUrl' => $this->getPosterUrl($media['posterName']),
                'videoSources' => $videoSources,
                'text' => $media['type'],
                'desc' => $media['type'],
                'source' => $this->source,
                'embed' => 'video',
                'warnings' => $media['warnings'],
            ];
        }
        return $results;
    }

    /**
     * Init the module.
     *
     * @param array  $config Datasource config
     * @param string $source Source identifier
     *
     * @return void
     */
    public function init(array $config, string $source): void
    {
        $this->config = $config;
        $this->source = $source;
    }

    /**
     * Get the poster url with the given file name.
     *
     * @param string $fileName Datasource config
     *
     * @return string
     */
    protected function getPosterUrl(string $fileName): string
    {
        if ($posterSource = $this->config['posterUrl'] ?? '') {
            return str_replace(
                '{filename}',
                $fileName,
                $posterSource
            );
        }
        return '';
    }

    /**
     * Get source config if specified for default videos.
     *
     * @return array
     */
    protected function getSourceConfig(): array
    {
        $results = [];
        $sourcePriority = 0;
        foreach ($this->config['video_sources'] ?? [] as $current) {
            $settings = explode('|', $current, 3);
            if (!isset($settings[0])) {
                continue;
            }
            $results[] = [
                'mediaType' => $settings[0],
                'src' => $settings[1],
                'sourceTypes' => explode(',', $settings[2] ?? 'mp4'),
                'priority' => $sourcePriority++,
            ];
        }
        return $results;
    }

    /**
     * Verify that required array keys have been given a value in config.
     *
     * @return bool
     */
    public function verifyConfig(): bool
    {
        foreach ($this->required as $req) {
            if (!isset($this->config[$req])) {
                $this->logError(
                    "Video datasource configuration missing setting: $req" .
                    " for source: {$this->source}."
                );
                return false;
            }
        }
        return true;
    }
}
