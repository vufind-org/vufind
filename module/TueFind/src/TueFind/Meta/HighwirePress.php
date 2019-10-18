<?php

namespace TueFind\Meta;

class HighwirePress extends AbstractBase {

    // https://jira.duraspace.org/secure/attachment/13020/Invisible_institutional.pdf
    protected $map = ['doi' => 'citation_doi',
                      'isbn' => 'citation_isbn',
                      'issn' => 'citation_issn',
                      'title' => 'citation_title'];
}
