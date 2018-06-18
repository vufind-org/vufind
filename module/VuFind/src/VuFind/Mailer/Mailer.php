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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Mailer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Mailer;
use VuFind\Exception\Mail as MailException,
    Zend\Mail\Address,
    Zend\Mail\AddressList,
    Zend\Mail\Message,
    Zend\Mail\Header\ContentType;

/**
 * VuFind Mailer Class
 *
 * @category VuFind
 * @package  Mailer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Mailer implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Mail transport
     *
     * @var \Zend\Mail\Transport\TransportInterface
     */
    protected $transport;

    /**
     * The maximum number of email recipients allowed (0 = no limit)
     *
     * @var int
     */
    protected $maxRecipients = 1;

    /**
     * Constructor
     *
     * @param \Zend\Mail\Transport\TransportInterface $transport Mail transport
     */
    public function __construct(\Zend\Mail\Transport\TransportInterface $transport)
    {
        $this->setTransport($transport);
    }

    /**
     * Get the mail transport object.
     *
     * @return \Zend\Mail\Transport\TransportInterface
     */
    public function getTransport()
    {
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
        $ctype = new ContentType();
        $ctype->setType('text/plain');
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
     * Convert a delimited string to an address list.
     *
     * @param string $input String to convert
     *
     * @return AddressList
     */
    public function stringToAddressList($input)
    {
        // Create recipient list
        $list = new AddressList();
        foreach (preg_split('/[\s,;]/', $input) as $current) {
            $current = trim($current);
            if (!empty($current)) {
                $list->add($current);
            }
        }
        return $list;
    }

    /**
     * Send an email message.
     *
     * @param string|Address|AddressList $to      Recipient email address (or
     * delimited list)
     * @param string|Address             $from    Sender name and email address
     * @param string                     $subject Subject line for message
     * @param string                     $body    Message body
     * @param string                     $cc      CC recipient (null for none)
     *
     * @throws MailException
     * @return void
     */
    public function send($to, $from, $subject, $body, $cc = null)
    {
        if ($to instanceof AddressList) {
            $recipients = $to;
        } else if ($to instanceof Address) {
            $recipients = new AddressList();
            $recipients->add($to);
        } else {
            $recipients = $this->stringToAddressList($to);
        }

        // Validate email addresses:
        if ($this->maxRecipients > 0
            && $this->maxRecipients < count($recipients)
        ) {
            throw new MailException('Too Many Email Recipients');
        }
        $validator = new \Zend\Validator\EmailAddress();
        if (count($recipients) == 0) {
            throw new MailException('Invalid Recipient Email Address');
        }
        foreach ($recipients as $current) {
            if (!$validator->isValid($current->getEmail())) {
                throw new MailException('Invalid Recipient Email Address');
            }
        }
        $fromEmail = ($from instanceof Address)
            ? $from->getEmail() : $from;
        if (!$validator->isValid($fromEmail)) {
            throw new MailException('Invalid Sender Email Address');
        }
        // Convert all exceptions thrown by mailer into MailException objects:
        try {
            // Send message
            $message = $this->getNewMessage()
                ->addFrom($from)
                ->addTo($recipients)
                ->setBody($body)
                ->setSubject($subject)
                ->setReplyTo($from);
            if ($cc !== null) {
                $message->addCc($cc);
            }
            $this->getTransport()->send($message);
        } catch (\Exception $e) {
            throw new MailException($e->getMessage());
        }
    }

    /**
     * Send an email message representing a link.
     *
     * @param string                          $to      Recipient email address
     * @param string|\Zend\Mail\Address       $from    Sender name and email address
     * @param string                          $msg     User notes to include in
     * message
     * @param string                          $url     URL to share
     * @param \Zend\View\Renderer\PhpRenderer $view    View object (used to render
     * email templates)
     * @param string                          $subject Subject for email (optional)
     * @param string                          $cc      CC recipient (null for none)
     *
     * @throws MailException
     * @return void
     */
    public function sendLink($to, $from, $msg, $url, $view, $subject = null,
        $cc = null
    ) {
        if (null === $subject) {
            $subject = $this->getDefaultLinkSubject();
        }
        $body = $view->partial(
            'Email/share-link.phtml',
            [
                'msgUrl' => $url, 'to' => $to, 'from' => $from, 'message' => $msg
            ]
        );
        return $this->send($to, $from, $subject, $body, $cc);
    }

    /**
     * Get the default subject line for sendLink().
     *
     * @return string
     */
    public function getDefaultLinkSubject()
    {
        return $this->translate('Library Catalog Search Result');
    }

    /**
     * Send an email message representing a record.
     *
     * @param string                            $to      Recipient email address
     * @param string|\Zend\Mail\Address         $from    Sender name and email
     * address
     * @param string                            $msg     User notes to include in
     * message
     * @param \VuFind\RecordDriver\AbstractBase $record  Record being emailed
     * @param \Zend\View\Renderer\PhpRenderer   $view    View object (used to render
     * email templates)
     * @param string                            $subject Subject for email (optional)
     * @param string                            $cc      CC recipient (null for none)
     *
     * @throws MailException
     * @return void
     */
    public function sendRecord($to, $from, $msg, $record, $view, $subject = null,
        $cc = null
    ) {
        if (null === $subject) {
            $subject = $this->getDefaultRecordSubject($record);
        }
        $body = $view->partial(
            'Email/record.phtml',
            [
                'driver' => $record, 'to' => $to, 'from' => $from, 'message' => $msg
            ]
        );
        return $this->send($to, $from, $subject, $body, $cc);
    }

    /**
     * Set the maximum number of email recipients
     *
     * @param int $max Maximum
     *
     * @return void
     */
    public function setMaxRecipients($max)
    {
        $this->maxRecipients = $max;
    }

    /**
     * Get the default subject line for sendRecord()
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record being emailed
     *
     * @return string
     */
    public function getDefaultRecordSubject($record)
    {
        return $this->translate('Library Catalog Record') . ': '
            . $record->getBreadcrumb();
    }
}
