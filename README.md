# PQP: A high performance multi-process queue listener

### Requirement

* php >= 5.3.6
* pcntl php extension
* posix php extension
* redis php extension (if you want to use the RedisQueue)
* linux/unix operating system
* use php cli

### Sample

```php
<?php
//require '/path/to/PQP/autoload.php';    // native autoloader
require '/path/to/vendor/autoload.php';    // composer autoloader

use PQP\Contracts\Observer;
use PQP\Contracts\Queue;
use PQP\Listener;
use PQP\Queues\RedisQueue;

class Processor1 implements Observer
{
    /**
     * @param Queue $queue Current queue
     * @param int $workerId From 0 to $config['workerNum'] - 1
     */
    public function update(Queue $queue, $workerId)
    {
        list($key, $content) = $queue->current();
        echo 'key: ', $key, '; content: ', $content, '; from: ', $workerId, PHP_EOL;
    }
}

// config.ini file copied from /path/to/PQP/config.ini.example
$listener = new Listener('config.ini');

// more processors [chain]
$listener->attach(new Processor1());

// RedisQueue require php redis extension
$listener->listen(new RedisQueue(array('queueKey1', 'queueKey2'), '127.0.0.1'));

// after listen
// send SIGHUP(kill -1 <listener pid>) to listener process will restart all workers.
// send SIGTERM(kill <listener pid>) to listener process will stop PQP.
// send SIGINT(Ctrl+C or kill -2 <listener pid>) to listener process will stop PQP.
```
