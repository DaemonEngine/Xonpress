<?php
/**
 * \file
 * \author Mattia Basaglia
 * \copyright Copyright 2015-2016 Mattia Basaglia
 * \section License
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \brief Simple 24 bit rgb color
 */
class Color
{
    public $r, $g, $b;

    function __construct ($r=0, $g=0, $b=0)
    {
        $this->r = $r;
        $this->g = $g;
        $this->b = $b;
    }

    /**
     * \brief Get the 12bit integer
     */
    function bitmask()
    {
        return ($this->r << 16) | ($this->g << 8) | $this->b;
    }

    function luma()
    {
        return (0.3*$this->r + 0.59*$this->g + 0.11*$this->b) / 255;
    }

    /**
     * \brief Multiply by a [0,1] value
     */
    function multiply($value)
    {
        $this->r = (int)($this->r*$value);
        $this->g = (int)($this->g*$value);
        $this->b = (int)($this->b*$value);
    }

    /**
     * \brief Add a [0,1] value
     */
    function add($value)
    {
        $this->r = (int)min($this->r+$value*255, 255);
        $this->g = (int)min($this->g+$value*255, 255);
        $this->b = (int)min($this->b+$value*255, 255);
    }

    /**
     * \brief Encode to html
     */
    function encode_html()
    {
        return sprintf("#%02x%02x%02x", $this->r, $this->g, $this->b);
    }

    function __toString()
    {
        return $this->encode_html();
    }
}

function colored_span($color, $text)
{
    if ( $color == null )
        return htmlspecialchars($text);

    return "<span style='color: $color;'>".htmlspecialchars($text)."</span>";
}

interface StringObject
{
    public function to_plaintext();
    public function to_html();
}

class ColoredText implements StringObject
{
    public $color;
    public $text;

    function __construct(Color $color = null, $text="")
    {
        $this->color = $color;
        $this->text = $text;
    }

    function append($text)
    {
        $this->text .= $text;
    }

    function to_plaintext()
    {
        return $this->text;
    }

    function to_html()
    {
        return colored_span($this->color, $this->text);
    }
}

class DpFont implements StringObject
{
    static private $qfont_table = array(
    '',   ' ',  '-',  ' ',  '_',  '#',  '+',  '·',  'F',  'T',  ' ',  '#',  '·',  '<',  '#',  '#', // 0
    '[',  ']',  ':)', ':)', ':(', ':P', ':/', ':D', '«',  '»',  '·',  '-',  '#',  '-',  '-',  '-', // 1
    '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?', // 2
    '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?', // 3
    '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?', // 4
    '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?', // 5
    '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?', // 6
    '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?',  '?', // 7
    '=',  '=',  '=',  '#',  '¡',  '[o]','[u]','[i]','[c]','[c]','[r]','#',  '¿',  '>',  '#',  '#', // 8
    '[',  ']',  ':)', ':)', ':(', ':P', ':/', ':D', '«',  '»',  '#',  'X',  '#',  '-',  '-',  '-', // 9
    ' ',  '!',  '"',  '#',  '$',  '%',  '&',  '\'', '(',  ')',  '*',  '+',  ',',  '-',  '.',  '/', // 10
    '0',  '1',  '2',  '3',  '4',  '5',  '6',  '7', '8',  '9',  ':',  ';',  '<',  '=',  '>',  '?',  // 11
    '@',  'A',  'B',  'C',  'D',  'E',  'F',  'G', 'H',  'I',  'J',  'K',  'L',  'M',  'N',  'O',  // 12
    'P',  'Q',  'R',  'S',  'T',  'U',  'V',  'W', 'X',  'Y',  'Z',  '[',  '\\', ']',  '^',  '_',  // 13
    '.',  'A',  'B',  'C',  'D',  'E',  'F',  'G', 'H',  'I',  'J',  'K',  'L',  'M',  'N',  'O',  // 14
    'P',  'Q',  'R',  'S',  'T',  'U',  'V',  'W', 'X',  'Y',  'Z',  '{',  '|',  '}',  '~',  '<'   // 15
    );

    public $qfont_index;
    public $color;

    function __construct($qfont_index, Color $color = null)
    {
        $this->qfont_index = $qfont_index;
        $this->color = $color;
    }

    function to_plaintext()
    {
        return $this->text;
    }

    function to_html()
    {
        return colored_span($this->color, $this->to_plaintext());
    }
}

