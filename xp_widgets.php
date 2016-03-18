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

class Xonpress_ServerTable extends WP_Widget 
{

	static $server_regex = '/([^:]*)(?::([0-9]*))/';

	/**
	 * Sets up the widgets name etc
	 */
	public function __construct() 
	{
		parent::__construct (
			'Xonpress_ServerTable', // id
			'Xonotic Server Table',  //name
			array ( // wp_register_sidebar_widget options 
				'description' => 'Shows the number of players in the provided servers', 
			) 
		);
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) 
	{
		echo $args['before_widget'];
		
		if ( ! empty( $instance['title'] ) ) 
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ). $args['after_title'];
		
		$servers = explode( ' ', $instance['servers'] );
		
		$table = new HTML_Table();
		
		foreach ( $servers as $server )
		{
			if ( preg_match(self::$server_regex, $server, $matches) )
			{
				$host = empty($matches[1]) ? '127.0.0.1' : $matches[1];
				$port = empty($matches[2]) ? 26000 : $matches[2];
				$status = DarkPlaces()->status($host,$port);
				if ( $status['error'] )
					$table->simple_row("$host:$port", 0);
				else
					$table->simple_row(
						DpStringFunc::string_dp2none($status["hostname"]),
						DarkPlaces()->player_number($status)
					);
			}
		}
		
		echo $table;
		echo $args['after_widget'];
	}

	/**
	 * Outputs the options form on admin
	 *
	 * @param array $instance The widget options
	 */
	public function form( $instance ) 
	{
		$title = empty( $instance['title'] ) ? '' : $instance['title'];
		$servers = empty( $instance['servers'] ) ? '127.0.0.1:26000' : $instance['servers'];
		?>
		<p>
		<label for="<?php echo $this->get_field_id( 'title' ); ?>">Title:</label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
		<label for="<?php echo $this->get_field_id( 'servers' ); ?>">Servers:</label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'servers' ); ?>" name="<?php echo $this->get_field_name( 'servers' ); ?>" type="text" value="<?php echo esc_attr( $servers ); ?>" />
		</p>
		<?php 
	}

	/**
	 * Processing widget options on save
	 *
	 * @param array $new_instance The new options
	 * @param array $old_instance The previous options
	 */
	public function update( $new_instance, $old_instance ) 
	{
		$instance = array();
		
		$instance['title'] = isset($new_instance['title']) ?  $new_instance['title'] : '';
		
		$servers = array();
		
		$new_servers = preg_split('/[\s,]+/', $new_instance['servers'], null, PREG_SPLIT_NO_EMPTY );
		foreach ( $new_servers as $server )
			if ( preg_match(self::$server_regex, $server) )
				$servers []= $server;
		
		$instance['servers'] = implode(' ',$servers);

		return $instance;
	}
}


function xonpress_widgets_init()
{
	register_widget( 'Xonpress_ServerTable' );
}
