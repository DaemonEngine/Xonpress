<?php
/**
 * \file
 * \author Mattia Basaglia
 * \copyright Copyright 2013-2016 Mattia Basaglia
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

class Darkplaces_Protocol
{
	public $header = "\xff\xff\xff\xff";
	public $responses = array(
		"rcon" => "n",
		"srcon" => "n",
		"getchallenge" => "challenge ",
		"getinfo" => "infoResponse\n",
		"getstatus" => "statusResponse\n",
	);
	public $receive_len = 1399;
	public $default_port = 26000;


	/**
	 * \brief Populates high-level keys
	 */
	function normalize_status($status_array)
	{
		$status_array["server.name"] = $status_array["hostname"];
		return $status_array;
	}
}

class Daemon_Protocol
{
	public $header = "\xff\xff\xff\xff";
	public $responses = array(
		"rcon" => "print\n",
		"srcon" => "print\n",
		"getchallenge" => "challengeResponse ",
		"getinfo" => "infoResponse\n",
		"getstatus" => "statusResponse\n",
	);
	public $receive_len = 1399;
	public $default_port = 27960;


	/**
	 * \brief Populates high-level keys
	 */
	function normalize_status($status_array)
	{
		$status_array["server.name"] = $status_array["sv_hostname"];
		return $status_array;
	}
}

class Engine_Address
{
	public $protocol;
	public $host;
	public $port;

	function __construct($protocol, $host, $port)
	{
		if ( is_string($protocol) )
			$this->protocol(Engine_Address::parse_scheme($protocol));
		else
			$this->protocol = $protocol;
		$this->host = $host;
		$this->port = $port != null ? $port : $this->protocol->default_port;
	}

	static function parse($url)
	{
		$parsed = parse_url($url);

		$protocol = Engine_Address::parse_scheme(
			isset($parsed["scheme"]) ? $parsed["scheme"] : ""
		);

		$host = isset($parsed["host"]) ? $parsed["host"] : $parsed["path"];
		if ( empty($host) )
			$host = "127.0.0.1";

		$port = isset($parsed["port"]) ? $parsed["port"] : null;

		return new Engine_Address($protocol, $host, $port);
	}

	static function parse_scheme($name)
	{
		if ( $name == "unv" )
			return new Daemon_Protocol();
		return new Darkplaces_Protocol();
	}

	static function address($obj)
	{
		if ( is_object($obj) && $obj instanceof Engine_Address )
			return $obj;
		return Engine_Address::parse($obj);
	}
}


/**
 * \brief Basic connection to darkplaces
 */
class Engine_Connection
{
	static $default_write_timeout = 1000;
	static $default_read_timeout = 40000;
	public $address;
	protected $socket;

	function __construct(Engine_Address $address)
	{
		$this->address = $address;
	}

	function socket()
	{
		if ( $this->socket == null )
		{
			$this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
			if ( $this->socket ) {
				// socket_bind($this->socket, $this->host);
				// default timeout = 1, 40 millisecond
				$this->socket_timeout(
					Engine_Connection::$default_write_timeout,
					Engine_Connection::$default_read_timeout
				);
			}
		}
		return $this->socket;
	}
	
	function socket_timeout($send_microseconds, $receive_microseconds = -1)
	{
		if ( !$this->socket() ) return false;
		
		if ( $receive_microseconds < 0 )
			$receive_microseconds = $send_microseconds;
		
		if ( $send_microseconds > 0 )
			socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO,
				array('sec' => 0, 'usec' => $send_microseconds));
			