class UnvIcon implements StringObject
{
    // List gathered from the pkg directory with
    // find . -exec zipinfo {} 'emoticons/*' \; 2>/dev/null | sed -r 's/.*emoticons\/([a-z]*).*/"\1",/' | sort | uniq
    static public $icon_list = array(
        "acidtube",
        "advbasilisk",
        "advdragoon",
        "advgranger",
        "advmarauder",
        "armoury",
        "barricade",
        "basilisk",
        "blaster",
        "booster",
        "bot",
        "bsuit",
        "chaingun",
        "check",
        "ckit",
        "cross",
        "defcomp",
        "dev",
        "dragoon",
        "dretch",
        "drill",
        "egg",
        "featured",
        "fire",
        "firebomb",
        "flamer",
        "granger",
        "grenade",
        "hive",
        "hovel",
        "human",
        "lasgun",
        "lcannon",
        "leech",
        "marauder",
        "mdriver",
        "medstat",
        "official",
        "overmind",
        "painsaw",
        "prifle",
        "reactor",
        "repeater",
        "rifle",
        "shotgun",
        "telenode",
        "tent",
        "tesla",
        "trapper",
        "turret",
        "tyrant",
        "unv",
    );
    static public $image_prefix = "";
    static public $image_suffix = ".png";

    public $icon;

    function __construct($icon)
    {
        $this->icon = $icon;
    }

    function to_plaintext()
    {
        return "[$this->icon]";
    }

    function to_html()
    {
        $url = htmlspecialchars(self::$image_prefix.$this->icon.self::$image_suffix, ENT_QUOTES);
        $alt = htmlspecialchars("$this->icon", ENT_QUOTES);
        return "<img src='$url' alt='$alt' title='$alt'/>";
    }

}

class MyString implements StringObject
{
    public $elements = array();

    function append(StringObject $element)
    {
        $this->elements []= $element;
    }

    private function convert($functor)
    {
        return implode("", array_map($functor, $this->elements));
    }

    function to_plaintext()
    {
        return $this->convert(function(StringObject $obj) {
            return $obj->to_plaintext();
        });
    }

    function to_html()
    {
        return $this->convert(function(StringObject $obj) {
            return $obj->to_html();
        });
    }
}

class StringParser
{
    public $default_color;
    static $min_luma = 0;
    static $max_luma = 0.8;

    protected $current_string;
    protected $subject;
    protected $output;


    function __construct(Color $default_color = null)
    {
        $this->default_color = $default_color;
    }

    protected function push_string()
    {
        if ( $this->current_string->text != "" )
            $this->output->append($this->current_string);
    }

    protected function push_element(StringObject $element)
    {
        $this->push_color($this->current_string->color);
        $this->output->append($element);
    }

    protected function push_color($color)
    {
        $this->push_string();

        if ( $color != null )
        {
            $luma = $color->luma();
            if ( $luma > self::$max_luma )
                $color->multiply(self::$max_luma);
            else if ( $luma < self::$min_luma )
                $color->add(self::$min_luma);
        }

        $this->current_string = new ColoredText($color);
    }

    protected function append_string($string)
    {
        $this->current_string->append($string);
    }

    protected function lex_caret(&$i)
    {
        if ( $i + 1 >= strlen($this->subject) )
        {
            $this->append_string("^");
            $i++;
            return true;
        }
        if ( $this->subject[$i+1] == "x" && $i + 4 < strlen($this->subject) )
        {
            $this->push_color(new Color(
                hexdec($this->subject[$i+2]) * 0x11,
                hexdec($this->subject[$i+3]) * 0x11,
                hexdec($this->subject[$i+4]) * 0x11
            ));
            $i += 5;
            return true;
        }
        if ( $this->subject[$i+1] == "^" )
        {
            $this->append_string("^");
            $i += 2;
            return true;
        }
        return false;
    }

    protected function lex_char(&$i)
    {
        return false;
    }

    function parse($string)
    {
        if ( $string == "" )
            return [];

        $this->output = new MyString();
        $this->subject = $string;
        $this->current_string = new ColoredText($this->default_color);

        for ( $i = 0; $i < strlen($string); )
        {
            if ( $this->lex_char($i) )
                continue;
            if ( $string[$i] == '^' && $this->lex_caret($i))
                continue;
            $this->append_string($string[$i]);
            $i++;
        }
        $this->push_string();

        $result = $this->output;
        $this->output = null;
        return $result;
    }

    function to_plaintext($string)
    {
        return $this->parse($string)->to_plaintext();
    }

    function to_html($string)
    {
        return $this->parse($string)->to_html();
    }
}

