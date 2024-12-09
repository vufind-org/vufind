<?php

/**
 * VuFind Mailer Class
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
 * @package  Mailer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Mailer;

use Laminas\Mail\Address;
use Laminas\Mail\AddressList;
use Laminas\Mail\Header\ContentType;
use Laminas\Mail\Transport\TransportInterface;
use Laminas\Mime\Message as MimeMessage;
use Laminas\Mime\Mime;
use Laminas\Mime\Part as MimePart;
use VuFind\Exception\Mail as MailException;

use function count;
use function is_callable;

/**
 * VuFind Mailer Class
 *
 * @category VuFind
 * @package  Mailer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Mailer implements
    \VuFind\I18n\Translator\TranslatorAwareInterface,
    \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Mail transport
     *
     * @var TransportInterface
     */
    protected $transport;

    /**
     * A clone of $transport above. This can be used to reset the connection state
     * in case transport doesn't support the disconnect method or it throws an
     * exception (this can happen if the connection is stale and the connector tries
     * to issue a QUIT message for clean disconnect).
     *
     * @var TransportInterface
     */
    protected $initialTransport;

    /**
     * The maximum number of email recipients allowed (0 = no limit)
     *
     * @var int
     */
    protected $maxRecipients = 1;

    /**
     * "From" address override
     *
     * @var string
     */
    protected $fromAddressOverride = '';

    /**
     * Constructor
     *
     * @param TransportInterface $transport  Mail transport
     * @param ?string            $messageLog File to log messages into (null for no logging)
     */
    public function __construct(TransportInterface $transport, protected ?string $messageLog = null)
    {
        $this->setTransport($transport);
    }

    /**
     * Get the mail transport object.
     *
     * @return TransportInterface
     */
    public function getTransport()
    {
        return $this->transport;
    }

    /**
     * Get a text email message object.
     *
     * @return Message
     */
    public function getNewMessage()
    {
        $message = $this->getNewBlankMessage();
        $headers = $message->getHeaders();
        $ctype = new ContentType();
        $ctype->setType(Mime::TYPE_TEXT);
        $ctype->addParameter('charset', 'UTF-8');
        $headers->addHeader($ctype);
        return $message;
    }

    /**
     * Reset the connection in the transport. Implements a fluent interface.
     *
     * @return Mailer
     */
    public function resetConnection()
    {
        // If the transport has a disconnect method, call it. Otherwise, and in case
        // disconnect fails, revert to the transport instance clone made before a
        // connection was made.
        $transport = $this->getTransport();
        if (is_callable([$transport, 'disconnect'])) {
            try {
                $transport->disconnect();
            } catch (\Exception $e) {
                $this->setTransport($this->initialTransport);
            }
        } else {
            $this->setTransport($this->initialTransport);
        }
        return $this;
    }

    /**
     * Get a blank email message object.
     *
     * @return Message
     */
    public function getNewBlankMessage()
    {
        $message = new Message();
        $message->setEncoding('UTF-8');
        return $message;
    }

    /**
     * Set the mail transport object.
     *
     * @param TransportInterface $transport Mail transport object
     *
     * @return void
     */
    public function setTransport($transport)
    {
        $this->transport = $transport;
        // Store a clone of the given transport so that we can reset the connection
        // as necessary.
        $this->initialTransport = clone $this->transport;
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
     * Constructs a {@see MimeMessage} body from given text and html content.
     *
     * @param string|null $text Mail content used for plain text part
     * @param string|null $html Mail content used for html part
     *
     * @return MimeMessage
     */
    public function buildMultipartBody(
        string $text = null,
        string $html = null
    ): MimeMessage {
        $parts = new MimeMessage();

        if ($text) {
            $textPart = new MimePart($text);
            $textPart->setType(Mime::TYPE_TEXT);
            $textPart->setCharset('utf-8');
            $textPart->setEncoding(Mime::ENCODING_QUOTEDPRINTABLE);
            $parts->addPart($textPart);
        }

        if ($html) {
            $htmlPart = new MimePart($html);
            $htmlPart->setType(Mime::TYPE_HTML);
            $htmlPart->setCharset('utf-8');
            $htmlPart->setEncoding(Mime::ENCODING_QUOTEDPRINTABLE);
            $parts->addPart($htmlPart);
        }

        $alternativePart = new MimePart($parts->generateMessage());
        $alternativePart->setType('multipart/alternative');
        $alternativePart->setBoundary($parts->getMime()->boundary());
        $alternativePart->setCharset('utf-8');

        $body = new MimeMessage();
        $body->setParts([$alternativePart]);

        return $body;
    }

    /**
     * Send an email message.
     *
     * @param string|Address|AddressList $to      Recipient email address (or
     * delimited list)
     * @param string|Address             $from    Sender name and email address
     * @param string                     $subject Subject line for message
     * @param string|MimeMessage         $body    Message body
     * @param string                     $cc      CC recipient (null for none)
     * @param string|Address|AddressList $replyTo Reply-To address (or delimited
     * list, null for none)
     *
     * @throws MailException
     * @return void
     */
    public function send($to, $from, $subject, $body, $cc = null, $replyTo = null)
    {
        $recipients = $this->convertToAddressList($to);
        $replyTo = $this->convertToAddressList($replyTo);

        // Validate email addresses:
        if ($this->maxRecipients > 0) {
            if ($this->maxRecipients < count($recipients)) {
                throw new MailException(
                    'Too Many Email Recipients',
                    MailException::ERROR_TOO_MANY_RECIPIENTS
                );
            }
        }
        $validator = new \Laminas\Validator\EmailAddress();
        if (count($recipients) == 0) {
            throw new MailException(
                'Invalid Recipient Email Address',
                MailException::ERROR_INVALID_RECIPIENT
            );
        }
        foreach ($recipients as $current) {
            if (!$validator->isValid($current->getEmail())) {
                throw new MailException(
                    'Invalid Recipient Email Address',
                    MailException::ERROR_INVALID_RECIPIENT
                );
            }
        }
        foreach ($replyTo as $current) {
            if (!$validator->isValid($current->getEmail())) {
                throw new MailException(
                    'Invalid Reply-To Email Address',
                    MailException::ERROR_INVALID_REPLY_TO
                );
            }
        }
        $fromEmail = ($from instanceof Address)
            ? $from->getEmail() : $from;
        if (!$validator->isValid($fromEmail)) {
            throw new MailException(
                'Invalid Sender Email Address',
                MailException::ERROR_INVALID_SENDER
            );
        }

        if (
            !empty($this->fromAddressOverride)
            && $this->fromAddressOverride != $fromEmail
        ) {
            // Add the original from address as the reply-to address unless
            // a reply-to address has been specified
            if (count($replyTo) === 0) {
                $replyTo->add($fromEmail);
            }
            if (!($from instanceof Address)) {
                $from = new Address($from);
            }
            $name = $from->getName();
            if (!$name) {
                [$fromPre] = explode('@', $from->getEmail());
                $name = $fromPre ? $fromPre : null;
            }
            $from = new Address($this->fromAddressOverride, $name);
        }

        // Convert all exceptions thrown by mailer into MailException objects:
        try {
            // Send message
            $message = $body instanceof MimeMessage
                ? $this->getNewBlankMessage()
                : $this->getNewMessage();
            $message->addFrom($from)
                ->addTo($recipients)
                ->setBody($body)
                ->setSubject($subject);
            if ($cc !== null) {
                $message->addCc($cc);
            }
            if ($replyTo) {
                $message->addReplyTo($replyTo);
            }
            $this->getTransport()->send($message);
            if ($this->messageLog) {
                file_put_contents($this->messageLog, $message->toString() . "\n", FILE_APPEND);
            }
        } catch (\Exception $e) {
            $this->logError($e->getMessage());
            throw new MailException($e->getMessage(), MailException::ERROR_UNKNOWN);
        }
    }

    /**
     * Send an email message representing a link.
     *
     * @param string                             $to      Recipient email address
     * @param string|\Laminas\Mail\Address       $from    Sender name and email
     * address
     * @param string                             $msg     User notes to include in
     * message
     * @param string                             $url     URL to share
     * @param \Laminas\View\Renderer\PhpRenderer $view    View object (used to render
     * email templates)
     * @param string                             $subject Subject for email
     * (optional)
     * @param string                             $cc      CC recipient (null for
     * none)
     * @param string|Address|AddressList         $replyTo Reply-To address (or
     * delimited list, null for none)
     *
     * @throws MailException
     * @return void
     */
    public function sendLink(
        $to,
        $from,
        $msg,
        $url,
        $view,
        $subject = null,
        $cc = null,
        $replyTo = null
    ) {
        if (null === $subject) {
            $subject = $this->getDefaultLinkSubject();
        }
        $body = $view->partial(
            'Email/share-link.phtml',
            [
                'msgUrl' => $url, 'to' => $to, 'from' => $from, 'message' => $msg,
            ]
        );
        $this->send($to, $from, $subject, $body, $cc, $replyTo);
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
     * @param string                             $to      Recipient email address
     * @param string|\Laminas\Mail\Address       $from    Sender name and email
     * address
     * @param string                             $msg     User notes to include in
     * message
     * @param \VuFind\RecordDriver\AbstractBase  $record  Record being emailed
     * @param \Laminas\View\Renderer\PhpRenderer $view    View object (used to render
     * email templates)
     * @param string                             $subject Subject for email
     * (optional)
     * @param string                             $cc      CC recipient (null for
     * none)
     * @param string|Address|AddressList         $replyTo Reply-To address (or
     * delimited list, null for none)
     *
     * @throws MailException
     * @return void
     */
    public function sendRecord(
        $to,
        $from,
        $msg,
        $record,
        $view,
        $subject = null,
        $cc = null,
        $replyTo = null
    ) {
        if (null === $subject) {
            $subject = $this->getDefaultRecordSubject($record);
        }
        $body = $view->partial(
            'Email/record.phtml',
            [
                'driver' => $record, 'to' => $to, 'from' => $from, 'message' => $msg,
            ]
        );
        $this->send($to, $from, $subject, $body, $cc, $replyTo);
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

    /**
     * Get the "From" address override value
     *
     * @return string
     */
    public function getFromAddressOverride()
    {
        return $this->fromAddressOverride;
    }

    /**
     * Set the "From" address override
     *
     * @param string $address "From" address
     *
     * @return void
     */
    public function setFromAddressOverride($address)
    {
        $this->fromAddressOverride = $address;
    }

    /**
     * Convert the given addresses to an AddressList object
     *
     * @param string|Address|AddressList $addresses Addresses
     *
     * @return AddressList
     */
    protected function convertToAddressList($addresses)
    {
        if ($addresses instanceof AddressList) {
            $result = $addresses;
        } elseif ($addresses instanceof Address) {
            $result = new AddressList();
            $result->add($addresses);
        } else {
            $result = $this->stringToAddressList($addresses ? $addresses : '');
        }
        return $result;
    }
}
