<?php

/**
 * Elasticsearch API
 *
 * using elasticsearch composer package
 * for more documentation, see:
 * - https://www.elastic.co/guide/en/elasticsearch/client/php-api/6.0/_quickstart.html
 * - https://www.elastic.co/guide/en/elasticsearch/client/javascript-api/14.x/api-reference-6-0.html
 * - https://www.elastic.co/guide/en/elasticsearch/reference/current/docs.html
 *
 * Functions throw exceptions on error
 */

namespace TueFind\Elasticsearch;

class Api {

    /**
     * ...still to be decided if these values will be passed from outside (non-static?)
     * ...hosts might be fetched from config file later
     */
    const INDEX = 'TueFind';
    const TYPE = 'TueFind';
    const HOSTS = [];

    /**
     * Initialize Elasticsearch client
     *
     * @return \Elasticsearch\Client
     */
    static private function _getClient() {
        $client = \Elasticsearch\ClientBuilder::create()->setHosts(self::HOSTS)->build();
        return $client;
    }

    /**
     * Add document
     * (...to be decided if "index" is used rather than "create" for unique documents)
     *
     * @param string $id
     * @param array $fields
     */
    static public function addDocument($id, $fields=[]) {
        $params = [
            'index' => self::INDEX,
            'type' => self::TYPE,
            'id' => $id,
            'body' => $fields,
        ];

        $client = self::_getClient();
        $client->index($params);
    }

    /**
     * Create index
     * @param array $settings
     */
    static public function createIndex($settings=[]) {
        $params = [
            'index' => self::INDEX,
            'body' => [
                'settings' => $settings,
            ]
        ];

        $client = self::_getClient();
        $client->indices()->create($params);
    }

    /**
     * Delete document
     * @param string $id
     */
    static public function deleteDocument($id) {
        $params = [
            'index' => self::INDEX,
            'type' => self::TYPE,
            'id' => $id,
        ];

        $client = self::_getClient();
        $client->delete($params);
    }

    /**
     * Delete index
     */
    static public function deleteIndex() {
        $params = [
            'index' => self::INDEX,
        ];

        $client = self::_getClient();
        $client->indices()->delete($params);
    }

    /**
     * Get document
     *
     * @param string $id
     * @return array
     */
    static public function getDocument($id) {
        $params = [
            'index' => self::INDEX,
            'type' => self::TYPE,
            'id' => $id,
        ];

        $client = self::_getClient();
        $response = $client->get($params);
        return $response;
    }

    /**
     * Search for a document by given field(s)
     *
     * @param array $fields
     * @return array
     */
    static public function searchDocument($fields=[]) {
        $params = [
            'index' => self::INDEX,
            'type' => self::TYPE,
            'body' => [
                'query' => [
                    'match' => $fields,
                ]
            ]
        ];

        $client = self::_getClient();
        $response = $client->search($params);
        return $response;
    }
}
