<?php
/**
 * Dynamic Book Cover Generator
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2014.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Cover_Generator
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/use_of_external_content Wiki
 */
namespace VuFind\Cover;
use VuFindCode\ISBN, Zend\Log\LoggerInterface, ZendService\Amazon\Amazon;

/**
 * Dynamic Book Cover Generator
 *
 * @category VuFind2
 * @package  Cover_Generator
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/use_of_external_content Wiki
 */
class Generator
{
    /**
     * Style configuration
     *
     * @var array
     */
    protected $settings = [];
    /**
     * Reserved color
     */
    protected $black;
    /**
     * Reserved color
     */
    protected $white;

    /**
     * Constructor
     *
     * @param \VuFindTheme\ThemeInfo $themeTools For font loading
     * @param array                  $settings   Overwrite styles
     */
    public function __construct($themeTools, $settings = [])
    {
        $this->themeTools = $themeTools;
        $default = [
            'mode'         => 'grid',
            'authorFont'   => 'DroidSerif-Bold.ttf',
            'fontSize'     => 7,
            'lightness'    => 220,
            'maxLines'     => 5,
            'minFontSize'  => 5,
            'saturation'   => 80,
            'size'         => 84,
            'textAlign'    => 'center',
            'titleFont'    => 'DroidSerif-Bold.ttf',
            'topPadding'   => 19,
            'wrapWidth'    => 80,
        ];
        foreach ($settings as $i => $setting) {
            $default[$i] = $setting;
        }
        $default['authorFont'] = $this->fontPath($default['authorFont']);
        $default['titleFont']  = $this->fontPath($default['titleFont']);
        $this->settings = (object) $default;
    }

    /**
     * Generates a dynamic cover image from elements of the book
     *
     * @param string $title      Title of the book
     * @param string $author     Author of the book
     * @param string $callnumber Callnumber of the book
     *
     * @return string contents of image file
     */
    public function generate($title, $author, $callnumber = null)
    {
        if ($this->settings->mode == 'solid') {
            return $this->generateSolid($title, $author, $callnumber);
        } else {
            return $this->generateGrid($title, $author, $callnumber);
        }
    }

    /**
     * Generates a solid color background, ala Google
     *
     * @param string $title      Title of the book
     * @param string $author     Author of the book
     * @param string $callnumber Callnumber of the book
     *
     * @return string contents of image file
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function generateSolid($title, $author, $callnumber)
    {
        $half = $this->settings->size/2;
        // Create image
        if (!($im = imagecreate($this->settings->size, $this->settings->size))) {
            throw new \Exception("Cannot Initialize new GD image stream");
        }
        // this->white backdrop
        $this->white = imagecolorallocate($im, 255, 255, 255);
        // this->black
        $this->black = imagecolorallocate($im, 0, 0, 0);

        // Generate seed from callnumber, title back up
        $seed = $this->createSeed($title, $callnumber);
        // Number to color, hsb to control saturation and lightness
        $color = $this->makeHSBColor(
            $im,
            $seed%256,
            $this->settings->saturation,
            $this->settings->lightness
        );

        // Fill solid color
        imagefilledrectangle(
            $im,
            0,
            0,
            $this->settings->size,
            $this->settings->size,
            $color
        );

        $this->drawText(
            $im,
            strtoupper($title[0]),
            $half,
            $half+28,
            $this->settings->titleFont,
            60,
            $this->white,
            false,
            'center'
        );

        // Output png CHECK THE PARAM
        ob_start();
        imagepng($im);
        $img = ob_get_contents();
        ob_end_clean();

        // Clear memory
        imagedestroy($im);
        // GTFO
        return $img;
    }

    /**
     * Generates a grid of colors as primary feature
     *
     * @param string $title      Title of the book
     * @param string $author     Author of the book
     * @param string $callnumber Callnumber of the book
     *
     * @return string contents of image file
     */
    protected function generateGrid($title, $author, $callnumber)
    {
        // Set up common variables
        $half = $this->settings->size/2;
        $box  = $this->settings->size/8;

        // Create image
        if (!($im = imagecreate($this->settings->size, $this->settings->size))) {
            throw new \Exception("Cannot Initialize new GD image stream");
        }
        // this->white backdrop
        $this->white = imagecolorallocate($im, 255, 255, 255);
        // this->black
        $this->black = imagecolorallocate($im, 0, 0, 0);

        // Generate seed from callnumber, title back up
        $seed = $this->createSeed($title, $callnumber);
        // Number to color, hsb to control saturation and lightness
        $grid_color = $this->makeHSBColor(
            $im,
            $seed%256,
            $this->settings->saturation,
            $this->settings->lightness
        );
        // Render the grid
        $pattern = $this->createPattern($seed);
        $this->render($pattern, $im, $grid_color, $half, $box);

        if (null !== $title) {
            $this->drawTitle($im, $title, $box);
        }
        if (null !== $author) {
            $this->drawAuthor($im, $author);
        }
        // Output png CHECK THE PARAM
        ob_start();
        imagepng($im);
        $img = ob_get_contents();
        ob_end_clean();

        // Clear memory
        imagedestroy($im);
        // GTFO
        return $img;
    }

