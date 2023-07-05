<?php

namespace VuFind\Connection;

use Exception;

trait OauthServiceTrait
{
    protected $tokenData = null;

    // Lightly adapted from OverdriveConnector->connectToApi().
    // TODO: refactor?
    // @returns token
    public function authenticateWithClientCredentials(
        $oauthUrl,
        $clientId,
        $clientSecret,
        $forceNewConnection = false)
    {
        $this->debug("connecting to API");
        $tokenData = $this->tokenData;
        $this->debug("Last API Token: " . print_r($tokenData, true));
        if (
            $forceNewConnection || $tokenData == null
            || !isset($tokenData->access_token)
            || time() >= $tokenData->expirationTime
        ) {
            $authHeader = base64_encode(
                $clientId . ":" . $clientSecret
            );
            $headers = [
                'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
                "Authorization: Basic $authHeader",
            ];

            $this->client->setHeaders($headers);
            $this->client->setMethod("POST");
            $this->client->setRawBody("grant_type=client_credentials");
            $response = $this->client
                ->setUri($oauthUrl)
                ->send();

            if ($response->isServerError()) {
                $this->error(
                    "API HTTP Error: " .
                    $response->getStatusCode()
                );
                $this->debug("Request: " . $client->getRequest());
                return false;
            }

            $body = $response->getBody();
            $tokenData = json_decode($body);
            $this->debug(
                "TokenData returned from API Call: " . print_r(
                    $tokenData,
                    true
                )
            );
            if ($tokenData != null) {
                if (isset($tokenData->errorCode)) {
                    // In some cases, this should be returned perhaps...
                    $this->error("API Error: " . $tokenData->errorCode);
                    return false;
                } else {
                    $tokenData->expirationTime = time()
                        + ($tokenData->expires_in ?? 0);
                    $this->tokenData = $tokenData;
                    return $tokenData;
                }
            } else {
                $this->error(
                    "Overdrive Error: Nothing returned from API call."
                );
                $this->debug(
                    "Body return from API Call: " . print_r($body, true)
                );
            }
        }
        return $tokenData;
    }
}