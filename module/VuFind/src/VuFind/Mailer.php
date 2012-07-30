<?php
/**
 * VuFind Mailer Class
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
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
namespace VuFind;
use VuFind\Config\Reader as ConfigReader, VuFind\Exception\Mail as MailException,
    VuFind\Translator\Translator, Zend\Mail\Message, Zend\Mail\Transport\Smtp,
    Zend\Mail\Transport\SmtpOptions;

/**
 * VuFind Mailer Class
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
class Mailer
{
    protected $config;
    protected $transport;

    /**
     * Constructor
     *
     * @param \Zend\Mail\Transport\TransportInterface $transport Mail transport
     * object (we'll build our own if none is provided).
     * @param \Zend\Config\Config                     $config    VuFind configuration
     * object (we'll auto-load if none is provided).
     */
    public function __construct($transport = null, $config = null)
    {
        if (!is_null($transport)) {
            $this->setTransport($transport);
        }
        $this->config = is_null($config) ? ConfigReader::getConfig() : $config;
    }

    /**
     * Get the mail transport object.
     *
     * @return \Zend\Mail\Transport\TransportInterface
     */
    public function getTransport()
    {
        // Create transport if it does not already exist:
        if (is_null($this->transport)) {
            $settings = array (
                'host' => $this->config->Mail->host,
                'port' => $this->config->Mail->port
            );
            if (isset($this->config->Mail->username)
                && isset($this->config->Mail->password)
            ) {
                $settings['connection_class'] = 'login';
                $settings['connection_config'] = array(
                    'username' => $this->config->Mail->username,
                    'password' => $this->config->Mail->password
                );
            }
            $this->transport = new Smtp();
            $this->transport->setOptions(new SmtpOptions($settings));
        }
        return $this->transport;
    }

    /**
     * Get a blank email message object.
     *
     * @return Message
     */
    public function getNewMessage()
    {
        $message = new Message();
        $message->setEncoding('UTF-8');
        $headers = $message->getHeaders();
        $ctype = new \Zend\Mail\Header\ContentType();
        $ctype->addParameter('charset', 'UTF-8');
        $headers->addHeader($ctype);
        return $message;
    }

    /**
     * Set the mail transport object.
     *
     * @param \Zend\Mail\Transport\TransportInterface $transport Mail transport
     * object
     *
     * @return void
     */
    public function setTransport($transport)
    {
        $this->transport = $transport;
    }

    /**
     * Send an email message.
     *
     * @param string $to      Recipient email address
     * @param string $from    Sender email address
     * @param string $subject Subject line for message
     * @param string $body    Message body
     *
     * @throws MailException
     * @return void
     */
    public function send($to, $from, $subject, $body)
    {
        // Validate sender and recipient
        $validator = new \Zend\Validator\EmailAddress();
        if (!$validator->isValid($to)) {
            throw new MailException('Invalid Recipient Email Address');
        }
        if (!$validator->isValid($from)) {
            throw new MailException('Invalid Sender Email Address');
        }

        // Convert all exceptions thrown by mailer into MailException objects:
        try {
            // Send message
            $message = $this->getNewMessage()
                ->addFrom($from)
                ->addTo($to)
                ->setBody($body)
                ->setSubject($subject);
            $this->getTransport()->send($message);
        } catch (\Exception $e) {
            throw new MailException($e->getMessage());
        }
    }

    /**
     * Send an email message representing a link.
     *
     * @param string    $to      Recipient email address
     * @param string    $from    Sender email address
     * @param string    $msg     User notes to include in message
     * @param string    $url     URL to share
     * @param Zend_View $view    View object (used to render email templates)
     * @param string    $subject Subject for email (optional)
     *
     * @throws MailException
     * @return void
     */
    public function sendLink($to, $from, $msg, $url, $view, $subject = null)
    {
        if (is_null($subject)) {
            $subject = 'Library Catalog Search Result';
        }
        $subject = Translator::translate($subject);
        $body = $view->partial(
            'Email/share-link.phtml',
            array(
                'msgUrl' => $url, 'to' => $to, 'from' => $from, 'message' => $msg
            )
        );
        return $this->send($to, $from, $subject, $body);
    }

    /**
     * Send an email message representing a record.
     *
     * @param string                            $to     Recipient email address
     * @param string                            $from   Sender email address
     * @param string                            $msg    User notes to include in
     * message
     * @param \VuFind\RecordDriver\AbstractBase $record Record being emailed
     * @param Zend_View                         $view   View object (used to render
     * email templates)
     *
     * @throws MailException
     * @return void
     */
    public function sendRecord($to, $from, $msg, $record, $view)
    {
        $subject = Translator::translate('Library Catalog Record') . ': '
            . $record->getBreadcrumb();
        $body = $view->partial(
            'Email/record.phtml',
            array(
                'driver' => $record, 'to' => $to, 'from' => $from, 'message' => $msg
            )
        );
        return $this->send($to, $from, $subject, $body);
    }
}