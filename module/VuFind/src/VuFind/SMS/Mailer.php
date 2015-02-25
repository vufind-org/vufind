<?php
/**
 * VuFind Mailer Class for SMS messages
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  SMS
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\SMS;
use VuFind\Exception\Mail as MailException;

/**
 * VuFind Mailer Class for SMS messages
 *
 * @category VuFind2
 * @package  SMS
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Mailer extends AbstractBase
{
    /**
     * Default carriers, usually overridden by contents of web/conf/sms.ini.
     *
     * @var array
     */
    protected $carriers = [
        'virgin' => ['name' => 'Virgin Mobile', 'domain' => 'vmobl.com'],
        'att' => ['name' => 'AT&T', 'domain' => 'mms.att.net'],
        'verizon' => ['name' => 'Verizon', 'domain' => 'vtext.com'],
        'nextel' => ['name' => 'Nextel', 'domain' => 'messaging.nextel.com'],
        'sprint' => ['name' => 'Sprint', 'domain' => 'messaging.sprintpcs.com'],
        'tmobile' => ['name' => 'T Mobile', 'domain' => 'tmomail.net'],
        'alltel' => ['name' => 'Alltel', 'domain' => 'message.alltel.com'],
        'Cricket' => ['name' => 'Cricket', 'domain' => 'mms.mycricket.com']
    ];

    /**
     * Default "from" address
     *
     * @var string
     */
    protected $defaultFrom;

    /**
     * VuFind Mailer object
     *
     * @var \VuFind\Mailer\Mailer
     */
    protected $mailer;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config  SMS configuration
     * @param array               $options Additional options: defaultFrom (optional)
     * and mailer (must be a \VuFind\Mailer\Mailer object)
     */
    public function __construct(\Zend\Config\Config $config, $options = [])
    {
        // Set up parent object first:
        parent::__construct($config, $options);

        // If found, use carriers from SMS configuration; otherwise, fall back to the
        // default list of US carriers.
        if (isset($config->Carriers) && count($config->Carriers) > 0) {
            $this->carriers = [];
            foreach ($config->Carriers as $id => $settings) {
                list($domain, $name) = explode(':', $settings, 2);
                $this->carriers[$id] = ['name' => $name, 'domain' => $domain];
            }
        }

        // Load default "from" address:
        $this->defaultFrom
            = isset($options['defaultFrom']) ? $options['defaultFrom'] : '';

        // Make sure mailer dependency has been injected:
        if (!isset($options['mailer'])
            || !($options['mailer'] instanceof \VuFind\Mailer\Mailer)
        ) {
            throw new \Exception(
                '$options["mailer"] must be a \VuFind\Mailer\Mailer'
            );
        }
        $this->mailer = $options['mailer'];
    }

    /**
     * Get a list of carriers supported by the module.  Returned as an array of
     * associative arrays indexed by carrier ID and containing "name" and "domain"
     * keys.
     *
     * @return array
     */
    public function getCarriers()
    {
        return $this->carriers;
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
        $knownCarriers = array_keys($this->carriers);
        if (empty($provider) || !in_array($provider, $knownCarriers)) {
            throw new MailException('Unknown Carrier');
        }

        $to = $this->filterPhoneNumber($to)
            . '@' . $this->carriers[$provider]['domain'];
        $from = empty($from) ? $this->defaultFrom : $from;
        $subject = '';
        return $this->mailer->send($to, $from, $subject, $message);
    }
}