<?php
declare(strict_types=1);

namespace App;

use App\lib\Log;
use App\lib\Util;

class Service
{
    protected $status = '';
    private int $pid;
    private string $serviceName;
    private string $pidFile;
    private int $processMinNum;
    private int $processMaxNum;
    private array $workAllPid = [];


    public function __construct()
    {
        global $argv;
        $this->status = $argv[1] ?? '';

        $this->serviceName = Util::env("service_name", "service");
        $this->processMinNum = (int)Util::env("process_min_num", '1');
        $this->processMaxNum = (int)Util::env("process_max_num", '2');
        $this->pidFile = Util::env("pid_file", "process.pid");
        if(file_exists($this->pidFile)){
            $pid = file_get_contents($this->pidFile);
        }
        $this->pid = (int)($pid ?? "");
    }

    public function run(): void
    {
        if($this->pid === 0 && $this->status != "start"){
            exit("\033[31mPid error. Service not started... \n" . "Useage php ". $_SERVER["PHP_SELF"] ." start\033[0m\n");
        }

        $status = $this->status;
        $this->$status();
    }

    protected function start(): void
    {
        if($this->pid > 0){
            exit("\033[1;31mAddress already in use.\033[0m\n");
        }
        /*
         * fork 子进程后，父子两个进程会继续执行以下的逻辑，根据pid不同区分进程
         *  父进程拿到pid为子进程的pid
         *  子进程拿到的pid为0
         *  pid小于0 进程创建失败
         */
        $pid = pcntl_fork();
        if($pid < 0){
            exit("\033[31mFork Error...\033[0m\n");
        }

        if($pid > 0){
            $this->parentProcess($pid);
        }
        if($pid === 0){
            $this->childProcess();
        }
    }

    /**
     * 父进程逻辑
     */
    protected function parentProcess(int $pid): void
    {
        cli_set_process_title($this->serviceName .":service");
        // while (1){
        //     sleep(10);
        // }

        echo file_get_contents("banner.txt");
        echo "                                Process Framework(v1.0.0)                                             \n";
        echo "***************************************************************************************************   \n";
        echo "*\t\t  Status: \033[32mrunning\033[0m, Master PId: ". $pid .", Worker Process: ". $this->processMaxNum ."\n";
        echo "***************************************************************************************************   \n";
        echo "\033[1;32mServer Start Success...\033[0m\n";
    }

    /**
     * 子进程逻辑
     */
    protected function childProcess(): void
    {
        // 使当前进程成为会话的主进程
        //  posix_setsid();

        // 关闭文件描述符
        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);

        // 重定向输入输出
        // Description https://stackoverflow.com/questions/6472102/redirecting-i-o-in-php
        // 当fclose(STDOUT) 调用时，文件描述符资源将被关闭并与STDOUT常量分离，
        // 当调用echo输出字符串时，PHP将不再使用STDOUT常量，它将从系统中实时查询当前的标准输出文件描述符，即：$STDOUT。
        // STDOUT 被关闭后将不再可用，比如fwrite(STDOUT, "hello")不可用
        global $STDOUT, $STDERR;
        $STDOUT = fopen("/dev/null", "a");
        $STDERR = fopen("/dev/null", "a");

        // 在子进程中获取pid
        $pid = posix_getpid();
        // 子进程设置名称
        cli_set_process_title($this->serviceName .":master");
        // 存储master进程pid
        file_put_contents($this->pidFile, $pid);
        //  pid写入日志
        Log::write("\n");
        Log::write("process start: ---master pid: ". $pid ."---┐");
        // 安装信号处理器
        // SIGKILL 信号不能被捕捉
        // Description 信号处理器注册到系统信号设置中的两种方式：1、tick机制 2、使用pcntl_signal_dispatch() 代码循环中自行处理 3、pcntl_async_signals()  *PHP >= 7.1
        // tick机制：*占用资源高，效率低
        //          1、需声明declare(ticks=1);
        //          2、使用PHP函数无法直接注册到操作系统信号设置中，pcntl信号需依赖tick机制。
        //          3、实现原理：触发信号后先将信号加入到一个队列中。然后再PHP的ticks回调函数中不断检查是否有信号，如果有信号就执行配置的回调函数
        // 使用pcntl_signal_dispatch()：
        //          1、代码循环中调用

        //  添加SIGUSR1信号处理器
        $this->loadSignal(SIGUSR1, $pid);
        //  添加SIGUSRs信号处理器
        $this->loadSignal(SIGUSR2, $pid);
        //  添加SIGALRM信号处理器
        $this->loadSignal(SIGALRM, $pid);

