<?php

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
				'desc' => 'Convert QFont to Unicode',
				'default' => "1",
			),
		);
		
		foreach ( self::$options as $key => &$val )
			add_option(self::option_key($key),$val["default"]);
			
		DpStringFunc::$convert_qfont = (int)self::get_option('qfont');
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
				function($input) use ($val) { return $this->sanitize($input,$val); } 
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
				case 'text':
				default:
					$this->render_input_text($option_key);
					break;
			}
		}
	}
}
