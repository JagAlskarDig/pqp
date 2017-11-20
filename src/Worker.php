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

use Exception;
use PQP\Common\Logger;
use PQP\Contracts\Observer;
use PQP\Contracts\Process;
use PQP\Contracts\Queue;

class Worker extends Process
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var int
     */
    protected $parentPID;

    /**
     * @var bool
     */
    protected $running = false;

    /**
     * @var int
     */
    protected $requestNum;

    /**
     * @var array
     */
    protected $caredSignals = array(SIGINT, SIGTERM, SIGHUP, SIGQUIT, SIGABRT, SIGALRM, SIGUSR1, SIGUSR2);

    /**
     * @var Queue
     */
    protected $queue;

    /**
     * @var array
     */
    protected $observers = array();

    /**
     * Worker constructor.
     * @param int $id
     * @param int $parentPID
     * @param Queue $queue
     * @param int $requestNum
     */
    public function __construct($id, $parentPID, Queue $queue, $requestNum)
    {
        $this->id = $id;
        $this->parentPID = $parentPID;
        $this->queue = $queue;
        $this->requestNum = $requestNum;
    }

    public function __destruct()
    {
        $this->queue = null;
        $this->observers = null;
    }

    public function run()
    {
        $this->running = true;
        $this->installSignals();

        $count = 0;
        while ($this->running) {
            try {
                $value = $this->queue->dequeue(1);

                if (null !== $value) {
                    $this->notify();

                    if (++$count >= $this->requestNum) {
                        $this->stop();
                    }
                }
            } catch (Exception $e) {
                Logger::error($e->getMessage(), ' (', $e->getFile(), ':', $e->getLine(), ')');
            }

            $this->dispatchSignals();
        }
    }

    /**
     * @param Observer $observer
     */
    public function attach(Observer $observer)
    {
        $this->observers[] = $observer;
    }

    public function dispatchSignals()
    {
        if ($this->parentPID == posix_getppid()) {
            parent::dispatchSignals();
        } else { // 爹都死了，你还在蹦哒个屁
            $this->stop();
        }
    }

    protected function withSignals()
    {
        $this->stop();
    }

    protected function stop()
    {
        $this->running = false;
        $this->restoreSignals();
    }

    protected function notify()
    {
        /**
         * @var Observer $observer
         */
        foreach ($this->observers as $observer) {
            $observer->update($this->queue, $this->id);
        }
    }
}
