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
    public function send($to, $from, $subject, $body, $cc = null)
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

        parent::send($to, $from, $subject, $body, $cc);
    }

    /**
     * Translate a string for use in Mail client
     * @see VuFind\Controller\AbstractBase->translate
     */
    public function translate($msg, $tokens = [], $default = null) {
        return $this->sm->get('ViewRenderer')->plugin('translate')->__invoke($msg, $tokens, $default);
    }
}
