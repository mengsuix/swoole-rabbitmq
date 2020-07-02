<?php
namespace SwooleRabbitmq\App;

use Swoole\Coroutine;
use Swoole\Event;
use Swoole\Process;
use Swoole\Runtime;
use Swoole\Timer;


class Application
{
    /**
     * 进程池
     * @var
     */
    private $pool;

    /**
     * 子进程协程状态
     * @var bool
     */
    private $enableCoroutine;

    /**
     * 进程数
     * @var null
     */
    private $workerNum;

    /**
     * 子进程开启回调函数
     * @var
     */
    private $onStartCallback;

    /**
     * 子进程退出回调函数
     * @var
     */
    private $onStopCallback;

    private $ppid;

    private $cpuNum;

    private $cpuPool = [];

    public function __construct($enableCoroutine = true, $workerNum = null)
    {
        //转换为守护进程
//        Process::daemon();
        $this->ppid = getmypid();
        $this->enableCoroutine = $enableCoroutine;
        $this->cpuNum = swoole_cpu_num();
        //如果没有设置进程数，则设置为cpu数量
        $this->workerNum = $workerNum ?? $this->cpuNum;
    }

    /**
     * 创建进程
     */
    protected function createProcess($cpu)
    {
        $process = new Process($this->onStartCallback, false, 1, $this->enableCoroutine);
        $process->start();
        $this->pool[$process->pid] = [
            'process' => $process,
            'cpu' => $cpu
        ];
    }

    /**
     * 主进程开启
     */
    public function start()
    {
        for ($i = 0; $i < $this->workerNum; $i++) {
            $cpu = $i % $this->cpuNum;
            array_push($this->cpuPool, $cpu);
            $this->createProcess($cpu);
        }
        //保留一个定时器，防止主进程的eventloop退出
        Timer::tick(30 * 1000, function () {});
        $this->sigtermHandle();
        $this->sigusr1Handle();
        $this->sigchldHandle();
    }

    /**
     * 主进程退出信号监听
     */
    protected function sigtermHandle()
    {
        Process::signal(SIGTERM, function () {
            System::setState(0);
            foreach ($this->pool as $pid => $process) {
                Process::kill($pid, SIGTERM);
            }
        });
    }

    /**
     * 主进程重启子进程信号监听
     */
    protected function sigusr1Handle()
    {
        Process::signal(SIGUSR1, function () {
            foreach ($this->pool as $pid => $process) {
                Process::kill($pid, SIGTERM);
            }
        });
    }

    /**
     * 主进程回收子进程
     */
    protected function sigchldHandle()
    {
        Process::signal(SIGCHLD, function () {
            while ($ret = Process::wait(false)) {
                $cpu = $this->pool[$ret["pid"]]['cpu'];
                unset($this->pool[$ret["pid"]]);
                if (!System::getState()) {
                    if (empty($this->pool)) {
                        Timer::clearAll();
                    }
                } else {
                    $this->createProcess($cpu);
                }
            }
        });
    }

    /**
     * 子进程开启回调函数注册
     * @param $function
     */
    public function onStart($function)
    {
        $this->onStartCallback = function () use ($function) {
            try {
                //设置进程的cpu亲和性
                Process::setaffinity([array_shift($this->cpuPool)]);
                //最大协程数量限制
                Coroutine::set([
                    'max_coroutine' => 300000,
                ]);
                //一键协程化，针对所有类型的fd
                Runtime::enableCoroutine(SWOOLE_HOOK_ALL | SWOOLE_HOOK_CURL);
                //注册stop回调
                if (empty($this->onStopCallable)) {
                    $this->onStop(function () {});
                }
                $this->processSigtermHandle();
                call_user_func($function);
            } catch (\Exception $e) {
                var_dump("代码异常：\n" . $e->getMessage() . "\n系统将终止。");
                Process::kill($this->ppid, SIGTERM);
            }
        };
    }

    /**
     * 子进程退出回调函数注册
     * @param $function
     */
    public function onStop($function)
    {
        $this->onStopCallback = function () use ($function) {
            Runtime::enableCoroutine(false);
            Timer::clearAll();
            Event::exit();
            call_user_func($function);
        };
    }

    /**
     * 子进程退出信号监听
     */
    protected function processSigtermHandle()
    {
        Process::signal(SIGTERM, function () {
            System::setState(0);
            Timer::tick(100, function () {
                if (System::getTaskCount() <= 0) {
                    call_user_func($this->onStopCallback);
                }
            });
        });
    }
}