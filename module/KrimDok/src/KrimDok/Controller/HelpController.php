<?php

namespace KrimDok\Controller;

class HelpController extends \VuFind\Controller\HelpController
{
    public function faqAction(){
        return $this->createViewModel(
            ['topics' =>
                [
                    'NachweiseKrimDok',
                    'FremdsprachigeSchlagworte2015',
                    'SignaturenInLiteraturnachweisen',
                    'BezeichnungInLiteraturnachweisen',
                    'WasIstSubito',
                    'WerkTuebingerBestaenden',
                    'KriminologischesWerkKeineBibliothek',
                    'KeineNotationen',
                    'WenigeNachweiseMonografien'
                ]
            ]
        );
    }
}