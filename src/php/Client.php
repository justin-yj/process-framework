<?php
declare(strict_types=1);

namespace App;

use App\lib\Util;
use Socket;

class Client
{
    /**
     * @var false|mixed|resource|Socket
     */
    protected static $socket;
    private string $ip;
    private int $port;

    public function __construct()
    {
        $this->ip = Util::env("socket_ip", "127.0.0.1");
        $this->port = (int)Util::env("socket_port", "9527");
        self::$socket = socket_create( AF_INET, SOCK_STREAM, SOL_TCP );

        if(!self::$socket){
            exit("socket create failed:". socket_strerror( self::$socket ) ."\n");
        }

        $connection = socket_connect(self::$socket, $this->ip, $this->port);
        if(!$connection){
            exit("socket connect() failed:".socket_strerror(socket_last_error(self::$socket))."\n");
        }
    }

    protected function run(): void
    {
        echo socket_read( self::$socket, 1024 );
        $stdin = fopen( 'php://stdin', 'r' );
        while (true) {
            $buffer = fgets($stdin, 1024);
            socket_write(self::$socket, $buffer, strlen($buffer));
            $message = trim(socket_read(self::$socket, 1024));

            echo "Message: ". $message ."\n";
            if ($message == "exit"){
                break;
            }
        }

        fclose($stdin);
        socket_close(self::$socket);
    }
}
