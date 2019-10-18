<?php

namespace TueFind\Meta;

class PRISM extends AbstractBase {
    // https://www.idealliance.org/prism-metadata
    protected $map = ['doi' => 'prism.doi',
                      'isbn' => 'prism.isbn',
                      'issn' => 'prism.issn', // print/online?
                      'title' => 'prism.title'];
}
