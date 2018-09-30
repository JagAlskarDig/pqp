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

final class Loader
{
    const DS = DIRECTORY_SEPARATOR;

    /**
     * @var self
     */
    protected static $instance;

    /**
     * @var string[] array
     */
    protected $vendors = array('PQP/' => __DIR__);

    /**
     * @var array
     */
    protected $classMap = array();

    /**
     * @var array
     */
    protected $staticFiles = array();

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
        spl_autoload_register(array($this, 'load'));
    }

    /**
     * @param string $namespace
     * @param string $baseDir
     */
    public static function registerPsr4($namespace, $baseDir)
    {
        $namespace = strtr(trim($namespace, " \t\n\r\0\x0B\\"), '\\', self::DS) . self::DS;
        self::init()->vendors[$namespace] = realpath($baseDir);
    }

    /**
     * @param string $className
     * @param string $filePath
     */
    public static function mapClass($className, $filePath)
    {
        self::init()->classMap[trim($className)] = realpath($filePath);
    }

    /**
     * @param array $classes
     */
    public static function mapClasses(array $classes)
    {
        $classMap = self::init()->classMap;
        self::init()->classMap = $classes + $classMap;
    }

    /**
     * @param string $filePath
     */
    public static function loadStatic($filePath)
    {
        $filePath = realpath($filePath);

        $instance = self::init();
        if (!isset($instance->staticFiles[$filePath])) {
            $instance->staticFiles[$filePath] = true;

            require $filePath;
        }
    }

    /**
     * @param string $className
     */
    protected function load($className)
    {
        if (isset($this->classMap[$className])) {
            require $this->classMap[$className];
            unset($this->classMap[$className]);

            return;
        }

        $className = strtr($className, '\\', $this::DS);
        foreach ($this->vendors as $name => $path) {
            $nameLen = strlen($name);

            if (0 === strncmp($name, $className, $nameLen)) {
                require $path . substr($className, $nameLen - 1) . '.php';

                return;
            }
        }
    }
}