		if ( $receive_microseconds > 0 )
			socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO,
				array('sec' => 0, 'usec' => $receive_microseconds));
		
	}
	
	function request($request)
	{
		if ( !$this->socket() ) return false;
		
		$request_command = strtok($request," ");
		$contents = $this->address->protocol->header.$request;
		
		socket_sendto($this->socket, $contents, strlen($contents), 
			0, $this->address->host, $this->address->port);
		
		$received = "";
		socket_recvfrom($this->socket, $received,
			$this->address->protocol->receive_len,
			0, $this->address->host, $this->address->port);
		
		$header = $this->address->protocol->header;
		if ( !empty($this->address->protocol->responses[$request_command]) )
			$header .= $this->address->protocol->responses[$request_command];
		if ( $header != substr($received, 0, strlen($header)) )
			return false;
		return substr($received, strlen($header));
	}
	
	function status() 
	{
		$status_response = $this->request("getstatus");

		$result = array (
			"error" => false,
			"host" => $this->address->host,
			"port" => $this->address->port,
			"server.name" => "{$this->address->host}:{$this->address->port}",
		);

		if ( !$status_response )
		{
			$result["error"] = true;
			return $result;
		}
		
		$lines = explode("\n",trim($status_response,"\n"));
		
		$info = explode("\\",trim($lines[0],"\\"));
		
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

		return $this->address->protocol->normalize_status($result);
	}

}


/**
 * \brief Connection to darkplaces which caches queries to database (abstract base)
 */
abstract class Engine_ConnectionCached extends Engine_Connection
{
	protected $dont_cache = array('getchallenge');
	public $cache_errors = false;

	function __construct(Engine_Address $address)
	{
		parent::__construct($address);
	}
	
	function key_suggestion($request)
	{
		return "{$this->address->host}:{$this->address->port}:$request";
	}
	
	abstract protected function get_cached_request($request);
	abstract protected function set_cached_request($request, $response);
	
	function request($request)
	{
		$request_command = strtok($request," ");
		$cached = false;
		if (!in_array($request_command, $this->dont_cache))
		{
			$cached = $this->get_cached_request($request);
			if ( $cached )
				return $cached;
		}
		
		$result = parent::request($request);
		if ( $this->cache_errors || $result !== false )
			$this->set_cached_request($request,$result);
		
		return $result;
	}
}

class Engine_Connection_Factory
{
	function build($address)
	{
		return new Engine_Connection(Engine_Address::address($address));
	}
}

/**
 * \brief Static access to darkplaces servers and caches results
 */
class DarkPlaces_Singleton
{
	private $connections = array();
	public $connection_factory;
	
	protected function __construct() 
	{
		$this->connection_factory = new Engine_Connection_Factory();
	}
	
	protected function __clone() {}
	
	static function instance()
	{
		static $instance = null;
		if ( !$instance ) 
			$instance = new DarkPlaces_Singleton();
		return $instance;
	}
	
	// "Factory" that ensures a single instance for each server (per page load)
	function get_connection($address)
	{
		$address = Engine_Address::address($address);
		$key = "{$address->host}:{$address->port}";
		if (isset($this->connections[$key]))
			return $this->connections[$key];
		$connection = $this->connection_factory->build($address);
		return $this->connections[$key] = $connection;
	}
	
	function status($address)
	{
		$conn = $this->get_connection($address);
		if ( !empty($conn->cached_status) )
			return $conn->cached_status;
		return $conn->cached_status = $conn->status();
	}
	
	
	/**
	 * \brief Get formatted player number
	 * \param $status Status array or host name/address
	 * \param $port Ignored or port number when \c $status is a host
	 */
	function player_number($status)
	{
		if ( $status['error'] )
			return 0;
		else
			return "{$status['clients']}/{$status['sv_maxclients']}".
					((int)$status['bots'] > 0 ? " ({$status['bots']} bots)": "");
	}
	
