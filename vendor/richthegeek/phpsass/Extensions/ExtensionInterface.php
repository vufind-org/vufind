<?php
interface ExtensionInterface
{
    public static function getFunctions($namespace);

    public static function resolveExtensionPath($filename, $parser, $syntax = 'scss');
}
