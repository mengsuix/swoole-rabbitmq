<?php
if (!function_exists('pack_go')) {
    /**
     * 系统封装的go函数
     * @param callable $function
     * @param bool $wait
     */
    function pack_go(callable $function, bool $wait = false)
    {
        \Core\Go\Co::create($function, $wait);
    }
}

if (!function_exists('wait')) {
    function wait()
    {
        \Core\Go\Co::wait();
    }
}

if (!function_exists('message_handle')) {
    function message_handle(callable $function)
    {
        \Core\App\System::taskAdd();
        go(function () use ($function) {
            call_user_func($function);
            \Core\App\System::taskMinus();
        });
    }
}