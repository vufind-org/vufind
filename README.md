Prototype VF2 proxy service
===========================

Installation
------------

Usage
-----

The ProxyService registers itself in the top-level service manager by
the name `Service\Proxy'.

Configuration
-------------

Service API
-----------

get(string URI[, array PARAMS[, array HEADERS]])

post(string URI[, mixed BODY[, array HEADERS]])

postForm(string URI[, array PARAMS[, array HEADERS]])

proxify(Zend\Http\Client $client[, array $options])

