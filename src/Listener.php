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
use PQP\Common\Config;
use PQP\Common\Logger;
use PQP\Common\System;
use PQP\Contracts\Observer;
use PQP\Contracts\Process;
use PQP\Contracts\Queue;

class Listener extends Process
{
    /**
     * @var array
     */
    protected $config = array(
        'name' => 'PQP Queue Processor',
        'deamonize' => false,
        'debug' => true,
        'log' => '/dev/null',
        'user' => null,
        'group' => null,
        'workerNum' => 4,
        'workerRequestNum' => 1000,
    );

    /**
     * @var int
     */
    protected $workerUserId;

    /**
     * @var int
     */
    protected $workerGroupId;

    /**
     * @var int
     */
    protected $pid;

    /**
     * @var array
     */
    protected $workers = array();

    /**
     * @var array
     */
    protected $caredSignals = array(SIGINT, SIGTERM, SIGHUP, SIGQUIT, SIGABRT, SIGALRM, SIGUSR1, SIGUSR2, SIGCHLD);

    /**
     * @var bool
     */
    protected $deamonize;

    /**
     * @var Queue
     */
    protected $queue;

    /**
     * @var array
     */
    protected $observers = array();

    /**
     * @var string
     */
    protected $stoppingScreen;

    /**
     * Listener constructor.
     * @param string $configFile
     * @throws Exception
     */
    public function __construct($configFile = null)
    {
        $this->checkEnvironment();

        set_error_handler(function ($code, $msg, $file, $line) {
            throw new ErrorException($msg, $code, $code, $file, $line);
        }, error_reporting());

        $log = 'php://stdout';
        $this->stoppingScreen = str_repeat("\n<<<<<<<< Stopping... For data safe reason, please wait! >>>>>>>>", 80);

        $cpuNum = System::getCpuNum();
        $cpuNum > 0 && $this->config['workerNum'] = $cpuNum * 2;

        $this->config = new Config($this->config);
        is_file($configFile) && $this->config->loadFromINIFile($configFile);

        if ($this->config['user']) {
            $this->setWorkerUser($this->config['user']);
        }

        if ($this->config['group']) {
            $this->setWorkerGroup($this->config['group']);
        }

        if ($this->config['deamonize']) {
            $log = $this->config['log'];
        }

        Logger::init($log, $this->config['debug'] ? Logger::LOG_DEBUG : Logger::LOG_WARNING);
    }

    public function __destruct()
    {
        $this->queue = null;
        $this->observers = null;
        $this->workers = null;
    }

    /**
     * @param Queue $queue
     */
    public function listen(Queue $queue)
    {
        $this->running = true;
        $this->queue = $queue;
        $log = $this->config['log'];
        $this->config['deamonize'] && System::deamonize('/dev/null', $log, $log);

        $this->setProcessTitle();
        $this->pid = posix_getpid();
        Logger::debug('Listener start: ', $this->pid);
        $this->installSignals();
        $this->initWorkers();

        while ($this->running) {
            $this->dispatchSignals();
        }
    }

    /**
     * @return int
     */
    public function getWorkerNum()
    {
        return $this->config['workerNum'];
    }

    /**
     * @param int $workerNum
     */
    public function setWorkerNum($workerNum)
    {
        $this->config['workerNum'] = $workerNum;
    }

    /**
     * @param Observer $observer
     */
    public function attach(Observer $observer)
    {
        $this->observers[] = $observer;
    }

    protected function blankSignal()
    {
        usleep(500000);
    }

    protected function withSignals()
    {
        do {
            $signal = $this->getSignalQueue()->dequeue();

            switch ($signal) {
                case SIGINT:
                case SIGTERM:
                case SIGQUIT:
                case SIGABRT:
                case SIGSTOP:
                    Logger::info($this->stoppingScreen);
                    $this->stop($signal);

                    return;
                case SIGHUP:
                case SIGUSR1:
                case SIGUSR2:
                    $this->stopWorkers($signal);
                    break;
                case SIGCHLD:
                    $this->restartWorkers();
                    break;
            }
        } while (!$this->getSignalQueue()->isEmpty());
    }

    /**
     * @param int $signal
     */
    protected function stop($signal)
    {
        $this->running = false;
        $this->restoreSignals();
        $this->stopWorkers($signal);

        while (0 < $pid = pcntl_wait($status)) {
            Logger::debug('Process pid: ', $pid, ' stopped.');
            unset($this->workers[$pid]);
        }
    }

    protected function initWorkers()
    {
        for ($i = 0; $i < $this->config['workerNum']; $i++) {
            $this->startWorker($i);
        }
    }

    /**
     * @param int $id
     */
    protected function startWorker($id)
    {
        switch ($pid = pcntl_fork()) {
            case 0:
                $this->restoreSignals();
                $this->setProcessTitle(false);

                $this->workerUserId && posix_setuid($this->workerUserId);
                $this->workerGroupId && posix_setgid($this->workerGroupId);

                $worker = new Worker($id, $this->pid, $this->queue, $this->config['workerRequestNum']);
                foreach ($this->observers as $observer) {
                    $worker->attach($observer);
                }

                $worker->run();
                exit;
            case -1:
                Logger::emergency('Fork process failed!');
                exit(-1);
            default:
                Logger::debug('Worker start: ', $pid);
                $this->workers[$pid] = $id;
                break;
        }
    }

    /**
     * @param int $signal
     */
    protected function stopWorkers($signal)
    {
        foreach (array_keys($this->workers) as $pid) {
            posix_kill($pid, $signal);
        }
    }

    protected function restartWorkers()
    {
        $idSet = array();
        while (0 < $pid = pcntl_wait($status, WNOHANG | WUNTRACED)) {
            $idSet[] = $this->workers[$pid];
            unset($this->workers[$pid]);
        }

        foreach ($idSet as $id) {
            $this->startWorker($id);
        }
    }

    protected function setWorkerUser($userName)
    {
        if (false === $userInfo = posix_getpwnam($userName)) {
            return;
        }

        $this->workerUserId = $userInfo['uid'];
    }

    protected function setWorkerGroup($groupName)
    {
        if (false === $groupInfo = posix_getgrnam($groupName)) {
            return;
        }

        $this->workerGroupId = $groupInfo['gid'];
    }

    protected function setProcessTitle($master = true)
    {
        if (!function_exists('cli_set_process_title')) {
            return;
        }

        try {
            cli_set_process_title($this->config['name'] . ': ' . ($master ? 'listener' : 'worker') . ' process');
        } catch (ErrorException $e) {
            Logger::warning('Set process filed: ', $e->getMessage());
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
