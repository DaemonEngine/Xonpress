<?php

class Xonpress_Settings
{
	private $id = 'xonpress_settings';
	
	function __construct()
	{
		add_action( 'admin_init', array($this,'admin_init') );
		add_action( 'admin_menu', array($this, 'admin_menu') );
	}
	
	static function set_defaults()
	{
		$upload_dir = wp_upload_dir();
		add_option('xonpress_maps_dir',"{$upload_dir['basedir']}/maps");
		add_option('xonpress_maps_url',"{$upload_dir['baseurl']}/maps");
	}

	function admin_init()
	{
		register_setting( $this->id, $this->id, array($this,'sanitize') );
	
		add_settings_section(
			'xonpress_section', 
			'Options', 
			function(){}, 
			$this->id
		);

		add_settings_field( 
			'xonpress_maps_dir', 
			'Map Directory', 
			array($this,'render_xonpress_maps_dir'), 
			$this->id, 
			'xonpress_section' 
		);

		add_settings_field( 
			'xonpress_maps_url', 
			'Map URL', 
			array($this,'render_xonpress_maps_url'),
			$this->id, 
			'xonpress_section' 
		);
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
	
	function sanitize( $input )
	{
		$upload_dir = wp_upload_dir();
		$clean_input = array();
		
		if ( isset($input['xonpress_maps_url']) )
		{
			$string = trim($input['xonpress_maps_url']);
			if ( $string == "" )
				$string = "{$upload_dir['baseurl']}/maps";
			$clean_input['xonpress_maps_url'] = $string;
		}
		
		if ( isset($input['xonpress_maps_dir']) )
		{
			$string = trim($input['xonpress_maps_dir']);
			if ( $string == "" )
				$string = "{$upload_dir['basedir']}/maps";
			$clean_input['xonpress_maps_dir'] = $string;
		}
		
		return $clean_input;
	}
	
	function render_field($name)
	{
		$value = esc_attr(get_option($name));
		echo "<input type='text' id='$name' name='$name' value='$value' />";
	}
	
	function render_xonpress_maps_dir()
	{
		$this->render_field('xonpress_maps_dir');
	}
	
	function render_xonpress_maps_url()
	{
		$this->render_field('xonpress_maps_url');
	}
}