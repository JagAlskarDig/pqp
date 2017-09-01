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

namespace PQP;

use ErrorException;
use Exception;

final class Loader
{
    /**
     * @var self
     */
    protected static $instance;

    /**
     * @var string
     */
    protected $path;

    /**
     * @return self
     */
    public static function init()
    {
        null === self::$instance && self::$instance = new self();

        return self::$instance;
    }

    /**
     * Loader constructor.
     */
    public function __construct()
    {
        $this->checkEnvironment();

        set_error_handler(function ($code, $msg, $file, $line) {
            throw new ErrorException($msg, $code, $code, $file, $line);
        }, error_reporting());

        $this->path = __DIR__;
        spl_autoload_register(array($this, 'load'));
    }

    /**
     * @param string $className
     */
    protected function load($className)
    {
        $className = strtr($className, '\\', DIRECTORY_SEPARATOR);
        if (0 === strncmp('PQP/', $className, 4)) {
            require $this->path . substr($className, 3) . '.php';
        }
    }

    /**
     * @throws Exception
     */
    protected function checkEnvironment()
    {
        if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50306) {
            throw new Exception('PHP version must >= 5.3.6.');
        }

        if ('cli' !== PHP_SAPI) {
            throw new Exception('PQP must run in cli mode.');
        }

        if (!extension_loaded('pcntl')) {
            throw new Exception('Miss pcntl php extension.');
        }

        if (!extension_loaded('posix')) {
            throw new Exception('Miss posix php extension.');
        }
    }
}
