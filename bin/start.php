<?php
require "../vendor/autoload.php";

function rabbitMessageHandle($message)
{
    message_handle(function () use ($message) {
        var_dump($message->body);
        pack_go(function () use ($message)  {
            $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
            if ($message->body === 'quit') {
                $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
            }
        }, true);
        wait();
    });
}

$app = new \Core\App\Application(true, 5);

$app->onStart(function () use ($app) {
    $rabbitPool = new \App\Pool\RabbitMQPool();
    $connection = $rabbitPool->createChannelConnection();
    $app->onStop(function () use ($connection, $rabbitPool) {
        $connection->close();
        $rabbitPool->getConnection()->close();
    });
    while (\Core\App\System::getState()) {
        $connection->wait();
    }
});
$app->start();