class DaemonStringParser extends StringParser
{
    # Taken from Color.cpp, replacing
    # \s+\{ ([0-9.]+)f, ([0-9.]+)f, ([0-9.]+)f, 1.00f \}, // (.).*
    # with
    #     '\4' => [\1, \2, \3],
    static public $color_table = [
        '0' => [0.20, 0.20, 0.20],
        '1' => [1.00, 0.00, 0.00],
        '2' => [0.00, 1.00, 0.00],
        '3' => [1.00, 1.00, 0.00],
        '4' => [0.00, 0.00, 1.00],
        '5' => [0.00, 1.00, 1.00],
        '6' => [1.00, 0.00, 1.00],
        '7' => [1.00, 1.00, 1.00],
        '8' => [1.00, 0.50, 0.00],
        '9' => [0.50, 0.50, 0.50],
        ':' => [0.75, 0.75, 0.75],
        ';' => [0.75, 0.75, 0.75],
        '<' => [0.00, 0.50, 0.00],
        '=' => [0.50, 0.50, 0.00],
        '>' => [0.00, 0.00, 0.50],
        '?' => [0.50, 0.00, 0.00],
        '@' => [0.50, 0.25, 0.00],
        'A' => [1.00, 0.60, 0.10],
        'B' => [0.00, 0.50, 0.50],
        'C' => [0.50, 0.00, 0.50],
        'D' => [0.00, 0.50, 1.00],
        'E' => [0.50, 0.00, 1.00],
        'F' => [0.20, 0.60, 0.80],
        'G' => [0.80, 1.00, 0.80],
        'H' => [0.00, 0.40, 0.20],
        'I' => [1.00, 0.00, 0.20],
        'J' => [0.70, 0.10, 0.10],
        'K' => [0.60, 0.20, 0.00],
        'L' => [0.80, 0.60, 0.20],
        'M' => [0.60, 0.60, 0.20],
        'N' => [1.00, 1.00, 0.75],
        'O' => [1.00, 1.00, 0.50],
    ];

    private function indexed_color($table_index)
    {
        $float_color = self::$color_table[$table_index];
        return new Color(
            (int)($float_color[0] * 255),
            (int)($float_color[1] * 255),
            (int)($float_color[2] * 255)
        );
    }

    protected function lex_caret(&$i)
    {
        if ( parent::lex_caret($i) )
            return true;

        if ( $this->subject[$i+1] == '*' )
        {
            $i += 2;
            $this->push_color(null);
            return true;
        }

        if ( $this->subject[$i+1] == "#" && $i + 7 < strlen($this->subject) )
        {
            $this->push_color(new Color(
                hexdec(substr($this->subject, $i+2, 2)),
                hexdec(substr($this->subject, $i+4, 2)),
                hexdec(substr($this->subject, $i+6, 2))
            ));
            $i += 8;
            return true;
        }

        $index = strtoupper($this->subject[$i+1]);
        if ( isset(self::$color_table[$index]) )
        {
            $this->push_color($this->indexed_color($index));
            $i += 2;
            return true;
        }
    }

    protected function lex_char(&$i)
    {
        if ( $this->subject[$i] != "[" )
            return false;

        $close = strpos($this->subject, "]", $i);
        if ( $close === false )
            return false;

        $icon = substr($this->subject, $i+1, $close-$i-1);
        if ( in_array($icon, UnvIcon::$icon_list) )
        {
            $this->push_element(new UnvIcon($icon));
            $i = $close + 1;
            return true;
        }

        return false;
    }

}

class DarkplacesStringParser extends StringParser
{
    static public $convert_qfont = true;

    protected function indexed_color($index)
    {
        switch ( (int)$index )
        {
            case 0: return new Color(  0,  0,  0);
            case 1: return new Color(255,  0,  0);
            case 2: return new Color(  0,255,  0);
            case 3: return new Color(255,255,  0);
            case 4: return new Color(  0,  0,255);
            case 5: return new Color(  0,255,255);
            case 6: return new Color(255,  0,255);
            case 7: return new Color(255,255,255);
            case 8: return new Color(136,136,136);
            case 9: return new Color(204,204,204);
        }
        return null;
    }

    protected function lex_caret(&$i)
    {
        if ( parent::lex_caret($i) )
            return true;

        if ( is_numeric($this->subject[$i+1]) )
        {
            $this->push_color($this->indexed_color($this->subject[$i+1]));
            $i += 2;
            return true;
        }
    }

    protected function lex_char(&$i)
    {
        if ( !self::$convert_qfont )
            return false;

        $char_byte = ord($this->subject[$i]);
        if ( $char_byte < 128 )
            return false;

        $length = 0;
        // extract number of leading 1s
        while ( $char_byte & 0x80 )
        {
            $length++;
            $char_byte <<= 1;
        }

        // Must be at least 110..... or fail
        if ( $length < 2 )
        {
            $i++;
            return true;
        }

        // Not enough bytes
        if ( $i + $length >= strlen($this->subject) )
        {
            $i += $length;
            return true;
        }

        // Restore byte (leading 1s have been eaten off)
        $char_byte >>= $length;

        $unicode = 0;
        $unicode_char = "";
        $j = 0;
        do
        {
            // Besides the first, they all start with 01...
            // So they give 6 bits and need to be &-ed with 63
            $unicode <<= 6;
            $unicode |= $char_byte & 63;
            $unicode_char .= $this->subject[$i+$j];

            $j++;
            $char_byte = ord($this->subject[$i+$j]);

        }
        while ($j < $length);

        if ( ($unicode & 0xFF00) == 0xE000 )
        {
            $this->push_element(new DpFont($unicode&0xff));
        }
        else
        {
            $this->append_string($unicode_char);
        }

        return true;
    }
}
