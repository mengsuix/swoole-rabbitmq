<?php
namespace Core\App;

class System
{
    protected static $state = 1;

    protected static $count = 0;

    public static function getState()
    {
        return self::$state;
    }

    public static function setState($state)
    {
        self::$state = $state;
    }

    public static function taskAdd()
    {
        self::$count++;
    }

    public static function taskMinus()
    {
        self::$count--;
    }

    public static function getTaskCount()
    {
        return self::$count;
    }
}