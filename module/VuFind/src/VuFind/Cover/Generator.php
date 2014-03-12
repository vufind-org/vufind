<?php
/**
 * Dynamic Book Cover Generator
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/use_of_external_content Wiki
 */
namespace VuFind\Cover;
use VuFind\Code\ISBN, Zend\Log\LoggerInterface, ZendService\Amazon\Amazon;

/**
 * Dynamic Book Cover Generator
 *
 * @category VuFind2
 * @package  Cover_Generator
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
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
    protected $settings = array();

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config     VuFind configuration
     * @param \Zend\Config\Config $themeTools For font loading
     * @param array               $settings   Overwrite styles
     */
    public function __construct($config, $themeTools, $settings = array())
    {
        $this->themeTools = $themeTools;
        $default = array(
            'authorFont'   => 'DroidSerif-Bold.ttf',
            'fontSize'     => 7,
            'lightness'    => 200,
            'maxLines'     => 5,
            'saturation'   => 100,
            'size'         => 84,
            'textAlign'    => 'center',
            'titleFont'    => 'DroidSerif-Bold.ttf',
            'topPadding'   => 19,
            'wrapWidth'    => 80,
        );
        foreach($settings as $i=>$setting) {
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
        // Set up common variables
        $half = $this->settings->size/2;
        $box = $this->settings->size/8;
        
        // Turn callnumber into number
        if (null == $callnumber) {
            $callnumber = $title;
        }
        if (null !== $callnumber) {
            $cv = 0;
            for($i=0;$i<strlen($callnumber);$i++) {
              $cv += ord($callnumber[$i]);
            }
        } else {
            // If no callnumber, random
            $cv = ceil(rand(pow(2,4), pow(2,32)));
        }
        
        // Convert to binary
        $bc = decbin((int)$cv%pow(2,32));
        // If we have less that a half of a quarter
        if(strlen($bc) < 8) {
          // Rotate square of the first 4 into a 4x2
          // Simulate matrix rotation on string
          $bc = substr($bc, 0, 3)
            . substr($bc, 0, 1)
            . substr($bc, 2, 2)
            . substr($bc, 3, 1)
            . substr($bc, 1, 1);
        }
        // If we have less than a quarter
        if(strlen($bc) < 16) {
          // Rotate the first 8 as a 4x2 into a 4x4
          $bc .= strrev($bc);
        }
        // Create image
        $im = imagecreate($this->settings->size, $this->settings->size)
            or die("Cannot Initialize new GD image stream");
        // White backdrop
        $white = imagecolorallocate($im, 255, 255, 255);
        // Number to color, hsb to control saturation and lightness
        $grid_color = $this->makeHSBColor(
            $im,
            $cv%256,
            $this->settings->saturation,
            $this->settings->lightness
        );
        // Black
        $black = imagecolorallocate($im, 0, 0, 0);
        // Put the grid into 
        $this->render($bc, $im, $grid_color, $half, $box);
        if (null !== $title) {
            // Wrap every 10 characters
            $lines = explode(' ', $title);
            // Wrap words into image
            // Add words until off image, go back and print
            $line = '';
            $lineCount = 0;
            $i = 0;
            while ($i<count($lines)&&$lineCount<$this->settings->maxLines-1) {
                $pline = $line;
                // Format
                $text = strtoupper($lines[$i]);
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
                        $this->settings->topPadding+$box*$lineCount,
                        $this->settings->titleFont,
                        $this->settings->fontSize,
                        $black,
                        $white
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
                $this->settings->topPadding+$box*$lineCount,
                $this->settings->titleFont,
                $this->settings->fontSize,
                $black,
                $white
            );
            // Add ellipses if we've truncated
            if ($i < count($lines)-1) {
                $this->drawText(
                    $im,
                    '...',
                    5,
                    $this->settings->topPadding+$this->settings->maxLines*$box,
                    $this->settings->titleFont,
                    $this->settings->fontSize+1,
                    $black,
                    $white
                );
            }
        }
        if (null !== $author) {
            // Scale author to fit by incrementing fontsizes down
            $fontSize = $this->settings->fontSize;
            do {
                $txtWidth=$this->textWidth($author, $this->settings->titleFont, $fontSize);
                $fontSize--;
            } while ($txtWidth > $this->settings->wrapWidth);
            // White text, black outline
            $this->drawText(
                $im,
                $author,
                3,
                $this->settings->size-3,
                $this->settings->authorFont,
                ++$fontSize,
                $white,
                $black,
                $fontSize < 6 ? 'left' : null // Too small to read? Align left
            );
        }
        // Output png CHECK THE PARAM
        $img = imagepng($im);
        // Clear memory
        imagedestroy($im);
        // GTFO
        return $img;
    }
    
    protected function fontPath($font)
    {
        // Check all supported image formats:
        $filenames = array('css/font/' . $font);
        $fileMatch = $this->themeTools->findContainingTheme($filenames, true);
        return empty($fileMatch) ? false : $fileMatch;
    }
    
    protected function textWidth($text, $font, $size)
    {
        $p = imagettfbbox($size, 0, $font, $text);
        return $p[2]-$p[0]-4;
    }
    
    /**
     * Simulate outlined text
     *
     * @return void
     */
    protected function drawText($im, $text, $x, $y, $font, $fontSize, $mcolor, $scolor, $align = null) {
        if (null == $align) {
            $align = $this->settings->textAlign;
        }
        if ($align == 'center') {
            $p = imagettfbbox($fontSize, 0, $this->settings->titleFont, $text);
            $txtWidth = $p[2]-$p[0]-4;
            $x = ($this->settings->size-$txtWidth)/2;
        }
        // Generate 5 lines of text, 4 offset in a border color
        imagettftext($im, $fontSize, 0, $x,   $y+1, $scolor, $font, $text);
        imagettftext($im, $fontSize, 0, $x,   $y-1, $scolor, $font, $text);
        imagettftext($im, $fontSize, 0, $x+1, $y,   $scolor, $font, $text);
        imagettftext($im, $fontSize, 0, $x-1, $y,   $scolor, $font, $text);
        // 1 centered in main color
        imagettftext($im, $fontSize, 0, $x,   $y,   $mcolor, $font, $text);
    }
    
    /**
     * Convert 16 long binary string to 8x8 color grid
     * Reflects vertically and horizontally
     *
     * @return void
     */
    protected function render($bc, $im, $color, $half, $box) {
        $bc = str_split($bc);
        for($k=0;$k<4;$k++) {
            $x = $k%2   ? $half : $half-$box;
            $y = $k/2<1 ? $half : $half-$box;
            $u = $k%2   ? $box : -$box;
            $v = $k/2<1 ? $box : -$box;
            for($i=0;$i<16;$i++) {
                if($bc[$i] == "1") {
                    imagefilledrectangle($im, $x, $y, $x+$box-1, $y+$box-1, $color);
                }
                $x += $u;
                if($x >= $this->settings->size || $x < 0) {
                    $x = $k%2 ? $half : $half-$box;
                    $y += $v;
                }
            }
        }
        //imagefilledrectangle($im,0,$size-11,$size-1,$size,$color);
    }
    
    // Using HSB allows us to control the contrast while allowing randomness
    function makeHSBColor($im, $h, $s, $v) {
        $s /= 256.0;
        if ($s == 0.0) return imagecolorallocate($im, $v,$v,$v);
        $h /= (256.0 / 6.0);
        $i = floor($h);
        $f = $h - $i;
        $p = (integer)($v * (1.0 - $s));
        $q = (integer)($v * (1.0 - $s * $f));
        $t = (integer)($v * (1.0 - $s * (1.0 - $f)));
        switch($i) {
            case 0:  return imagecolorallocate($im, $v,$t,$p);
            case 1:  return imagecolorallocate($im, $q,$v,$p);
            case 2:  return imagecolorallocate($im, $p,$v,$t);
            case 3:  return imagecolorallocate($im, $p,$q,$v);
            case 4:  return imagecolorallocate($im, $t,$p,$v);
            default: return imagecolorallocate($im, $v,$p,$q);
        }
        return imagecolorallocate($im, $R, $G, $B);
    }
}