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
 * @package  Mailer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
namespace VuFind\Mailer;
use VuFind\Config\Reader as ConfigReader, VuFind\Exception\Mail as MailException,
    VuFind\Mailer;

/**
 * VuFind Mailer Class for SMS messages
 *
 * @category VuFind2
 * @package  Mailer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
class SMS extends Mailer
{
    // Defaults, usually overridden by contents of sms.ini:
    protected $carriers = array(
        'virgin' => array('name' => 'Virgin Mobile', 'domain' => 'vmobl.com'),
        'att' => array('name' => 'AT&T', 'domain' => 'txt.att.net'),
        'verizon' => array('name' => 'Verizon', 'domain' => 'vtext.com'),
        'nextel' => array('name' => 'Nextel', 'domain' => 'messaging.nextel.com'),
        'sprint' => array('name' => 'Sprint', 'domain' => 'messaging.sprintpcs.com'),
        'tmobile' => array('name' => 'T Mobile', 'domain' => 'tmomail.net'),
        'alltel' => array('name' => 'Alltel', 'domain' => 'message.alltel.com'),
        'Cricket' => array('name' => 'Cricket', 'domain' => 'mms.mycricket.com')
    );

    // Default "from" address:
    protected $defaultFrom;

    /**
     * Constructor
     *
     * Sets up SMS carriers and other settings from sms.ini.
     *
     * @param \Zend\Mail\Transport\TransportInterface $transport Mail transport
     * object (we'll build our own if none is provided).
     * @param \Zend\Config\Config                     $config    VuFind configuration
     * object (we'll auto-load if none is provided).
     */
    public function __construct($transport = null, $config = null)
    {
        // Set up parent object first:
        parent::__construct($transport, $config);

        // if using sms.ini, then load the carriers from it
        // otherwise, fall back to the default list of US carriers
        $smsConfig = ConfigReader::getConfig('sms');
        if (isset($smsConfig->Carriers) && count($smsConfig->Carriers) > 0) {
            $this->carriers = array();
            foreach ($smsConfig->Carriers as $id=>$settings) {
                list($domain, $name) = explode(':', $settings, 2);
                $this->carriers[$id] = array('name'=>$name, 'domain'=>$domain);
            }
        }

        // Load default "from" address:
        $this->defaultFrom = isset($this->config->Site->email)
            ? $this->config->Site->email : '';
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
     * @throws MailException
     * @return mixed           PEAR error on error, boolean true otherwise
     */
    public function text($provider, $to, $from, $message)
    {
        $knownCarriers = array_keys($this->carriers);
        if (empty($provider) || !in_array($provider, $knownCarriers)) {
            throw new MailException('Unknown Carrier');
        }

        $badChars = array('-', '.', '(', ')', ' ');
        $to = str_replace($badChars, '', $to);
        $to = $to . '@' . $this->carriers[$provider]['domain'];
        $from = empty($from) ? $this->defaultFrom : $from;
        $subject = '';
        return $this->send($to, $from, $subject, $message);
    }

    /**
     * Send a text message representing a record.
     *
     * @param string                                $provider Target SMS provider
     * @param string                                $to       Recipient phone number
     * @param \VuFind\RecordDriver\AbstractBase     $record   Record being emailed
     * @param \Zend\View\Renderer\RendererInterface $view     View object (used to
     * render email templates)
     *
     * @throws MailException
     * @return void
     */
    public function textRecord($provider, $to, $record, $view)
    {
        $body = $view->partial(
            'Email/record-sms.phtml', array('driver' => $record, 'to' => $to)
        );
        return $this->text($provider, $to, null, $body);
    }
}