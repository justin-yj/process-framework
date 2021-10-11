<?php
declare(strict_types=1);

namespace App;

use App\lib\Util;
use Socket;

class Server
{
    /**
     * @var false|mixed|resource|Socket
     */
    protected static $socket;
    protected string $ip;
    protected int $port;

    public function __construct()
    {
        $this->ip = Util::env("socket_ip", "127.0.0.1");
        $this->port = (int)Util::env("socket_port", "9527");
        self::$socket = socket_create( AF_INET, SOCK_STREAM, SOL_TCP );

        if(!self::$socket){
            exit("socket create failed:". socket_strerror( self::$socket ) ."\n");
        }
        if(!socket_bind(self::$socket, $this->ip, $this->port)){
            exit("socket bind failed:". socket_strerror(socket_last_error(self::$socket)) ."\n");
        }
        if(!socket_listen(self::$socket)){
            exit("socket listen failed:". socket_strerror(socket_last_error(self::$socket)) ."\n");
        }
    }

    protected function run(): void
    {
        while (true) {
            $connection = socket_accept(self::$socket);
            if(!$connection) {
                echo "socket accept failed:". socket_strerror(socket_last_error(self::$socket)) ."\n";
                break;
            }

            $sendMessage = "Hello Word";
            socket_write($connection, $sendMessage, strlen($sendMessage) );
            while (true) {
                $message = strtoupper( trim( socket_read($connection, 1024 ) ) );

                socket_write( $connection, $message, strlen($message) );
                if ($message == "exit"){
                    break;
                }
            }

            socket_close($connection);
        }
    }

    public static function close(): void
    {
        socket_close(self::$socket);
    }
}
