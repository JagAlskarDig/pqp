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

class System
{
    /**
     * @return int
     */
    public static function getCpuNum()
    {
        if (is_file('/proc/cpuinfo')) {
            $content = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor\s*:\s*\d+$/m', $content, $matches);

            return count($matches[0]);
        }

        $pipe = popen('sysctl hw.ncpu', 'r');
        if (false === $pipe) {
            return 0;
        }
        $content = fgets($pipe);
        pclose($pipe);
        preg_match('/hw.ncpu:\s*(\d+)/', $content, $matches);

        return isset($matches[1]) ? (int)$matches[1] : 0;
    }


    /**
     * @param string $stdinFile
     * @param string $stdoutFile
     * @param string $stderrFile
     * @param string $dir
     * @return bool
     */
    public static function deamonize($stdinFile, $stdoutFile, $stderrFile, $dir = '/')
    {
        $forked = false;

        do {
            switch (pcntl_fork()) {
                case -1:
                    return false;
                case 0:
                    break;
                default:
                    exit;
            }

            if ($forked) {
                break;
            }

            if (-1 == posix_setsid()) {
                return false;
            }

            $forked = true;
        } while (true);

        global $STDIN, $STDOUT, $STDERR;

        fclose(STDIN);
        $STDIN = fopen($stdinFile, 'r');
        fclose(STDOUT);
        $STDOUT = fopen($stdoutFile, 'a+');
        fclose(STDERR);
        $STDERR = fopen($stderrFile, 'a+');

        if (false === chdir($dir)) {
            return false;
        }

        umask(0);

        return true;
    }
}