<?php

namespace VuFind\Connection;

interface OauthServiceInterface
{
    public function authenticateWithClientCredentials(
        $oauthUrl,
        $clientId,
        $clientSecret,
        $forceNewConnection
    );
}