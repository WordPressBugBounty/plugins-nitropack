<?php

namespace NitroPack\HttpClient;

/**
 * @method void alert(string $message, array $context = [])
 * @method void critical(string $message, array $context = [])
 * @method void emergency(string $message, array $context = [])
 * @method void error(string $message, array $context = [])
 * @method void debug(string $message, array $context = [])
 * @method void info(string $message, array $context = [])
 * @method void log(string $message, array $context = [])
 * @method void notice(string $message, array $context = [])
 * @method void warning(string $message, array $context = [])
 */
class Logger
{
    public function __construct()
    {
        // Initialize the logger
    }

    public function __call($name, $arguments)
    {
        // Log the message
    }
}
