<?php

namespace VuFindTest\Log\Writer;

use Monolog\Level;
use Monolog\LogRecord;
use VuFind\Log\Handler\SlackWebhookHandler;

class SlackTest extends \PHPUnit\Framework\TestCase
{
    public function testHandler(): void
    {
        $fakeUri = 'http://fake/webhook';

        $logRecord = new LogRecord(
            datetime: new \DateTimeImmutable('2025-07-09T14:57:30+00:00'),
            channel: 'test',
            level: Level::Alert,
            message: 'test',
            context: [],
            extra: []
        );
        $handler = $this->getMockBuilder(SlackWebhookHandler::class)
            ->setConstructorArgs([
                $fakeUri,
                '#test',
                'TestName',
            ])
            ->onlyMethods(['write'])
            ->getMock();

        $handler->expects($this->once())
            ->method('write')
            ->with($this->callback(function (LogRecord $record) {
                return $record->message === 'test';
            }));

        $handler->handle($logRecord);
    }
}
