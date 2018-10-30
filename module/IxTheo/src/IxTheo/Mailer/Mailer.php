<?php
namespace IxTheo\Mailer;

class Mailer extends \VuFind\Mailer\Mailer {

    protected $sm;

    public function __construct(\Zend\Mail\Transport\TransportInterface $transport, \Zend\ServiceManager\ServiceManager $sm)
    {
        parent::__construct($transport);
        $this->sm = $sm;

    }

    /**
     * Send an email message, append custom footer to body
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
    public function send($to, $from, $subject, $body, $cc = null, $reply_to = null)
    {
        $config = $this->sm->get('VuFind\Config')->get('config');
        $email = $config->Site->email;
        $email_from = $config->Site->email_from;

        if ($email != null) {
            $footer = $this->translate('mail_footer_please_contact') . PHP_EOL . $email;
            $body .= PHP_EOL . '--' . PHP_EOL . $footer;
        }

        if ($email_from != null) {
            $from = $email_from;
        }

        $message = $this->assembleMail($to, $from, $subject, $body, $cc, $reply_to);
        try {
            $this->getTransport()->send($message);
        } catch (\Exception $e) {
            throw new MailException($e->getMessage());
        }
    }

    /**
     * Translate a string for use in Mail client
     * @see VuFind\Controller\AbstractBase->translate
     */
    public function translate($msg, $tokens = [], $default = null) {
        return $this->sm->get('ViewRenderer')->plugin('translate')->__invoke($msg, $tokens, $default);
    }

    /*
     * Assemble the complete message but do not yet send it
     * @return The Mail message
     */
    function assembleMail($to, $from, $subject, $body, $cc = null, $reply_to = null) {
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
            // Assemble message
            $message = $this->getNewMessage()
                ->addFrom($from)
                ->addTo($recipients)
                ->setBody($body)
                ->setSubject($subject)
                ->setReplyTo($reply_to);

            if ($cc !== null) {
                $message->addCc($cc);
            }
        } catch (\Exception $e) {
            throw new MailException($e->getMessage());
        }
        return $message;
    }
}
