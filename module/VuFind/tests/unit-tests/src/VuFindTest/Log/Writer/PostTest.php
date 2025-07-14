<?php

namespace VuFindTest\Log\Writer;

use Laminas\Http\Client;
use Monolog\Level;
use Monolog\LogRecord;
use VuFind\Log\Handler\PostHandler;

class PostTest extends \PHPUnit\Framework\TestCase
{
    public function testHandler(): void
    {
        $fakeUri = 'http://fake';
        $expectedBody = json_encode(['message' => '[2025-07-09T14:57:30+00:00] test.INFO: test [] []' . PHP_EOL . PHP_EOL]);

        $logRecord = new LogRecord(
            datetime: new \DateTimeImmutable('2025-07-09T14:57:30+00:00'),
            channel: 'test',
            level: Level::Info,
            message: 'test',
            context: [],
            extra: []
        );

        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('setUri')
            ->with($this->equalTo($fakeUri));
        $client->expects($this->once())->method('setMethod')
            ->with($this->equalTo('POST'));
        $client->expects($this->once())->method('setEncType')
            ->with($this->equalTo('application/x-www-form-urlencoded'));
        $client->expects($this->once())->method('setRawBody')
            ->with($this->equalTo($expectedBody));
        $client->expects($this->once())->method('send');

        $handler = new PostHandler($fakeUri, $client);
        $handler->handle($logRecord);
    }
}
