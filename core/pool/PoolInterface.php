<?php
namespace Core\Pool;

interface PoolInterface
{
    /**
     * 创建连接
     * @return mixed
     */
    public function createConnection();
}