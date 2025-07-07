<?php

namespace VuFind\Log\Handler;

use Monolog\Handler\SlackWebhookHandler as MonologSlackWebhookHandler;
use Monolog\LogRecord;
use Monolog\Level;
use VuFind\Log\Handler\VerbosityTrait;

class SlackWebhookHandler extends MonologSlackWebhookHandler
{
    use VerbosityTrait;

    /**
     * Icons that appear at the start of log messages in Slack, by severity
     *
     * @var array
     */
    protected $messageIcons = [
        ':fire: :fire: :fire: ', // EMERGENCY
        ':rotating_light: ',     // ALERT
        ':red_circle: ',         // CRITICAL
        ':exclamation: ',        // ERROR
        ':warning: ',            // WARNING
        ':speech_balloon: ',     // NOTICE
        ':information_source: ', // INFO
        ':beetle: ',             // DEBUG
    ];
    
    public function __construct(
        string $webhookUrl,
        protected ?string $channel = "#log",
        protected ?string $username = "VuFind Log",
        bool $useAttachment = true,
        ?string $iconEmoji = null,
        bool $useShortAttachment = false,
        bool $includeContextAndExtra = false
    ) {
        
        parent::__construct($webhookUrl, $this->channel, $this->username, $useAttachment, $iconEmoji, $useShortAttachment, $includeContextAndExtra);
    }
    
    protected function write(LogRecord $record): void
    {
        $event = [
            'timestamp' => $record->datetime,
            'priority' => $record->level->value,
            'priorityName' => $record->level->getName(),
            'message' => $record->message,
            'extra' => $record->extra,
            'context' => $record->context,
            'channel' => $record->channel,
        ];

        // Apply verbosity filter
        $filteredEvent = $this->applyVerbosity($event);
        $modifiedRecord = new LogRecord(
            $record->datetime,
            $record->channel,
            $record->level,
            $this->formatMessage($filteredEvent),
            $filteredEvent['context'],
            $filteredEvent['extra'],
            $record->formatted
        );
        
        parent::write($modifiedRecord);
    }
    
    protected function formatMessage(array $event): string
    {
        $icon = $this->messageIcons[$event['priority']] ?? '';
        return $icon . $event['message'];
    }
    
    protected function getSlackData(LogRecord $record): array
    {
        $data = parent::getSlackData($record);
        
        $data['channel'] = $this->channel;
        $data['username'] = $this->username;
        
        return $data;
    }
}