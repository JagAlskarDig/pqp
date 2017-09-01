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

namespace PQP\Queues;

use Exception;
use PQP\Adapters\RedisAdapter;
use PQP\Contracts\Queue;
use Redis;

class RedisQueue implements Queue
{
    /**
     * @var Redis
     */
    protected $redis;

    /**
     * @var string
     */
    protected $redisHost;

    /**
     * @var int
     */
    protected $redisPort;

    /**
     * @var string|null
     */
    protected $redisAuth;

    /**
     * @var int
     */
    protected $redisSelectDB;

    /**
     * @var array
     */
    protected $queueKeys;

    /**
     * @var array|null
     */
    protected $current;

    /**
     * @return Redis
     */
    protected function getRedis()
    {
        if (null === $this->redis) {
            $this->redis = new RedisAdapter();
            $this->redis->connect($this->redisHost, $this->redisPort, 3.0);

            null !== $this->redisAuth && $this->redis->auth($this->redisAuth);
            $this->redisSelectDB && $this->redis->select($this->redisSelectDB);
        }

        return $this->redis;
    }

    /**
     * RedisQueue constructor.
     * @param array $queueKeys
     * @param string $host
     * @param int $port
     * @param string|null $auth
     * @param int $selectDB
     * @throws Exception
     */
    public function __construct(array $queueKeys, $host, $port = 6379, $auth = null, $selectDB = 0)
    {
        if (!extension_loaded('redis')) {
            throw new Exception('Miss redis php extension.');
        }

        $this->queueKeys = $queueKeys;
        $this->redisHost = $host;
        $this->redisPort = $port;
        $this->redisAuth = $auth;
        $this->redisSelectDB = $selectDB;
    }

    public function __destruct()
    {
        if ($this->redis) {
            try {
                $this->redis->close();
            } catch (Exception $e) {
                // no nothing
            }

            $this->redis = null;
        }
    }

    /**
     * @inheritdoc
     */
    public function dequeue($timeout = 0)
    {
        if ($ret = $this->getRedis()->brPop($this->queueKeys, $timeout)) {
            return $this->current = $ret;
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function enqueue($key, $value)
    {
        $this->getRedis()->lPush($key, $value);
    }

    /**
     * @inheritdoc
     */
    public function current()
    {
        return $this->current;
    }
}