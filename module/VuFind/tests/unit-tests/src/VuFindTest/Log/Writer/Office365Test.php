<?php
namespace VuFindTest\Log\Handler;

use Laminas\Http\Client;
use Monolog\Level;
use Monolog\LogRecord;
use VuFind\Log\Handler\Office365Handler;

class Office365Test extends \PHPUnit\Framework\TestCase
{
    public function testHandler(): void
    {
        $fakeUri = 'http://fake';
        $expectedBody = '{"@context":"https:\/\/schema.org\/extensions",' .
            '"@type":"MessageCard","themeColor":"0072C6",' .
            '"title":"Test Title","text":"[2025-07-09T14:55:20+00:00] test.INFO: test [] []\n"}';
        
        $options = ['title' => 'Test Title'];
        $logRecord = new LogRecord(
            datetime: new \DateTimeImmutable('2025-07-09T14:55:20+00:00'),
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
            ->with($this->equalTo('application/json'));
        $client->expects($this->once())->method('setRawBody')
            ->with($this->equalTo($expectedBody));
        $client->expects($this->once())->method('send');
        
        $handler = new Office365Handler($fakeUri, $client, $options);
        $handler->handle($logRecord);
    }
}