<?php

/**
 * HTTP POST log writer for Office365 webhooks.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @package  Error_Logging
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Log\Writer;

use Laminas\Http\Client;

/**
 * This class extends the Laminas Logging to send errors to Office365 webhooks.
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Office365 extends Post
{
    /**
     * The title for generated cards.
     *
     * @var string
     */
    protected $title;

    /**
     * Constructor
     *
     * @param string $url     URL to open as a stream
     * @param Client $client  Pre-configured http client
     * @param array  $options Optional settings (may contain 'channel' for the
     * Slack channel to use and/or 'name' for the username messages are posted under)
     *
     * @throws \Exception
     */
    public function __construct($url, Client $client, array $options = [])
    {
        $this->title = $options['title'] ?? 'VuFind Log';
        parent::__construct($url, $client);
    }

    /**
     * Get data for raw body
     *
     * @param array $event event data
     *
     * @return string
     */
    protected function getBody($event)
    {
        $data = [
            '@context' => 'https://schema.org/extensions',
            '@type' => 'MessageCard',
            'themeColor' => '0072C6',
            'title' => $this->title,
            'text' => $this->formatter->format($event),
        ];
        return json_encode($data);
    }
}
