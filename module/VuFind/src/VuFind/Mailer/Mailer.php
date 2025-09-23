<?php

/**
 * VuFind Mailer Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2009.
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Mailer;

use Laminas\View\Renderer\PhpRenderer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use VuFind\Exception\Mail as MailException;
use VuFind\RecordDriver\AbstractBase;

use function count;
use function is_array;

/**
 * VuFind Mailer Class
 *
 * @category VuFind
 * @package  Mailer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
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
     * @var MailerInterface
     */
    protected $transport;

    /**
     * A clone of $transport above. This can be used to reset the connection state.
     *
     * @var MailerInterface
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
     * @param MailerInterface $transport Mail transport
     * @param array           $options   Message log options
     */
    public function __construct(MailerInterface $transport, protected array $options = [])
    {
        $this->setTransport($transport);
    }

    /**
     * Get the mail transport object.
     *
     * @return MailerInterface
     */
    public function getTransport(): MailerInterface
    {
        return $this->transport;
    }

    /**
     * Get an email message object.
     *
     * @return Email
     */
    public function getNewMessage(): Email
    {
        return new Email();
    }

    /**
     * Reset the connection in the transport. Implements a fluent interface.
     *
     * @return Mailer
     */
    public function resetConnection()
    {
        $this->setTransport($this->initialTransport);
        return $this;
    }

    /**
     * Get a blank email message object.
     *
     * @return Email
     *
     * @deprecated Use getNewMessage
     */
    public function getNewBlankMessage(): Email
    {
        return $this->getNewMessage();
    }

    /**
     * Set the mail transport object.
     *
     * @param MailerInterface $transport Mail transport object
     *
     * @return void
     */
    public function setTransport(MailerInterface $transport): void
    {
        $this->transport = $transport;
        // Store a clone of the given transport so that we can reset the connection as necessary:
        $this->initialTransport = clone $this->transport;
    }

    /**
     * Convert a delimited string to an address list.
     *
     * @param string $input String to convert
     *
     * @return array
     */
    public function stringToAddressList($input): array
    {
        // Create recipient list
        $list = [];
        foreach (preg_split('/[\s,;]/', $input) as $current) {
            $current = trim($current);
            if (!empty($current)) {
                $list[] = $current;
            }
        }
        return $list;
    }

    /**
     * Constructs an {@see Email} body from given text and html content.
     *
     * @param ?string $text Mail content used for plain text part
     * @param ?string $html Mail content used for html part
     *
     * @return Email
     */
    public function buildMultipartBody(
        ?string $text = null,
        ?string $html = null
    ): Email {
        $email = $this->getNewMessage();
        if (null !== $text) {
            $email->text($text);
        }
        if (null !== $html) {
            $email->html($html);
        }
        return $email;
    }

    /**
     * Send an email message.
     *
     * @param string|string[]|Address|Address[]      $to            Recipient email address(es) (or delimited list)
     * @param string|Address                         $from          Sender name and email address
     * @param string                                 $subject       Subject line for message
     * @param string|Email                           $body          Message body
     * @param string|string[]|Address|Address[]|null $cc            CC recipient(s) (null for none)
     * @param string|string[]|Address|Address[]|null $replyTo       Reply-To address(es) (or delimited list, null for
     * none)
     * @param bool                                   $subjectInBody Allow subject to be extracted from body when
     * body text begins with "Subject: " and $body is a string (ignored when $body is an Email object)
     *
     * @throws MailException
     * @return void
     */
    public function send(
        string|Address|array $to,
        string|Address $from,
        string $subject,
        string|Email $body,
        string|Address|array|null $cc = null,
        string|Address|array|null $replyTo = null,
        bool $subjectInBody = true
    ) {
        try {
            if (!($from instanceof Address)) {
                $from = new Address($from);
            }
        } catch (RfcComplianceException $e) {
            throw new MailException('Invalid Sender Email Address', MailException::ERROR_INVALID_SENDER, $e);
        }
        try {
            $recipients = $this->convertToAddressList($to);
        } catch (RfcComplianceException $e) {
            throw new MailException('Invalid Recipient Email Address', MailException::ERROR_INVALID_RECIPIENT, $e);
        }
        try {
            $replyTo = $this->convertToAddressList($replyTo);
        } catch (RfcComplianceException $e) {
            throw new MailException('Invalid Reply-To Email Address', MailException::ERROR_INVALID_REPLY_TO, $e);
        }
        try {
            $cc = $this->convertToAddressList($cc);
        } catch (RfcComplianceException $e) {
            throw new MailException('Invalid CC Email Address', MailException::ERROR_INVALID_RECIPIENT, $e);
        }

        // Validate recipient email address count:
        if (count($recipients) == 0) {
            throw new MailException('Invalid Recipient Email Address', MailException::ERROR_INVALID_RECIPIENT);
        }
        if ($this->maxRecipients > 0) {
            if ($this->maxRecipients < count($recipients)) {
                throw new MailException(
                    'Too Many Email Recipients',
                    MailException::ERROR_TOO_MANY_RECIPIENTS
                );
            }
        }

        if (
            !empty($this->fromAddressOverride)
            && $this->fromAddressOverride != $from->getAddress()
        ) {
            // Add the original from address as the reply-to address unless
            // a reply-to address has been specified
            if (!$replyTo) {
                $replyTo[] = $from->getAddress();
            }
            $name = $from->getName();
            if (!$name) {
                [$fromPre] = explode('@', $from->getAddress());
                $name = $fromPre ? $fromPre : null;
            }
            $from = new Address($this->fromAddressOverride, $name);
        }

        try {
            // Send message
            if ($body instanceof Email) {
                $email = $body;
                if (null === $email->getSubject()) {
                    $email->subject($subject);
                }
            } else {
                if ($subjectInBody) {
                    // Extract any subject line at the beginning of the message body:
                    $body = preg_replace_callback(
                        '/^Subject: (.+)\n+/',
                        function ($matches) use (&$subject) {
                            $subject = $matches[1];
                            return '';
                        },
                        $body
                    );
                }
                $email = $this->getNewMessage();
                $email->text($body);
                $email->subject($subject);
            }
            $email->addFrom($from);
            foreach ($recipients as $current) {
                $email->addTo($current);
            }
            foreach ($cc as $current) {
                $email->addCc($current);
            }
            foreach ($replyTo as $current) {
                $email->addReplyTo($current);
            }
            $this->getTransport()->send($email);
            if ($logFile = $this->options['message_log'] ?? null) {
                $format = $this->options['message_log_format'] ?? 'plain';
                $data = 'serialized' === $format
                    ? base64_encode(serialize($email)) . "\x1E" // Record Separator
                    : $email->toString() . "\n\n";
                file_put_contents($logFile, $data, FILE_APPEND);
            }
        } catch (\Exception $e) {
            $this->logError((string)$e);
            // Convert all exceptions thrown by mailer into MailException objects:
            throw new MailException($e->getMessage(), MailException::ERROR_UNKNOWN, $e);
        }
    }

    /**
     * Send an email message representing a link.
     *
     * @param string|string[]|Address|Address[]      $to      Recipient email address(es) (or delimited list)
     * @param string|Address                         $from    Sender name and email address
     * @param string                                 $msg     User notes to include in message
     * @param string                                 $url     URL to share
     * @param PhpRenderer                            $view    View object (used to render email templates)
     * @param ?string                                $subject Subject for email (optional)
     * @param string|string[]|Address|Address[]|null $cc      CC recipient(s) (null for none)
     * @param string|string[]|Address|Address[]|null $replyTo Reply-To address(es) (or delimited list, null for none)
     *
     * @throws MailException
     * @return void
     */
    public function sendLink(
        string|Address|array $to,
        string|Address $from,
        string $msg,
        string $url,
        PhpRenderer $view,
        ?string $subject = null,
        string|Address|array|null $cc = null,
        string|Address|array|null $replyTo = null
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
     * @param string|Address|Address[]      $to      Recipient email address(es) (or delimited list)
     * @param string|Address                $from    Sender name and email address
     * @param string                        $msg     User notes to include in message
     * @param AbstractBase                  $record  Record being emailed
     * @param PhpRenderer                   $view    View object (used to render email templates)
     * @param ?string                       $subject Subject for email (optional)
     * @param string|Address|Address[]|null $cc      CC recipient(s) (null for none)
     * @param string|Address|Address[]|null $replyTo Reply-To address(es) (or delimited list, null for none)
     *
     * @throws MailException
     * @return void
     */
    public function sendRecord(
        string|Address|array $to,
        string|Address $from,
        string $msg,
        AbstractBase $record,
        PhpRenderer $view,
        ?string $subject = null,
        string|Address|array|null $cc = null,
        string|Address|array|null $replyTo = null
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
        return $this->translate('Library Catalog Record') . ': ' . $record->getBreadcrumb();
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
     * Convert the given addresses to an array
     *
     * @param string|Address|Address[]|null $addresses Addresses
     *
     * @return array
     */
    protected function convertToAddressList(string|Address|array|null $addresses): array
    {
        if (empty($addresses)) {
            return [];
        }
        if ($addresses instanceof Address) {
            return [$addresses];
        }
        if (is_array($addresses)) {
            // Address::createArray takes an array of strings or Address objects, so this handles both cases:
            return Address::createArray($addresses);
        }
        $result = [];
        foreach (explode(';', $addresses) as $current) {
            $result[] = Address::create($current);
        }
        return $result;
    }
}
