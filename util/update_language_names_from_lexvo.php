<?php
include_once __DIR__.'/../vendor/autoload.php';
use pietercolpaert\hardf\TriGParser;
use pietercolpaert\hardf\Util;

$map = [];
$parser = new TriGParser(['format' => 'n-triples'], function ($error, $triple) use (& $map) {
    if (!($error ?? false) && isset($triple)) {
        if (str_starts_with($triple['subject'], 'http://lexvo.org/id/iso639-3/')
            && $triple['predicate'] === 'http://www.w3.org/2000/01/rdf-schema#label'
        ) {
            $parts = explode('/', $triple['subject']);
            $key = array_pop($parts);
            $lang = Util::getLiteralLanguage($triple['object']);
            $val = Util::getLiteralValue($triple['object']);
            if (!isset($map[$lang][$key])
                || str_contains($map[$lang][$key], ' language')
            ) {
                $map[$lang][$key] = $val;
            }
        }
    } elseif ($error) {
        fwrite(STDERR, $error->getMessage()."\n");
    }
});
$tmpDir = "/tmp";
$source = "http://lexvo.org/resources/lexvo_2013-02-09.nt.gz";
$targetFile = "$tmpDir/lekvo.nt";
$filteredOutput = "$tmpDir/lekvo-filtered.nt";
if (!file_exists($targetFile)) {
    passthru("wget $source -O $targetFile.gz");
    passthru("gzip -d $targetFile.gz");
}
if (!file_exists($filteredOutput)) {
    passthru("cat $targetFile | grep \"http://lexvo.org/id/iso639-3/\" | grep \"http://www.w3.org/2000/01/rdf-schema#label\" > $filteredOutput");
}
passthru("mkdir -p $tmpDir/lang");
$handle = fopen($filteredOutput, 'r');
while ($line = fgets($handle)) {
    $parser->parseChunk($line);
}
fclose($handle);
$parser->end();
foreach ($map as $lang => $vals) {
    $handle = fopen($tmpDir . '/lang/' . $lang . '.ini', 'w');
    foreach ($vals as $key => $val) {
        fputs($handle, "$key = $val\n");
    }
    fclose($handle);
}

$langDir = __DIR__ . "/../languages/ISO639-3";
$dir = opendir($langDir);
while ($file = readdir($dir)) {
    if (str_ends_with($file, '.ini') && file_exists("$tmpDir/lang/$file")) {
        passthru("cat $langDir/$file >> $tmpDir/lang/$file");
        passthru("mv $tmpDir/lang/$file $langDir/$file");
    }
}
closedir($dir);

$VUFIND_HOME = __DIR__ . '/..';
passthru("php $VUFIND_HOME/public/index.php language normalize languages");