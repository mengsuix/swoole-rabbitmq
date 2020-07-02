<?php

$app = new \Core\App\Application(true);

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