    /**
     * Generates a dynamic cover image from elements of the book
     *
     * @param string $title      Title of the book
     * @param string $callnumber Callnumber of the book
     *
     * @return integer unique number for this record
     */
    protected function createSeed($title, $callnumber)
    {
        // Turn callnumber into number
        if (null == $callnumber) {
            $callnumber = $title;
        }
        if (null !== $callnumber) {
            $cv = 0;
            for ($i = 0;$i<strlen($callnumber);$i++) {
                $cv += ord($callnumber[$i]);
            }
            return $cv;
        } else {
            // If no callnumber, random
            return ceil(rand(pow(2, 4), pow(2, 32)));
        }
    }

    /**
     * Turn number into pattern
     *
     * @param integer $seed Seed used to generate the pattern
     *
     * @return string binary string describing a quarter of the pattern
     */
    protected function createPattern($seed)
    {
        // Convert to binary
        $bc = decbin($seed);
        // If we have less that a half of a quarter
        if (strlen($bc) < 8) {
            // Rotate square of the first 4 into a 4x2
            // Simulate matrix rotation on string
            $bc = substr($bc, 0, 3)
                . substr($bc, 0, 1)
                . substr($bc, 2, 2)
                . substr($bc, 3, 1)
                . substr($bc, 1, 1);
        }
        // If we have less than a quarter
        if (strlen($bc) < 16) {
            // Rotate the first 8 as a 4x2 into a 4x4
            $bc .= strrev($bc);
        }
        return $bc;
    }

    /**
     * Render title in wrapped, black text with white border
     *
     * @param GCImage $im         Image object to render to
     * @param string  $title      Title to write
     * @param integer $lineHeight Pixels we move down each line
     *
     * @return void
     */
    protected function drawTitle($im, $title, $lineHeight)
    {
        $words = explode(' ', $title);
        // Wrap words into image
        // Add words until off image, go back and print
        $line = '';
        $lineCount = 0;
        $i = 0;
        while ($i<count($words) && $lineCount<$this->settings->maxLines-1) {
            $pline = $line;
            // Format
            $text = strtoupper($words[$i]);
            $line .= $text . ' ';
            $textWidth = $this->textWidth(
                $line,
                $this->settings->titleFont,
                $this->settings->fontSize
            );
            if ($textWidth > $this->settings->wrapWidth) {
                // Print black with white border
                $this->drawText(
                    $im,
                    $pline,
                    3,
                    $this->settings->topPadding+$lineHeight*$lineCount,
                    $this->settings->titleFont,
                    $this->settings->fontSize,
                    $this->black,
                    $this->white
                );
                $line = $text . " ";
                $lineCount++;
            }
            $i++;
        }
        // Print the last words
        $this->drawText(
            $im,
            $line,
            3,
            $this->settings->topPadding+$lineHeight*$lineCount,
            $this->settings->titleFont,
            $this->settings->fontSize,
            $this->black,
            $this->white
        );
        // Add ellipses if we've truncated
        if ($i < count($words)-1) {
            $this->drawText(
                $im,
                '...',
                5,
                $this->settings->topPadding+$this->settings->maxLines*$lineHeight,
                $this->settings->titleFont,
                $this->settings->fontSize+1,
                $this->black,
                $this->white
            );
        }
    }

    /**
     * Render author at bottom in wrapped, white text with black border
     *
     * @param GCImage $im     Image object to render to
     * @param string  $author Author to write
     *
     * @return void
     */
    protected function drawAuthor($im, $author)
    {
        // Scale author to fit by incrementing fontsizes down
        $fontSize = $this->settings->fontSize;
        do {
            $txtWidth = $this->textWidth(
                $author,
                $this->settings->titleFont,
                $fontSize
            );
            $fontSize--;
        } while ($txtWidth > $this->settings->wrapWidth);
        // white text, black outline
        $fontSize = ++$fontSize < $this->settings->minFontSize
            ? $this->settings->fontSize
            : $fontSize;
        // Too small to read? Align left
        $alignment = $fontSize < $this->settings->minFontSize
            ? 'left'
            : null;
        $this->drawText(
            $im,
            $author,
            5,
            $this->settings->size-3,
            $this->settings->authorFont,
            $fontSize,
            $this->white,
            $this->black,
            $alignment
        );
    }

    /**
     * Find font in the theme folder
     *
     * @param string $font Font_name.ttf
     *
     * @return string file path
     */
    protected function fontPath($font)
    {
        // Check all supported image formats:
        $filenames = ['css/font/' . $font];
        $fileMatch = $this->themeTools->findContainingTheme($filenames, true);
        return empty($fileMatch) ? false : $fileMatch;
    }

