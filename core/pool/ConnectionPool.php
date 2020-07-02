<?php
namespace Core\Pool;

use Swoole\Coroutine\Channel;
use Swoole\Timer;

abstract class ConnectionPool implements PoolInterface
{
    private $channel;

    /**
     * 定时器
     * @var
     */
    private $timer;

    /**
     * 计数
     * @var int
     */
    private $count = 0;

    /**
     * 最小连接数
     * @var int
     */
    protected $minActive = 5;

    /**
     * 最大连接数
     * @var int
     */
    protected $maxActive = 10;

    /**
     * 最大消费者等待数量
     * @var int
     */
    protected $maxWait = 0;

    /**
     * 最大获取连接超时时间
     * @var int
     */
    protected $maxWaitTime = 0;

    /**
     * 最大关闭连接超时时间
     * @var int
     */
    protected $maxCloseTime = 3;

    /**
     * 最大空闲时间
     * @var int
     */
    protected $maxIdleTime = 60;

    /**
     * 空闲检测时间
     * @var int
     */
    protected $checkTime =  30 * 1000;

    public function __construct()
    {
        $this->channel = new Channel($this->maxActive);
//        $this->timedDetection();
    }

    /**
     * 从连接池获取连接
     * @return mixed
     * @throws \Exception
     */
    public function getConnection()
    {
        //如果没有到达最小连接数则直接创建连接
        if ($this->count < $this->minActive) {
            return $this->getCreateConnection();
        }
        //从通道中返回连接
        if (!$this->channel->isEmpty()) {
            return $this->unpack($this->channel->pop());
        }
        //如果通道中没有可用连接则创建连接
        if ($this->count < $this->maxActive) {
            return $this->getCreateConnection();
        }
        //当通道的消费者大于默认消费者时抛出错误
        $stats = $this->channel->stats();
        if ($this->maxWait > 0 && $stats['consumer_num'] >= $this->maxWait) {
            throw new \Exception(
                sprintf('Channel consumer is full, maxActive=%d, maxWait=%d, currentCount=%d',
                    $this->maxActive, $this->maxWaitTime, $this->count)
            );
        }
        //阻塞获取通道中的连接
        $connection = $this->unpack($this->channel->pop($this->maxWaitTime));
        if ($connection === false) {
            throw new \Exception(
                sprintf('Channel pop timeout by %fs', $this->maxWaitTime)
            );
        }
        return $connection;
    }

    /**
     * 从子类获取创建连接
     * @return mixed
     * @throws \Exception
     */
    protected function getCreateConnection()
    {
        $this->count++;
        try {
            return $this->createConnection();
        } catch (\Exception $e) {
            $this->count--;
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * 将连接放入连接池
     * @param $connection
     */
    public function releaseConnection($connection)
    {
        $stats = $this->channel->stats();
        if ($stats["queue_num"] < $this->maxActive) {
            $this->channel->push($this->pack($connection));
        }
    }

    /**
     * 关闭连接池
     * @return int
     */
    public function close()
    {
        Timer::clear($this->timer);
        $i = 0;
        if (empty($this->channel)) {
            $this->channel->close();
            return $i;
        }
        for (; $i < $this->count; $i++) {
            $connection = $this->channel->pop($this->maxCloseTime);
            if ($connection === false) {
                break;
            }
            $connection->close();
        }
        $this->channel->close();
        return $this->count;
    }

    /**
     * 打包连接
     * @param $connection
     * @return array
     */
    private function pack($connection)
    {
        return [
            "connection" => $connection,
            "last_time" => time()
        ];
    }

    /**
     * 解包连接
     * @param $package
     * @return mixed
     */
    private function unpack($package)
    {
        return $package["connection"];
    }

    /**
     * 空闲连接定时检测
     */
    private function timedDetection()
    {
        $this->timer = Timer::tick($this->checkTime, function () {
            if (!$this->channel->isEmpty()) {
                for ($i = 0; $i < $this->channel->length(); $i++) {
                    $package = $this->channel->pop();
                    if (time() - $package["last_time"] > $this->maxIdleTime) {
                        continue;
                    } else {
                        $this->channel->push($package);
                    }
                }
            }
        });
    }
}