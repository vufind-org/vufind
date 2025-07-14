<?php

namespace VuFind\Log\Handler;

use Laminas\Http\Client;

class Office365Handler extends PostHandler
{
    protected $title;

    public function __construct(protected string $url, protected Client $client, array $options = [])
    {
        $this->title = $options['title'] ?? 'VuFind Log';
        parent::__construct($url, $client);
        $this->setContentType('application/json');
    }

    protected function getBody($event)
    {
        $data = [
            '@context' => 'https://schema.org/extensions',
            '@type' => 'MessageCard',
            'themeColor' => '0072C6',
            'title' => $this->title,
            'text' => $event['message'],
        ];

        return json_encode($data);
    }
}
