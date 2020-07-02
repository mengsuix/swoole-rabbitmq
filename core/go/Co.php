<?php
namespace Core\Go;

use Swoole\Coroutine;

class Co
{
    /**
     * 记录顶层协程id
     * @var array
     * @example
     * [
     *    'child id'  => 'top id',
     *    'child id'  => 'top id',
     *    'child id'  => 'top id'
     * ]
     */
    private static $mapping = [];

    /**
     * 顶层协程记录的waitGroup实例
     * @var array
     */
    private static $waitGroup = [];

    /**
     * 获取顶层协程id
     * @return int
     */
    public static function tid(): int
    {
        $cid = Coroutine::getCid();
        if (isset(self::$mapping[$cid])) {
            $tid = self::$mapping[$cid];
            unset(self::$mapping[$cid]);
            return $tid;
        } else {
            return $cid;
        }
    }

    /**
     * 获取由当前顶层协程产生的waitGroup实例
     * @return mixed
     */
    public static function waitGroup(): object
    {
        $tid = self::tid();
        if (!isset(self::$waitGroup[$tid])) {
            self::$waitGroup[$tid] = new WaitGroup();
        }
        return self::$waitGroup[$tid];
    }

    /**
     * 系统封装的协程创建方法
     * @param callable $callable
     * @param bool     $wait
     * @return int If success, return coID
     */
    public static function create(callable $callable, bool $wait = false): int
    {
        //获取顶层协程id
        $tid = self::tid();
        if ($wait) {
            //协程计数+1
            self::waitGroup()->add();
        }
        return go(function () use ($callable, $wait, $tid) {
            //记录顶层协程id
            self::$mapping[Coroutine::getCid()] = $tid;
            //执行用户逻辑
            call_user_func($callable);
            if ($wait) {
                //协程计数-1
                self::waitGroup()->done();
            }
        });
    }

    /**
     * 协程等待收包
     */
    public static function wait()
    {
        $tid = self::tid();
        if (self::waitGroup()->wait()) {
            unset(self::$waitGroup[$tid]);
        }
    }
}