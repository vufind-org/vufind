<?php
namespace VuFind\Log\Handler;

use Laminas\Http\Client;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use function is_array;

class PostHandler extends AbstractProcessingHandler
{
    use VerbosityTrait;
    
    /**
     * @var string Content type
     */
    protected $contentType = 'application/x-www-form-urlencoded';
    
    public function __construct(protected string $url, protected Client $client)
    {
    }
    
    public function setContentType($type)
    {
        $this->contentType = $type;
    }
    
    protected function getBody($event)
    {
        return json_encode(
            ['message' => $event['message'] . PHP_EOL]
        );
    }
    
    protected function write(LogRecord $record): void
    {
        $event = [
            'timestamp' => $record->datetime,
            'priority' => $record->level->value,
            'priorityName' => $record->level->getName(),
            'message' => $record->formatted,
            'extra' => $record->extra,
            'context' => $record->context,
            'channel' => $record->channel,
        ];
        
        if (is_array($event['message'])) {
            $event['message'] = $event['message'][$this->verbosity];
        }
        
        $this->client->setUri($this->url);
        $this->client->setMethod('POST');
        $this->client->setEncType($this->contentType);
        $this->client->setRawBody($this->getBody($this->applyVerbosity($event)));
        // Send
        $this->client->send();
    }
}