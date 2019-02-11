<?

namespace TueFind;

class Utility {
    public static function normalizeGermanParallelDescriptions($german_term) {
       $translations = [ "Äquivalent" => "Equivalent",
                         "Digitale Übertragung von" => "Digital Reproduction of",
                         "Digitale Übertragung" => "Digital Reproduction of",
                         "Faksimile von" => "Facsimile of",
                         "Faksimile" => "Facsimile",
                         "Bestanderhaltungsfaksimile von" => "Preservation Facsimile of",
                         "Bestanderhaltungsfaksimile" => "Preservation Facsimile",
                         "Nachdruck von" => "Reprint of",
                         "Nachgedruckt als" => "Reprinted as",
                         "Reproduktion von" => "Reproduction of",
                         "Reproduziert als" => "Reproduced as",
                         "Elektronische Reproduktion von" => "Electronic Reproduction of",
                         "Elektronische Reproduktion" => "Electronic Reproduction"
                        ];
        if (array_key_exists($german_term, $translations))
            return $translations[$german_term];
        return $german_term;
    }


    public static function isSurroundedByQuotes($string) {
        return preg_match('/^(["\']).*\1/m', $string);
    }
}

?>
