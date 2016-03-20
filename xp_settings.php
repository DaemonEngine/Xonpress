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

class Xonpress_Settings
{
    static $options;
    static $prefix = 'xonpress_settings';
    private $id;


    function __construct()
    {
        $this->id = self::$prefix;

        if ( empty(self::$options) )
            self::init();

        add_action( 'admin_init', array($this,'admin_init') );
        add_action( 'admin_menu', array($this, 'admin_menu') );
    }

    static function option_key($key)
    {
        return self::$prefix.'_'.$key;
    }

    static function get_option($name)
    {
        return get_option(self::option_key($name));
    }

    static function init()
    {
        $upload_dir = wp_upload_dir();

        self::$options = array(
            "maps_dir" => array(
                'type' => 'text',
                'desc' => 'Map Directory',
                'default' => "{$upload_dir['basedir']}/maps",
            ),
            "maps_url" => array(
                'type' => 'text',
                'desc' => 'Map URL',
                'default' => "{$upload_dir['baseurl']}/maps",
            ),
            "qfont" => array(
                'type' => 'checkbox',
                'desc' => 'Convert Darkplaces QFont to Unicode',
                'default' => "1",
            ),
            "unvicon_prefix" => array(
                'type' => 'text',
                'desc' => 'Unvanquished icon prefix',
                'default' => UnvIcon::$image_prefix,
            ),
            "unvicon_suffix" => array(
                'type' => 'text',
                'desc' => 'Unvanquished icon suffix',
                'default' => UnvIcon::$image_suffix,
            ),
            "color_min_luma" => array(
                'type' => 'number',
                'desc' => 'Minimum color luma (to contrast over dark backgrounds)',
                'default' => StringParser::$min_luma,
                'min' => 0,
                'max' => 1,
                'step' => 0.1,
            ),
            "color_max_luma" => array(
                'type' => 'number',
                'desc' => 'Maximum color luma (to contrast over light backgrounds)',
                'default' => StringParser::$max_luma,
                'min' => 0,
                'max' => 1,
                'step' => 0.1,
            ),
        );

        foreach ( self::$options as $key => &$val )
        {
            add_option(self::option_key($key), $val["default"]);

            if ( $val["type"] == "number" )
            {
                if ( !isset($val['min']) )
                    $val['min'] = 0;
                if ( !isset($val['max']) )
                    $val['max'] = 1;
                if ( !isset($val['step']) )
                    $val['step'] = 1;
            }
        }

        DarkplacesStringParser::$convert_qfont = (int)self::get_option('qfont');
        UnvIcon::$image_prefix = self::get_option('unvicon_prefix');
        UnvIcon::$image_suffix = self::get_option('unvicon_suffix');
        StringParser::$min_luma = self::get_option('color_min_luma');
        StringParser::$max_luma = self::get_option('color_max_luma');
    }

    function admin_init()
    {
        //register_setting( $this->id, $this->id, array($this,'sanitize') );

        add_settings_section(
            'xonpress_section',
            'Options',
            function(){},
            $this->id
        );

        foreach ( self::$options as $key => $val )
        {
            register_setting( $this->id, self::option_key($key),
                function($input) use ($val) { return $this->sanitize($input, $val); }
            );
            add_settings_field(
                self::$prefix.'['.$key.']',
                $val['desc'],
                function() use($key) { $this->render_input($key); },
                $this->id,
                'xonpress_section'
            );
        }
    }

    function admin_menu()
    {
        if ( !is_admin() )
            return;

        add_options_page(
            'Xonpress',
            'Xonpress',
            'manage_options',
            $this->id,
            array($this,'menu_page')
        );
    }

    function menu_page()
    {
        ?>
        <form action='options.php' method='post'>

            <h2>Xonpress</h2>

            <?php
            settings_fields( $this->id );
            do_settings_sections( $this->id );
            submit_button();
            ?>

        </form>
        <?php
    }

    private function sanitize( $input, $val )
    {
        switch ( $val['type'] )
        {
            case 'checkbox':
                return $input ? 1 : 0;
            case 'text':
                $string = trim($input);
                if ( $string == "" )
                    $string = $val["default"];
                return $string;
            case 'number':
                $value = (float)$input;
                $value = max((float)$val["min"], $value);
                $value = min((float)$val["max"], $value);
                return $value;
        }
        return $input;
    }

    private function render_input_text($name)
    {
        $value = esc_attr(get_option($name));
        echo "<input type='text' id='$name' name='$name' value='$value' />";
    }

    private function render_input_checkbox($name)
    {
        echo "<input type='checkbox' id='$name' name='$name' ";
        if ( (int)get_option($name) )
            echo "checked='checked' ";
        echo "/>";
    }

    private function render_input_number($name, $min, $max, $step)
    {
        $value = esc_attr(get_option($name));
        echo "<input type='number' min='$min' max='$max' step='$step' value='$value'/>";
    }

    private function render_input($key)
    {
        if ( isset(self::$options[$key]) )
        {
            $option_key = self::option_key($key);
            switch ( self::$options[$key]['type'] )
            {
                case 'checkbox':
                    $this->render_input_checkbox($option_key);
                    break;
                case 'number':
                    $min = self::$options[$key]['min'];
                    $max = self::$options[$key]['max'];
                    $step = self::$options[$key]['step'];
                    $this->render_input_checkbox($option_key, $min, $max, $step);
                    break;
                case 'text':
                default:
                    $this->render_input_text($option_key);
                    break;
            }
        }
    }
}
