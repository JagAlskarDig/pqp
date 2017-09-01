<?php
/**
 * This file is part of the PQP package.
 * Copyright (C) 2017 pengzhile <pengzhile@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace PQP\Common;

class Logger
{
    const LOG_EMERGENCY = LOG_EMERG;
    const LOG_ALERT = LOG_ALERT;
    const LOG_CRITICAL = LOG_CRIT;
    const LOG_ERROR = LOG_ERR;
    const LOG_WARNING = LOG_WARNING;
    const LOG_NOTICE = LOG_NOTICE;
    const LOG_INFO = LOG_INFO;
    const LOG_DEBUG = LOG_DEBUG;

    /**
     * @var resource
     */
    protected $logFileHandle;

    /**
     * @var int
     */
    protected $logLevel;

    /**
     * @var array
     */
    protected $logTypeMap = array(
        self::LOG_EMERGENCY => 'EMERGENCY',
        self::LOG_ALERT => 'ALERT',
        self::LOG_CRITICAL => 'CRITICAL',
        self::LOG_ERROR => 'ERROR',
        self::LOG_WARNING => 'WARNING',
        self::LOG_NOTICE => 'NOTICE',
        self::LOG_INFO => 'INFO',
        self::LOG_DEBUG => 'DEBUG',
    );

    /**
     * @var int
     */
    protected $pid;

    /**
     * @var self
     */
    protected static $logger;

    public static function init($logFile, $logLevel = self::LOG_WARNING)
    {
        if (null === self::$logger) {
            self::$logger = new self($logFile, $logLevel);
        }
    }

    protected static function getLogger()
    {
        return self::$logger;
    }

    protected function __construct($logFile, $logLevel)
    {
        $this->logLevel = $logLevel;
        $this->logFileHandle = fopen($logFile, 'a+');
        $this->pid = posix_getpid();
    }

    public function __destruct()
    {
        if ($this->logFileHandle) {
            fclose($this->logFileHandle);
        }
    }

    public static function debug()
    {
        self::getLogger()->log(self::LOG_DEBUG, func_get_args());
    }

    public static function info()
    {
        self::getLogger()->log(self::LOG_INFO, func_get_args());
    }

    public static function notice()
    {
        self::getLogger()->log(self::LOG_NOTICE, func_get_args());
    }

    public static function warning()
    {
        self::getLogger()->log(self::LOG_WARNING, func_get_args());
    }

    public static function error()
    {
        self::getLogger()->log(self::LOG_ERROR, func_get_args());
    }

    public static function critical()
    {
        self::getLogger()->log(self::LOG_CRITICAL, func_get_args());
    }

    public static function alert()
    {
        self::getLogger()->log(self::LOG_ALERT, func_get_args());
    }

    public static function emergency()
    {
        self::getLogger()->log(self::LOG_EMERGENCY, func_get_args());
    }

    protected function log($type, array $params)
    {
        if ($type > $this->logLevel) {
            return;
        }

        $typeStr = $this->logTypeMap[$type];

        $traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $trace = array_pop($traces);
        $callerType = isset($trace['type']) ? $trace['type'] : '';
        $callerClass = isset($trace['class']) ? $trace['class'] : '';
        $caller = $callerClass . $callerType . $trace['function'] . '()';

        foreach ($params as $key => $param) {
            if (null === $param) {
                $params[$key] = 'null';
                continue;
            }
            if (true === $param) {
                $params[$key] = 'true';
                continue;
            }
            if (false === $param) {
                $params[$key] = 'false';
                continue;
            }
            if (!is_scalar($param)) {
                $params[$key] = print_r($param, true);
            }
        }
        $str = date('Y-m-d H:i:s') . ' [' . $typeStr . '] ' . $this->pid . ' ' . $caller . ': ' . implode($params) . PHP_EOL;

        $this->write($str);
    }

    protected function write($str)
    {
        flock($this->logFileHandle, LOCK_EX);
        fwrite($this->logFileHandle, $str);
        flock($this->logFileHandle, LOCK_UN);
    }
}
