<?php

class Xonpress_ServerTable extends WP_Widget 
{

	/**
	 * Sets up the widgets name etc
	 */
	public function __construct() {
		// widget actual processes
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		// outputs the content of the widget
	}

	/**
	 * Outputs the options form on admin
	 *
	 * @param array $instance The widget options
	 */
	public function form( $instance ) 
	{
		// outputs the options form on admin
	}

	/**
	 * Processing widget options on save
	 *
	 * @param array $new_instance The new options
	 * @param array $old_instance The previous options
	 */
	public function update( $new_instance, $old_instance ) 
	{
		// processes widget options to be saved
	}
}


function xonpress_widgets_init()
{
	register_widget( 'Xonpress_ServerTable' );
}