        // master进程发送SIGALRM信号
        $this->sendSignal(SIGALRM, $pid);
        while (true){
            pcntl_signal_dispatch();
            sleep(1);
        }
    }

    protected function sendSignal(int $signal, int $pid): bool
    {
        return posix_kill($pid, $signal);
    }

    protected function loadSignal(int $signal, int $pid): void
    {
        switch ($signal){
            case SIGUSR1: $this->sigUsrOne($pid); break;
            case SIGUSR2: $this->sigUsrTwo($pid); break;
            case SIGALRM: $this->sigAlrm($pid); break;
            default:
        }
    }

    protected function sigUsrOne(int $pid): void
    {
        $sigusr1Result = pcntl_signal(SIGUSR1, function () use ($pid){
            Log::write("SIGUSR1 start  -----------------------|");

            // 杀掉work进程
            foreach ($this->workAllPid as $workPid){
                $workKillResult = $this->sendSignal(SIGKILL, $workPid);
                Log::write("workKIllResult:    pid ". $workPid ." ". ($workKillResult == 1? "success":"fail   ") ."  |");
            }

            Log::write("SIGUSR1 end    -----------------------|");
            Log::write("masterKIllResult:  pid ". $pid ." success  |");
            Log::write("process end:   ---master pid: ". $pid ."---┘\n");

            // 杀掉主进程
            $this->sendSignal(SIGKILL, $pid);
        });

        Log::write("signal-SIGUSR1-result: ". ($sigusr1Result == 1? "success":"fail   ") ."        |");
    }

    protected function sigUsrTwo(int $pid): void
    {
        $sigusr2Result = pcntl_signal(SIGUSR2, function () use ($pid){
            Log::write("SIGUSR2 start  -----------------------|");

            // 杀掉work进程
            foreach ($this->workAllPid as $workPid){
                $workKillResult = $this->sendSignal(SIGKILL, $workPid);
                Log::write("workKIll:". $workPid." | ". $workKillResult );
            }

            // 发送SIGALRM信号
            $this->sendSignal(SIGALRM, $pid);

            Log::write("SIGUSR2 end    -----------------------|");
        });


        Log::write("signal-SIGUSR2-result: ". ($sigusr2Result == 1? "success":"fail   ") ."        |");
    }

    protected function sigAlrm(int $pid): void
    {
        $sigalrmResult = pcntl_signal(SIGALRM, function () use ($pid){
            Log::write("SIGALRM start  -----------------------|");

            //  创建work进程
            for ($i = 1; $i <= $this->processMaxNum; $i++){
                $workPid = pcntl_fork();
                // 创建失败
                if($workPid < 0){
                    // ...
                }
                // master进程存储workPid
                if($workPid > 0){
                    $this->workAllPid[] = $workPid;

                    Log::write("masterFork:     work process ". $workPid ."    |");
                }
                // work进程执行逻辑
                if($workPid === 0){
                    //  work process logic
                    cli_set_process_title($this->serviceName .":worker");
                    while(true){
                        sleep(5);
                    }
                }
            }

            //  进程超过设置配置最大数  杀掉多余进程
            $process_num = count($this->workAllPid) - $this->processMaxNum;
            for ($i = 1; $i <= $process_num; $i++){
                $this->sendSignal(SIGKILL, $this->workAllPid[$i]);
            }

            Log::write("SIGALRM end    -----------------------|");
        });

        Log::write("signal-SIGALRM-result: ". ($sigalrmResult == 1? "success":"fail   ") ."        |");
    }

    protected function restart(): void
    {
        if(!posix_kill($this->pid, SIGUSR2)){
            exit("\033[31m". $this->serviceName ." restart fail...\033[0m\n");
        }

        exit("\033[1;32m". $this->serviceName ." restart success...\033[0m\n");
    }

    protected function reload(): void
    {
        if(!posix_kill($this->pid, SIGHUP)){
            exit("\033[31m". $this->serviceName ." reload fail...\033[0m\n");
        }

        exit("\033[1;32m". $this->serviceName ." reload success...\033[0m\n");
    }

    protected function stop(): void
    {
        file_put_contents($this->pidFile, "");

        if(!posix_kill($this->pid, SIGUSR1)){
            exit("\033[31m". $this->serviceName ." stop fail...\033[0m\n");
        }

        exit("\033[1;32m". $this->serviceName ." stop success...\033[0m\n");
    }

    public function __call(string $name, array $arguments): void
    {
        exit("\033[1;31mUseage php ". $_SERVER["PHP_SELF"] ." start|reload|restart|stop\033[0m\n");
    }
}