	function status_html($address, $public_host=null, $stats_url=null, $css_prefix="dptable_")
	{
		$address = Engine_Address::address($address);
		$status = $this->status($address);
		
		if ( empty($public_host) ) $public_host = $address->host;

		$status_table = new HTML_Table("{$css_prefix}status");

		$server_name = DpStringFunc::string_dp2html($status["server.name"]);
		if ( $stats_url )
			$server_name = "<a href='$stats_url'>$server_name</a>";
		$status_table->simple_row("Server", $server_name, false);

		if ( $status["error"] )
		{
			$status_table->simple_row("Error", 
				"<span class='{$css_prefix}error'>Could not retrieve server info</span>", 
				false);
		}
		else
		{
			$status_table->simple_row("Address","$public_host:{$address->port}");
			$status_table->simple_row("Map", $status["mapname"]);
			$status_table->simple_row("Players", $this->player_number($status));
		}
		
		return $status_table;
	}
	
	
	function players_html($address, $css_prefix="dptable_")
	{
		$status = $this->status($address);
		
		if (!empty($status["players"]))
		{
			$players = new HTML_Table("{$css_prefix}players");
			$players->header_row(array("Name", "Score", "Ping"));

			foreach ( $status["players"] as $player )
				$players->data_row( array (
					new HTML_TableCell(DpStringFunc::string_dp2html($player->name), false, array('class'=>"{$css_prefix}player_name")),
					$player->score == -666 ? "spectator" : $player->score,
					$player->ping != 0 ? $player->ping : "bot",
				), false );
			return $players;
		}
		
		return "";
	}
}

function DarkPlaces()
{
	return DarkPlaces_Singleton::instance();
}


/**
 * \brief Simple, 12 bit rgb color
 */
class Color_12bit
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
		return ($this->r<<8)|($this->g<<4)|$this->b;
	}
	
	function luma()
	{
		return (0.3*$this->r + 0.59*$this->g + 0.11*$this->b) / 15;
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
		$this->r = (int)max($this->r+$value*15,15);
		$this->g = (int)max($this->g+$value*15,15);
		$this->b = (int)max($this->b+$value*15,15);
	}
	
	/**
	 * \brief Decode darkplaces color
	 */
	static function decode_dp($dpcolor)
	{
		$dpcolor = ltrim($dpcolor,"^x");
		
		if ( strlen($dpcolor) == 3 )
			return new Color_12bit(hexdec($dpcolor[0]),hexdec($dpcolor[1]),hexdec($dpcolor[2]));
		else if ( strlen($dpcolor) == 1 )
			switch ( $dpcolor[0] )
			{
				case 0: return new Color_12bit(0,0,0);
				case 1: return new Color_12bit(0xf,0,0);
				case 2: return new Color_12bit(0,0xf,0);
				case 3: return new Color_12bit(0xf,0xf,0);
				case 4: return new Color_12bit(0,0,0xf);
				case 5: return new Color_12bit(0,0xf,0xf);
				case 6: return new Color_12bit(0xf,0,0xf);
				case 7: return new Color_12bit(0xf,0xf,0xf);
				case 8: return new Color_12bit(0x8,0x8,0x8);
				case 9: return new Color_12bit(0xc,0xc,0xc);
			}
		return new Color_12bit();
	}
	
	/**
	 * \brief Encode to html
	 */
	function encode_html()
	{
		return "#".dechex($this->r).dechex($this->g).dechex($this->b);
	}
	
	function __toString()
	{
		return $this->encode_html();
	}
}

// DarkPlaces to HTML functor
class DpStringFunc
{	
	private $open = false;
	public $min_luma = 0;
	public $max_luma = 0.8;
	static $convert_qfont = true;
	
	function html_close() 
	{
		if ( $this->open )
		{
			$this->open = false;
			return "</span>";
		}
		return "";
	}
	
	function __invoke($matches="") 
	{
		if (!empty($matches[2]))
		{
			$close = $this->html_close();
			$this->open = true;
			
			$color = Color_12bit::decode_dp($matches[2]);
			
			$luma = $color->luma();
			if ( $luma > $this->max_luma )
				$color->multiply($this->max_luma);
			else if ( $luma < $this->min_luma )
				$color->add($this->min_luma);
			
			return "$close<span style='color: $color;'>";
		}
		
		if (!empty($matches[1]))
			$text = "^";
		else if (is_array($matches))
			$text = $matches[0];
		else
			$text = $matches;
		
		return htmlspecialchars($text);
	}
	
