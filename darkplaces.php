<?php

/**
 * \file
 * \author Mattia Basaglia
 * \copyright Copyright 2013-2015 Mattia Basaglia
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
require_once("table.php");

/**
 * \brief Basic connection to darkplaces
 */
class DarkPlaces_Connection
{
	protected $dpheader = "\xff\xff\xff\xff";
	protected $dpresponses = array(
		"rcon" => "n",
		"srcon" => "n",
		"getchallenge" => "challenge ",
		"getinfo" => "infoResponse\n",
		"getstatus" => "statusResponse\n",
	);
	protected $dpreceivelen = 1399;

	public $host;
	public $port;
	protected $socket;

	function __construct($host="127.0.0.1", $port=26000) 
	{
		$this->host = $host;
		$this->port = $port;
	}

	function socket()
	{
		if ( $this->socket == null )
		{
			$this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
			if ( $this->socket ) {
				socket_bind($this->socket, $this->host);
				// default timeout = 1, 40 millisecond
				$this->socket_timeout(1000,40000);
			}
		}
		return $this->socket;
	}
	
	function socket_timeout($send_microseconds, $receive_microseconds = -1)
	{
		if ( !$this->socket() ) return false;
		
		if ( $receive_microseconds < 0 )
			$receive_microseconds = $send_microseconds;
		
		socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, 
			array('sec' => 0, 'usec' => $send_microseconds));
			
		socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, 
			array('sec' => 0, 'usec' => $receive_microseconds));
		
	}
	
	function request($request)
	{
		if ( !$this->socket() ) return false;
		
		$request_command = strtok($request," ");
		$contents = $this->dpheader.$request;
		
		socket_sendto($this->socket, $contents, strlen($contents), 
			0, $this->host, $this->port);
		
		$received = "";
		socket_recvfrom($this->socket, $received, $this->dpreceivelen,
			0, $this->host, $this->port);
		
		$header = $this->dpheader;
		if ( !empty($this->dpresponses[$request_command]) )
			$header .= $this->dpresponses[$request_command];
		if ( $header != substr($received, 0, strlen($header)) )
			return false;
		return substr($received, strlen($header));
	}
	
	function status() 
	{
		$status_response = $this->request("getstatus");
		if ( !$status_response )
			return array("error" => true);
		
		$lines = explode("\n",trim($status_response,"\n"));
		
		$info = explode("\\",trim($lines[0],"\\"));
		
		$result = array ( 
			"error" => false,
			"host" => $this->host,
			"port" => $this->port,
		);
		
		for ( $i = 0; $i < count($info)-1; $i += 2 )
			$result[$info[$i]] = $info[$i+1];
		
		if ( count($lines) > 1 )
		{
			$result["players"] = array();
			for ( $i = 1; $i < count($lines); $i++ )
			{
				$player = new stdClass();
				$player->score = strtok($lines[$i]," ");
				$player->ping = strtok(" ");
				$player->name = substr(strtok("\n"),1,-1);
				$result["players"][$i-1] = $player;
			}
		}
		
		return $result;
	}
}

/**
 * \brief Static access to darkplaces servers and caches results
 */
class DarkPlaces_Singleton
{
	private $connections = array();
	
	protected function __construct() {}
	protected function __clone() {}
	
	static function instance()
	{
		static $instance = null;
		if ( !$instance ) 
			$instance = new DarkPlaces_Singleton();
		return $instance;
	}
	
	// "Factory" that ensures a single instance for each server (per page load)
	protected function get_connection($host, $port)
	{
		$server = "$host:$port";
		if (isset($this->connections[$server]))
			return $this->connections[$server];
		return $this->connections[$server] = new DarkPlaces_Connection($host,$port);
	}
	
	function status($host="127.0.0.1", $port=26000)
	{
		$conn = $this->get_connection($host,$port);
		if ( !empty($conn->cached_status) )
			return $conn->cached_status;
		return $conn->cached_status = $conn->status();
	}
	
	function status_html($host = "127.0.0.1", $port = 26000, 
		$public_host = null, $css_prefix="dptable_")
	{
		$status = $this->status($host, $port);
		
		if ( empty($public_host) ) $public_host = $host;

		$html = "";
		$status_table = new HTML_Table("{$css_prefix}status");

		$status_table->simple_row("Server","$public_host:$port");

		if ( $status["error"] )
		{
			$status_table->simple_row("Error", 
				"<span class='{$css_prefix}error'>Could not retrieve server info</span>", 
				false);
			$html .= $status_table;
		}
		else
		{
			$status_table->simple_row("Name", $status["hostname"]);
			$status_table->simple_row("Map", $status["mapname"]);
			$status_table->simple_row("Players", 
				"{$status['clients']}/{$status['sv_maxclients']} ({$status['bots']} bots)");

			$html .= $status_table;

			if (!empty($status["players"]))
			{
				$players = new HTML_Table('xonpress_players');
				$players->header_row(array("Name", "Score", "Ping"));

				foreach ( $status["players"] as $player )
					$players->data_row( array (
						$player->name,
						$player->score == -666 ? "spectator" : $player->score,
						$player->ping,
					));
				$html .= $players;
			}
		}
		
		return $html;
	}
}

function DarkPlaces()
{
	return DarkPlaces_Singleton::instance();
}