<?php
namespace VuFind\Log\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Monolog\Logger;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Table\LogEntry;

class DatabaseHandler extends AbstractProcessingHandler implements DbServiceAwareInterface
{
    use VerbosityTrait;
    use DbServiceAwareTrait;

    /**
     * Column mapping for log data
     */
    protected array $columnMapping = [
        'priority' => 'priority',
        'message' => 'message', 
        'logtime' => 'timestamp',
        'ident' => 'ident',
    ];

    /**
     * Constructor
     */
    public function __construct(protected string $tableName) {}

    /**
     * Set column mapping
     */
    public function setColumnMapping(array $mapping): void
    {
        $this->columnMapping = array_merge($this->columnMapping, $mapping);
    }

    /**
     * Write log record to database
     */
    protected function write(LogRecord $record): void
    {
        $recordData = $record->toArray();
        $modifiedRecordData = $this->applyVerbosity($recordData);
        
        // Prepare data for database insertion
        $logData = $this->formatLogData($modifiedRecordData);
        
        try {
            // Get the database table service
            $table = $this->getDbService($this->tableName);
            
            // Insert log entry
            $table->insert($logData);
        } catch (\Exception $e) {
            // Fallback to error_log if database write fails
            error_log("DatabaseHandler failed to write log: " . $e->getMessage());
            error_log("Original log message: " . $record->message);
        }
    }

    /**
     * Format log data for database insertion
     */
    protected function formatLogData(array $recordData): array
    {
        $data = [];
        
        if (isset($this->columnMapping['priority'])) {
            $data[$this->columnMapping['priority']] = $this->mapLogLevel($recordData['level']);
        }
        
        if (isset($this->columnMapping['message'])) {
            $message = $recordData['message'];
            if (!empty($recordData['context'])) {
                $message .= ' ' . json_encode($recordData['context']);
            }
            if (!empty($recordData['extra'])) {
                $message .= ' ' . json_encode($recordData['extra']);
            }
            $data[$this->columnMapping['message']] = $message;
        }
        
        if (isset($this->columnMapping['logtime'])) {
            $data[$this->columnMapping['logtime']] = $recordData['datetime']->format('Y-m-d H:i:s');
        }
        
        if (isset($this->columnMapping['ident'])) {
            $data[$this->columnMapping['ident']] = $recordData['channel'] ?? 'vufind';
        }
        
        return $data;
    }
    
    protected function mapLogLevel(int $level): int
    {
        $levelMap = [
            Logger::DEBUG => 7,      // Debug
            Logger::INFO => 6,       // Info
            Logger::NOTICE => 5,     // Notice
            Logger::WARNING => 4,    // Warning
            Logger::ERROR => 3,      // Error
            Logger::CRITICAL => 2,   // Critical
            Logger::ALERT => 1,      // Alert
            Logger::EMERGENCY => 0,  // Emergency
        ];
        
        return $levelMap[$level] ?? 7;
    }
}