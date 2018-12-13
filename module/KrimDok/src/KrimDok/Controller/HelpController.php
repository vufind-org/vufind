<?php

namespace KrimDok\Controller;

// Note: This controller is no longer used in krimdok2 theme and can be removed
//       as soon as old krimdok(1) theme is removed as well.
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