    //                              1      2                                   3
	static private $color_regex = "/(\^\^)|((?:\^[0-9])|(?:\^x[0-9a-fA-F]{3}))|([^^]*(?:^$)?)/";
	
	/**
	 * \brief Strip colors from a DP colored string
	 */
	static function string_dp2none($string)
	{
		return preg_replace_callback(self::$color_regex,
			function ($matches)
			{
				if ( $matches[0] == "^^" )
					return "^";
				else if ( !empty($matches[3]) )
					return $matches[3];
				return "";
			}
			,self::string_dp_convert($string));
	}
	
	/**
	 * \brief Convert a colored DP string to a colored HTML string
	 */
	static function string_dp2html($string)
	{
		$functor = new DpStringFunc();
		
		return preg_replace_callback(self::$color_regex, $functor,
			self::string_dp_convert($string)).$functor->html_close();
	}
	
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
	
	/**
	 * \brief Convert special DP characters to Unicode
	 * \note Supports for up to 3 byte long UTF-8 characters
	 */
	static function string_dp_convert($string)
	{
		if ( !self::$convert_qfont )
			return $string;

		$out = "";
		
		$unicode = array();        
		$bytes = array();
		
		for ($i = 0; $i < strlen( $string ); $i++ ) 
		{
			$c = $string[$i];
			$char_byte = ord($c);
			
			if ( $char_byte < 128 )
			{
				// ASCII
				$out .= $c;
			}
			else 
			{
				// Start of multibyte character
				// NOTE: the only one not starting with 0 or 10
				if ( count($bytes) == 0 )
				{
					$unicode_char = "";
					$length = 0;
					// extract number of leading 1s
					while ( $char_byte & 0x80 )
					{
						$length++;
						$char_byte <<= 1;
					}
					
					// Must be at least 110..... or fail
					if ( $length < 2 )
						continue;
					
					// Restore byte (leading 1s have been eaten off)
					$char_byte >>= $length;
				}
				
				// Keep track of bytes
				$bytes[] = $char_byte;
				$unicode_char .= $c;
				
				// Reached the end
				// NOTE: checking for $length ensures that invalid utf-8 codes are discarded
				if ( count( $bytes ) == $length )
				{
					$unicode = 0;
					foreach ( $bytes as $byte )
					{
						// Add up all the bytes 
						// Besides the first, they all start with 01... 
						// So they give 6 bits and need to be &-ed with 63
						$unicode <<= 6;
						$unicode |= $byte & 63;
					}
					
					// Get the output string we want
					$out .= ( ($unicode & 0xFF00) == 0xE000 ) ? self::$qfont_table[$unicode&0xff] : $unicode_char;
					
					$bytes = array();
				}
			} 
		}
		return $out;
	}
}

/**
 * \brief Class used to load mapinfo files
 */
class Mapinfo
{
	public $name;        ///< Name of the bsp
	public $title;       ///< Human-readable name
	public $description; ///< Longer description
	public $author;      ///< Map creator(s)
	public $gametypes = array(); ///< List of supported gametypes
	public $screenshot; ///< Screenshot URL (needs to be set manually)
	
	/**
	 * \brief Parse mapinfo file
	 */
	function load_file($file)
	{
		if (file_exists($file))
		{
			$this->name = basename($file, ".mapinfo");
			$lines = file($file);
			$this->gametypes = array();
			foreach ( $lines as $line )
			{
				if ( preg_match('/^gametype\s+([a-z]+)/', $line, $matches) )
					$this->gametypes []= $matches[1];
				else if ( preg_match('/^description\s+(.+)/', $line, $matches) )
					$this->description = $matches[1];
				else if ( preg_match('/^title\s+(.+)/', $line, $matches) )
					$this->title = $matches[1];
				else if ( preg_match('/^author\s+(.+)/', $line, $matches) )
					$this->author = $matches[1];
			}
			return true;
		}
		return false;
	}
}
