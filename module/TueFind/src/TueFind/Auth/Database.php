<?php

namespace TueFind\Auth;

class Database extends \VuFind\Auth\Database
{
    protected function collectParamsFromRequest($request)
    {
        $params = parent::collectParamsFromRequest($request);
        $params['newsletter'] = boolval($request->getPost()->get('newsletter', false));
        return $params;
    }

    protected function createUserFromParams($params, $table)
    {
        $user = parent::createUserFromParams($params, $table);
        $user->setSubscribedToNewsletter($params['newsletter']);
        return $user;
    }
}
