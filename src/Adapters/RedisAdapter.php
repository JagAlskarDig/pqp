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

namespace PQP\Adapters;

use Redis;
use RedisException;

class RedisAdapter
{
    /**
     * @var Redis
     */
    protected $redis;

    protected $context = array();

    /**
     * @var array
     */
    protected $connectArguments;

    protected $lastDB = 0;

    /**
     * RedisAdapter constructor.
     */
    public function __construct()
    {
        $this->redis = new Redis();
    }

    /**
     * @param string $name
     * @param array|null $arguments
     * @return mixed
     * @throws RedisException
     */
    public function __call($name, $arguments)
    {
        $this->setupContext($name, $arguments);

        try {
            return call_user_func_array(array($this->redis, $name), $arguments);
        } catch (RedisException $e) {
            return $this->restoreFromContext($e, $name, $arguments);
        }
    }

    /**
     * @param string $name
     * @param array|null $arguments
     */
    protected function setupContext($name, $arguments)
    {
        if ('connect' == $name || 'open' == $name || 'pconnect' == $name || 'popen' == $name) {
            $this->context['connect'] = array($name, $arguments);
        } elseif ('auth' == $name) {
            $this->context['auth'] = $arguments;
        } elseif ('select' == $name) {
            $this->context['select'] = $arguments;
        }
    }

    /**
     * @param RedisException $e
     * @param string $name
     * @param array|null $arguments
     * @return mixed
     * @throws RedisException
     */
    protected function restoreFromContext(RedisException $e, $name, $arguments)
    {
        if ('connect' == $name || 'open' == $name || 'pconnect' == $name || 'popen' == $name) {
            throw $e;
        }

        if (0 !== strcasecmp(trim($e->getMessage()), 'Redis server went away')) {
            throw $e;
        }

        if (empty($this->context['connect'])) {
            throw $e;
        }

        list($connectName, $connectArguments) = $this->context['connect'];
        call_user_func_array(array($this->redis, $connectName), $connectArguments);

        if (isset($this->context['auth'])) {
            call_user_func_array(array($this->redis, 'auth'), $this->context['auth']);
        }

        if (isset($this->context['select'])) {
            call_user_func_array(array($this->redis, 'select'), $this->context['select']);
        }

        return call_user_func_array(array($this->redis, $name), $arguments);
    }
}