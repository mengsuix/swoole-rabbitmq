<?php
namespace App\Pool;

class RabbitMQPool
{
    protected $connection;

    protected $param = [
        "exchange" => "router",
        "queue" => "msgs2",
        "consumerTag" => "consumer",
    ];

    public function __construct()
    {
        $this->connection = new \PhpAmqpLib\Connection\AMQPStreamConnection("192.168.10.1", 5672, "guest", "guest", "/");
    }

    public function createChannelConnection()
    {
        $channel = $this->connection->channel();
        $channel->queue_declare($this->param["queue"], false, true, false, false);
        $channel->exchange_declare($this->param["exchange"], \PhpAmqpLib\Exchange\AMQPExchangeType::DIRECT, false, true, false);
        $channel->queue_bind($this->param["queue"], $this->param["exchange"]);
        $channel->basic_consume($this->param["queue"], $this->param["consumerTag"], false, false, false, false, "rabbitMessageHandle");
        return $channel;
    }

    public function getParam()
    {
        return $this->param;
    }

    public function getConnection()
    {
        return $this->connection;
    }
}