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
require_once("string.php");

class Protcol
{
    public $header = "\xff\xff\xff\xff";
    public $responses = array();
    public $receive_len = 1024;
    public $default_port = 26000;
    public $scheme = null;
    public $string;

    /**
     * \brief Populates high-level keys
     */
    function normalize_status($status_array)
    {
        return $status_array;
    }

    /**
     * \brief Returns a list of addresses, as retrieved from the master
     */
    function server_list($address = null)
    {
        return [];
    }

    function default_master()
    {
        return null;
    }
}

class Darkplaces_Protocol extends Protcol
{
    public $responses = array(
        "rcon" => "n",
        "srcon" => "n",
        "getchallenge" => "challenge ",
        "getinfo" => "infoResponse\n",
        "getstatus" => "statusResponse\n",
    );
    public $receive_len = 1399;
    public $default_port = 26000;

    function __construct()
    {
        $this->string = new DarkplacesStringParser();
    }

    function normalize_status($status_array)
    {
        $status_array["server.name"] = $status_array["hostname"];
        $status_array["server.game"] = $status_array["gamename"];
        $status_array["server.version"] = $status_array["gameversion"];
        return $status_array;
    }
}

class Daemon_Protocol extends Protcol
{
    public $responses = array(
        "rcon" => "print\n",
        "srcon" => "print\n",
        "getchallenge" => "challengeResponse ",
        "getinfo" => "infoResponse\n",
        "getstatus" => "statusResponse\n",
    );
    public $receive_len = 32768;
    public $default_port = 27960;
    public $scheme = "unv";

    function __construct()
    {
        $this->string = new DaemonStringParser();
    }

    function normalize_status($status_array)
    {
        $status_array["server.name"] = $status_array["sv_hostname"];
        $matches = [];
        if ( preg_match("/^(\S+)\s+(\S+).*/", $status_array["version"], $matches) )
        {
            $status_array["server.game"] = $matches[1];
            $status_array["server.version"] = $matches[2];
        }
        return $status_array;
    }

    function default_master()
    {
        return new Engine_Address($this, "master.unvanquished.net", 27950);
    }

    function server_list($address = null)
    {
        if ( $address == null )
            $address = $this->default_master();

        $game = "UNVANQUISHED";
        $protocol = 86;
        $extra_flags = "empty full";
        $read_size = 1024;

        $request = "{$this->header}getserversExt $game $protocol $extra_flags";

        $socket = new EngineSocket();
        $response = $socket->write($address, $request);

        $packet_index = 0;
        $packet_count = 1;
        $servers = [];
        while ( $packet_index < $packet_count )
        {
            $this->parse_master_response(
                $socket->read($address, $read_size),
                $packet_index,
                $packet_count,
                $servers
            );
        }
        return $servers;
    }

    /**
     * \brief Parses the result of a getserversExt response from the master.
     */
    private function parse_master_response($data, &$index, &$count, &$servers)
    {
        $index = 1;
        $count = 1;

        $buffer = fopen("php://memory", "rwb");
        fwrite($buffer, $data);
        rewind($buffer);

        $nextbyte = function() use ($buffer)
        {
            return fread($buffer, 1);
        };

        $read_until = function($skipped, &$read = null) use ($nextbyte)
        {
            $byte = $nextbyte();
            $read = "";
            while ( $byte != "" && strpos($skipped, $byte) === false )
            {
                $read .= $byte;
                $byte = $nextbyte();
            }
            return $byte;
        };

        $byte = $read_until("\\/\0");

        if ( $byte === "\0" )
        {
            $index_str = '';
            $count_str = '';
            $byte = $read_until("\\/\0", $index_str);
            if ( $byte === "\0" )
            {
                $byte = $read_until("\\/\0", $count_str);
                if ( (int)$count_str >= 1 && (int)$index_str >= 1 && (int)$index_str <= (int)$count_str )
                {
                    $index = (int)$index_str;
                    $count = (int)$count_str;
                }
            }
            if ( $byte == '\\' || $byte == '/')
                $byte = $read_until("\\/");
        }

        for ( ; !feof($buffer);  $byte = $nextbyte() )
        {
            if ( $byte == "\\" ) # IPv4
            {
                $ip = [];
                foreach ( range(0, 3) as $i )
                {
                    $ip[] = (string)ord($nextbyte());
                    // ipv4 address field starts with '\'
                    // but serverResponse packet also ends with '\'
                    // if end of packet is reached, do not parse ipv4
                    // and can stop the parsing there
                    if ( feof($buffer) )
                        return;
                }
                $port = ord($nextbyte()) << 8;
                $port |= ord($nextbyte());
                $ip = implode(".", $ip);
                $servers[] = new Engine_Address($this, $ip, $port);
            }
            elseif ( $byte == "/" ) # IPv6
            {
                $ip = [];
                foreach ( range(0, 7) as $i )
                {
                    $high = sprintf('%02x', ord($nextbyte()));
                    $low = sprintf('%02x', ord($nextbyte()));
                    $ip[] = "$high$low";
                    $port = ord($nextbyte()) << 8;
                    $port |= ord($nextbyte());
                    $servers[] = new Engine_Address($this, "[".implode(":", $ip)."]", $port);
                }
            }
        }
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
            $this->protocol = Engine_Address::parse_scheme($protocol);
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

    function __toString()
    {
        $scheme = "";
        if ( $this->protocol->scheme )
            $scheme = $this->protocol->scheme . "://";
        return "$scheme$this->host:$this->port";
    }

}

class EngineSocket
{
    static $default_write_timeout =  1000; // 1 millisecond
    static $default_read_timeout = 500000; // 500 milliseconds
    private $socket = null;