    /**
     * Returns the width a string would render to
     *
     * @param string $text Text to test
     * @param string $font Full font path
     * @param string $size Size of the font
     *
     * @return string file path
     */
    protected function textWidth($text, $font, $size)
    {
        $p = imagettfbbox($size, 0, $font, $text);
        return $p[2]-$p[0]-4;
    }

    /**
     * Simulate outlined text
     *
     * @param GCImage $im       Image object
     * @param string  $text     Text to render
     * @param integer $x        Left position
     * @param integer $y        Top position
     * @param string  $font     Full path to font
     * @param integer $fontSize Size of the font
     * @param GCColor $mcolor   Main text color
     * @param GCColor $scolor   Secondary border color
     * @param string  $align    'left','center','right'
     *
     * @return void
     */
    protected function drawText($im, $text, $x, $y,
        $font, $fontSize, $mcolor, $scolor = false, $align = null
    ) {
        $txtWidth = $this->textWidth(
            $text,
            $this->settings->titleFont,
            $this->settings->fontSize
        );
        if ($txtWidth > $this->settings->size) {
            $align = 'left';
            $x = 0;
        }
        if (null == $align) {
            $align = $this->settings->textAlign;
        }
        if ($align == 'center') {
            $p = imagettfbbox($fontSize, 0, $this->settings->titleFont, $text);
            $txtWidth = $p[2]-$p[0]-4;
            $x = ($this->settings->size-$txtWidth)/2;
        }
        if ($align == 'right') {
            $p = imagettfbbox($fontSize, 0, $this->settings->titleFont, $text);
            $txtWidth = $p[2]-$p[0]-4;
            $x = $this->settings->size-$txtWidth-$x;
        }

        // Generate 5 lines of text, 4 offset in a border color
        if ($scolor) {
            imagettftext($im, $fontSize, 0, $x,   $y+1, $scolor, $font, $text);
            imagettftext($im, $fontSize, 0, $x,   $y-1, $scolor, $font, $text);
            imagettftext($im, $fontSize, 0, $x+1, $y,   $scolor, $font, $text);
            imagettftext($im, $fontSize, 0, $x-1, $y,   $scolor, $font, $text);
        }
        // 1 centered in main color
        imagettftext($im, $fontSize, 0, $x,   $y,   $mcolor, $font, $text);
    }

    /**
     * Convert 16 long binary string to 8x8 color grid
     * Reflects vertically and horizontally
     *
     * @param string  $bc    Binary string of pattern
     * @param GCImage $im    Image object
     * @param GCColor $color Fill color
     * @param integer $half  Half the size, shortcut for math
     * @param integer $box   Box size
     *
     * @return void
     */
    protected function render($bc, $im, $color, $half, $box)
    {
        $bc = str_split($bc);
        for ($k = 0;$k<4;$k++) {
            $x = $k%2   ? $half : $half-$box;
            $y = $k/2<1 ? $half : $half-$box;
            $u = $k%2   ? $box : -$box;
            $v = $k/2<1 ? $box : -$box;
            for ($i = 0;$i<16;$i++) {
                if ($bc[$i] == "1") {
                    imagefilledrectangle($im, $x, $y, $x+$box-1, $y+$box-1, $color);
                }
                $x += $u;
                if ($x >= $this->settings->size || $x < 0) {
                    $x = $k%2 ? $half : $half-$box;
                    $y += $v;
                }
            }
        }
        //imagefilledrectangle($im,0,$size-11,$size-1,$size,$color);
    }

    /**
     * Using HSB allows us to control the contrast while allowing randomness
     *
     * @param GCImage $im Image object
     * @param integer $h  Hue (0-255)
     * @param integer $s  Saturation (0-100)
     * @param integer $v  Lightness (0-100)
     *
     * @return GCColor
     */
    protected function makeHSBColor($im, $h, $s, $v)
    {
        $s /= 256.0;
        if ($s == 0.0) {
            return imagecolorallocate($im, $v, $v, $v);
        }
        $h /= (256.0 / 6.0);
        $i = floor($h);
        $f = $h - $i;
        $p = (integer)($v * (1.0 - $s));
        $q = (integer)($v * (1.0 - $s * $f));
        $t = (integer)($v * (1.0 - $s * (1.0 - $f)));
        switch($i) {
        case 0:
            return imagecolorallocate($im, $v, $t, $p);
        case 1:
            return imagecolorallocate($im, $q, $v, $p);
        case 2:
            return imagecolorallocate($im, $p, $v, $t);
        case 3:
            return imagecolorallocate($im, $p, $q, $v);
        case 4:
            return imagecolorallocate($im, $t, $p, $v);
        default:
            return imagecolorallocate($im, $v, $p, $q);
        }
    }
}