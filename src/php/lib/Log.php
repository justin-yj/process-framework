<?php
declare(strict_types=1);

namespace App\lib;

class Log
{
    public static function write(string $content): void
    {
        $logPath = Util::env('log_path', __DIR__ ."/../../../log");
        $logFile = $logPath .'/'. date("Ymd", time()) .".log";

        file_put_contents($logFile, date("Y-m-d H:i:s", time()) ."  ". $content ."  \n", FILE_APPEND);
    }
}
