<?php
return [
    'extends' => 'parent',
    'css' => ['child.css'],
    'js' => ['extra.js'],
    'helpers' => [
        'factories' => [
            'foo' => 'fooOverrideFactory',
        ],
        'invokables' => [
            'xyzzy' => 'Xyzzy',
        ]
    ],
];
