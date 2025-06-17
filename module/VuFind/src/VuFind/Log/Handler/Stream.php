<?php

namespace VuFind\Log\Handler;

use Monolog\Handler\StreamHandler;
use Monolog\LogRecord;
use VuFind\Log\Handler\VerbosityTrait;

class Stream extends StreamHandler{

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