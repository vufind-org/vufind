<?php

/**
 * Class for text messaging via Clickatell's HTTP API
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2009.
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
 * @package  SMS
 * @author   Ronan McHugh <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\SMS;

use VuFind\Exception\SMS as SMSException;

use function function_exists;

/**
 * Class for text messaging via Clickatell's HTTP API
 *
 * @category VuFind
 * @package  SMS
 * @author   Ronan McHugh <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Clickatell extends AbstractBase
{
    /**
     * HTTP client
     *
     * @var \Laminas\Http\Client
     */
    protected $client;

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $config  SMS configuration
     * @param array                  $options Additional options (client may be an
     * HTTP client object)
     */
    public function __construct(\Laminas\Config\Config $config, $options = [])
    {
        parent::__construct($config);
        $this->client = $options['client'] ?? new \Laminas\Http\Client();
    }

    /**
     * Send a text message to the specified provider.
     *
     * @param string $provider The provider ID to send to
     * @param string $to       The phone number at the provider
     * @param string $from     The email address to use as sender
     * @param string $message  The message to send
     *
     * @throws \VuFind\Exception\Mail
     * @return void
     */
    public function text($provider, $to, $from, $message)
    {
        $url = $this->getApiUrl($to, $message);
        try {
            $result = $this->client->setMethod('GET')->setUri($url)->send();
        } catch (\Exception $e) {
            throw new SMSException($e->getMessage(), SMSException::ERROR_UNKNOWN);
        }
        $response = $result->isSuccess() ? trim($result->getBody()) : '';
        if (empty($response)) {
            throw new SMSException('Problem sending text.', SMSException::ERROR_UNKNOWN);
        }
        if (!str_starts_with($response, 'ID:')) {
            throw new SMSException($response, SMSException::ERROR_UNKNOWN);
        }
        return true;
    }

    /**
     * Get a list of carriers supported by the module. Returned as an array of
     * associative arrays indexed by carrier ID and containing "name" and "domain"
     * keys.
     *
     * @return array
     */
    public function getCarriers()
    {
        return [
            'Clickatell' => ['name' => 'Clickatell', 'domain' => null],
        ];
    }

    /**
     * Get API username.
     *
     * @return string
     */
    protected function getApiUsername()
    {
        return $this->smsConfig->Clickatell->user ?? null;
    }

    /**
     * Get API password.
     *
     * @return string
     */
    protected function getApiPassword()
    {
        return $this->smsConfig->Clickatell->password ?? null;
    }

    /**
     * Get API ID.
     *
     * @return string
     */
    protected function getApiId()
    {
        return $this->smsConfig->Clickatell->api_id ?? null;
    }

    /**
     * Get API URL.
     *
     * @param string $to      The phone number at the provider
     * @param string $message The message to send
     *
     * @return string
     */
    protected function getApiUrl($to, $message)
    {
        // Get base URL:
        $url = isset($this->smsConfig->Clickatell->url)
            ? trim($this->smsConfig->Clickatell->url, '?')
            : 'https://api.clickatell.com/http/sendmsg';

        // Add parameters to URL:
        $url .= '?api_id=' . urlencode($this->getApiId());
        $url .= '&user=' . urlencode($this->getApiUsername());
        $url .= '&password=' . urlencode($this->getApiPassword());
        $url .= '&to=' . urlencode($this->filterPhoneNumber($to));
        $url .= '&text=' . urlencode($this->formatMessage($message));

        return $url;
    }

    /**
     * Format message for texting.
     *
     * @param string $message Message to format
     *
     * @return string
     */
    protected function formatMessage($message)
    {
        // Clickatell expects UCS-2 encoding:
        if (!function_exists('iconv')) {
            throw new SMSException(
                'Clickatell requires iconv PHP extension.',
                SMSException::ERROR_UNKNOWN
            );
        }
        // Normalize UTF-8 if intl extension is installed:
        if (class_exists('Normalizer')) {
            $message = \Normalizer::normalize($message);
        }
        $message = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $message);

        // We need to trim to 160 bytes (note that we need to use substr and not
        // mb_substr, because the limit is BYTES not CHARACTERS; this may result
        // in broken multi-byte characters but it seems unavoidable):
        return substr($message, 0, 160);
    }
}