    function socket()
    {
        if ( $this->socket == null )
        {
            $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            $this->set_timeout(
                EngineSocket::$default_write_timeout,
                EngineSocket::$default_read_timeout
            );
        }
        return $this->socket;
    }

    function set_timeout($send_microseconds, $receive_microseconds = -1)
    {
        if ( !$this->socket )
            return;

        if ( $receive_microseconds < 0 )
            $receive_microseconds = $send_microseconds;

        if ( $send_microseconds > 0 )
            socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO,
                array('sec' => 0, 'usec' => $send_microseconds));

        if ( $receive_microseconds > 0 )
            socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO,
                array('sec' => 0, 'usec' => $receive_microseconds));

    }

    function request(Engine_Address $address, $request)
    {
        if ( !$this->socket() )
            return false;

        $request_command = strtok($request, " ");
        $contents = $address->protocol->header.$request;

        $this->write($address, $contents);

        $received = $this->read($address, $address->protocol->receive_len);

        $header = $address->protocol->header;
        if ( !empty($address->protocol->responses[$request_command]) )
            $header .= $address->protocol->responses[$request_command];
        if ( $header != substr($received, 0, strlen($header)) )
            return false;
        return substr($received, strlen($header));
    }

    function write($address, $data)
    {
        socket_sendto($this->socket(), $data, strlen($data),
            0, $address->host, $address->port);
    }

    function read($address, $read_size)
    {
        $received = "";
        socket_recvfrom($this->socket(), $received, $read_size,
            0, $address->host, $address->port);
        return $received;
    }

}


/**
 * \brief Basic connection to a game engine
 */
class Engine_Connection
{
    public $address;
    protected $socket;

    function __construct(Engine_Address $address)
    {
        $this->address = $address;
        $this->socket = new EngineSocket();
    }

    function status()
    {
        $status_response = $this->request("getstatus");

        $result = array (
            "error" => false,
            "host" => $this->address->host,
            "port" => $this->address->port,
            "server.name" => "$this->address",
            "server.game" => "",
            "server.version" => "",
            "clients.players" => array(),
            "mapname" => "",
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
            for ( $i = 1; $i < count($lines); $i++ )
            {
                $player = new stdClass();
                $player->score = strtok($lines[$i], " ");
                $player->ping = strtok(" ");
                $player->name = substr(strtok("\n"), 1, -1);
                $player->bot = $player->ping == 0;
                $result["clients.players"][] = $player;
            }
        }

        return $this->address->protocol->normalize_status($result);
    }

    function request($request)
    {
        return $this->socket->request($this->address, $request);
    }

    function master_list_servers()
    {
        return $this->address->protocol->server_list($this->address);
    }

}


/**
 * \brief Connection to a game engine which caches queries to database (abstract base)
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
        return "$this->address:$request";
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


    function master_list_servers()
    {
        $cache_key = "__master_list_servers";
        $cached = $this->get_cached_request($cache_key);
        if ( $cached )
        {
            return explode(" ", $cached);
        }

        $addresses = parent::master_list_servers();
        $this->set_cached_request($cache_key, implode(" ", $addresses));
        return $addresses;
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
 * \brief Static access to game engine servers and caches results
 */
