<?php

namespace VuFind\Log\Handler;

use Monolog\Handler\MailHandler as MonologMailHandler;
use Monolog\LogRecord;
use Monolog\Logger;
use VuFind\Log\Handler\VerbosityTrait;
use VuFind\Mailer\Mailer;

/**
 * Custom Mail Handler for VuFind with verbosity support and VuFind mailer integration
 */
class MailHandler extends MonologMailHandler
{
    use VerbosityTrait;

    /**
     * Constructor
     *
     * @param string $to      Recipient email address
     * @param string $subject Email subject
     * @param string $from    Sender email address
     * @param Mailer $mailer  VuFind mailer instance
     */
    public function __construct(protected string $to, protected string $subject, protected string $from, protected Mailer $mailer){}

    /**
     * Send the mail using VuFind's mailer
     *
     * @param string $content The email content
     * @param array  $records The log records that triggered this handler
     * @return void
     */
    protected function send(string $content, array $records): void
    {
        $this->mailer->send($this->to, $this->from, $this->subject, $this->buildMessage($records));
    }
    
    protected function write(LogRecord $record): void
    {
        // Apply verbosity to the record before processing
        $recordData = $record->toArray();
        $modifiedRecordData = $this->applyVerbosity($recordData);
        
        $modifiedRecord = new LogRecord(
            $record->datetime,
            $record->channel,
            $record->level,
            $modifiedRecordData['message'],
            $modifiedRecordData['context'],
            $modifiedRecordData['extra'],
            $record->formatted
        );

        parent::write($modifiedRecord);
    }

    /**
     * Gets the formatted content for this handler
     *
     * @param array $records Array of LogRecord objects
     * @return string The formatted content
     */
    protected function buildMessage(array $records): string
    {
        $message = "VuFind Log Alert\n";
        
        foreach ($records as $record) {
            $recordData = $record->toArray();
            $modifiedRecordData = $this->applyVerbosity($recordData);
            
            $message .= sprintf(
                "[%s] %s.%s: %s\n",
                $recordData['datetime']->format('Y-m-d H:i:s'),
                $recordData['channel'],
                $recordData['level_name'],
                $modifiedRecordData['message']
            );
            
            if (!empty($modifiedRecordData['context'])) {
                $message .= "Context: " . json_encode($modifiedRecordData['context'], JSON_PRETTY_PRINT) . "\n";
            }
            
            if (!empty($modifiedRecordData['extra'])) {
                $message .= "Extra: " . json_encode($modifiedRecordData['extra'], JSON_PRETTY_PRINT) . "\n";
            }
            
            $message .= "\n" . str_repeat('-', 50) . "\n\n";
        }
        
        return $message;
    }
}