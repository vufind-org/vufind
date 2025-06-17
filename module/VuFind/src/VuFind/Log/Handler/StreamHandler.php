<?php

namespace VuFind\Log\Handler;

use Monolog\Handler\StreamHandler as MonologStreamHandler;
use Monolog\LogRecord;
use VuFind\Log\Handler\VerbosityTrait;

class StreamHandler extends MonologStreamHandler{

    use VerbosityTrait;

    protected function write(LogRecord $record): void
    {
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
}