class Controller_Singleton
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
            $instance = new Controller_Singleton();
        return $instance;
    }

    // "Factory" that ensures a single instance for each server (per page load)
    function get_connection($address)
    {
        $address = Engine_Address::address($address);
        $key = (string)$address;
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

        $bots = 0;
        foreach ( $status["clients.players"] as $player )
        {
            if ( $player->bot )
                $bots++;
        }

        $count_string = (count($status["clients.players"]) - $bots).
                        "/{$status['sv_maxclients']}";

        if ( $bots > 1 )
            $count_string .= " ($bots bots)";
        elseif ( $bots == 1 )
            $count_string .= " ($bots bot)";

        return $count_string;
    }

    function status_html($address, $public_host=null, $stats_url=null, $css_prefix="dptable_")
    {
        $address = Engine_Address::address($address);
        $status = $this->status($address);

        $status_table = new HTML_Table("{$css_prefix}status");

        $server_name = $address->protocol->string->to_html($status["server.name"]);
        if ( $stats_url )
            $server_name = new HTML_Link($server_name, $stats_url);
        $status_table->simple_row("Server", $server_name, false);

        if ( $status["error"] )
        {
            $status_table->simple_row("Error",
                "<span class='{$css_prefix}error'>Could not retrieve server info</span>",
                false);
        }
        else
        {
            $public_address = clone $address;
            if ( !empty($public_host) )
                $public_address->host = $public_host;
            $link = "$public_address";
            if ( $public_address->protocol->scheme )
                $link = new HTML_Link($link);

            $status_table->simple_row("Address", $link, false);
            $status_table->simple_row("Map", $status["mapname"]);
            $status_table->simple_row("Players", $this->player_number($status));
        }

        return $status_table;
    }


    function players_html($address, $css_prefix="dptable_")
    {
        $address = Engine_Address::address($address);
        $status = $this->status($address);

        if (!empty($status["clients.players"]))
        {
            $players = new HTML_Table("{$css_prefix}players");
            $players->header_row(array("Name", "Score", "Ping"));

            foreach ( $status["clients.players"] as $player )
                $players->data_row( array (
                    new HTML_TableCell(
                        $address->protocol->string->to_html($player->name),
                        false,
                        array('class'=>"{$css_prefix}player_name")
                    ),
                    $player->score == -666 ? "spectator" : $player->score,
                    $player->bot ? "bot" : $player->ping,
                ), false );
            return $players;
        }

        return "";
    }

    function server_list_html($addresses, $css_prefix="dptable_")
    {
        if ( empty($addresses) )
            return "";

        $table = new HTML_Table("{$css_prefix}server_list");
        $headers = ["Server", "Version", "Map", "Players", "Links"];
        $table->header_row($headers);

        foreach ( $addresses as $address )
        {
            $address = Engine_Address::address($address);
            $status = $this->status($address);

            $link = "$address";
            if ( $address->protocol->scheme )
                $link = new HTML_Link("Connect", $link);

            if ( isset($status["sv_statsURL"]) )
                $link .= " " . new HTML_Link("Stats", $status["sv_statsURL"]);

            $table->data_row(
                [
                    $address->protocol->string->to_html($status["server.name"]),
                    $status["server.version"],
                    $status["mapname"],
                    $this->player_number($status),
                    $link
                ],
                false,
                array(
                    "class" => "{$css_prefix}server_list_info",
                )
            );

            $table->data_row(
                new HTML_TableCell(
                    $this->players_html($address),
                    false,
                    array("colspan" => count($headers))
                ),
                false,
                array(
                    "class" => "{$css_prefix}server_list_players",
                )
            );

        }
        return $table;
    }

    function server_list($master_address)
    {
        if ( !$master_address )
            return [];
        $connection = $this->get_connection($master_address);
        return $connection->master_list_servers();
    }

    function protocol_server_list($protocol)
    {
        if ( is_string($protocol) )
            $protocol = Engine_Address::parse_scheme($protocol);
        return $this->server_list($protocol->default_master());
    }
}

function Controller()
{
    return Controller_Singleton::instance();
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
