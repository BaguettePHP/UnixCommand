<?php

/**
 * UNIX-like command implementations
 *
 * @author    USAMI KENTA <tadsan@zonu.me>
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @copyright 2015 USAMI Kenta
 */

namespace Baguette\UnixCommand;

/**
 * @param  string[] $argv
 * @param  resource $stdin
 * @param  resource $stdout
 * @param  resource $stderr
 * @return int UNIX status code
 */
function cat(array $argv, $stdin = STDIN, $stdout = STDOUT, $stderr = STDERR)
{
    $files  = $argv ?: ['-'];
    $failed = false;
    $stdin_content = null;

    foreach ($files as $f) {
        $file = null;
        if ($f === '-') {
            if ($stdin_content !== null) {
                fwrite($stdout, $stdin_content);
                continue;
            }

            $file = $stdin;
        } elseif (!is_file($f)) {
            $failed = true;
            fwrite($stderr, "cat: {$f}: No such file or directory" . PHP_EOL);
            continue;
        } else {
            $file = fopen($f, 'r');
        }

        while (!feof($file)) {
            $data = fread($file, 8192);
            if ($data === false) {
                break;
            }
            if ($f === '-') {
                $stdin_content .= $data;
            }

            fwrite($stdout, $data);
        }
        fclose($file);
    }

    return $failed ? 1 : 0;
}

/**
 * @param  string[] $argv
 * @param  resource $stdin
 * @param  resource $stdout
 * @param  resource $stderr
 * @return int UNIX status code
 */
function cp(array $argv, $stdin = STDIN, $stdout = STDOUT, $stderr = STDERR)
{
    $failed = false;

    $len = count($argv);
    if ($len === 0) {
        fwrite($stderr, 'cp: missing file operand' . PHP_EOL);
        return 1;
    }

    $dest = array_pop($argv);
    $is_dir = is_dir($dest);

    if ($len === 1) {
        $message = "cp: missing destination file operand after '{$dest}'";
        fwrite($stderr, $message . PHP_EOL);
        return 1;
    }

    if (count($argv) > 1 && !$is_dir) {
        $message = "cp: target '{$dest}' is not a directory";
        fwrite($stderr, $message . PHP_EOL);
        return 1;
    }

    if ($is_dir) {
        $pathinfo = pathinfo($dest);
        $dest = $pathinfo['dirname'] . DIRECTORY_SEPARATOR . $pathinfo['basename'];
    }

    foreach ($argv as $f) {
        if (is_dir($f)) {
            $message = "cp: omitting directory '{$f}'";
        } elseif (!is_file($f)) {
            $message = "cp: cannot stat '{$f}': No such file or directory";
        } elseif ($dest == $f) {
            $message = "cp: '{$f}' and '{$f}' are the same file";
        }

        if ($is_dir) {
            $new_file = $dest . DIRECTORY_SEPARATOR . basename($f);
        } else {
            $new_file = $dest;
        }

        if (!is_file($new_file)) {
            touch($new_file);
        } elseif (!is_writable($dest)) {
            $message = "cp: cannot create regular file '{$new_file}': Permission denied";
        }

        if (isset($message)) {
            fwrite($stderr, $message . PHP_EOL);
            $failed = true;
            continue;
        }

        try {
            copy($f, $new_file);
        } catch (\ErrorException $e) {
            $message = preg_replace('@\Acopy@', 'cp', $e->getMessage());
            fwrite($stderr, $message . PHP_EOL);
            $failed = true;
            continue;
        }
    }

    return $failed ? 1 : 0;
}

/**
 * @param  string[] $argv
 * @param  resource $stdin
 * @param  resource $stdout
 * @param  resource $stderr
 * @return int UNIX status code
 */
function echo_(array $argv, $stdin = STDIN, $stdout = STDOUT, $stderr = STDERR)
{
    static $tr_table = [
        '\\\\' => '\\',
        '\a' => "\a",
        '\b' => "\b",
        '\c' => "\c",
        '\d' => "\d",
        '\e' => "\e",
        '\f' => "\f",
        '\g' => "\g",
        '\h' => "\h",
        '\i' => "\i",
        '\j' => "\j",
        '\k' => "\k",
        '\l' => "\l",
        '\m' => "\m",
        '\n' => "\n",
        '\o' => "\o",
        '\p' => "\p",
        '\q' => "\q",
        '\r' => "\r",
        '\s' => "\s",
        '\t' => "\t",
        '\u' => "\u",
        '\v' => "\v",
        '\w' => "\w",
        '\x' => "\x",
        '\y' => "\y",
        '\z' => "\z",
    ];

    $last_newline = true;
    $first = array_shift($argv);

    if ($first === '-n') {
        $last_newline = false;
        $first = array_shift($argv);
    }


    $printline = function ($l) use ($tr_table, $stdout) {
        $terminate = false;
        $line = strtr($l, $tr_table);
        if (strpos($line, "\c") !== false) {
            $terminate = true;
            list($line, $_) = explode("\c", $line, 2);
        }
        fwrite($stdout, $line);

        return $terminate;
    };

    if ($printline($first)) {
        return 0;
    }

    while ($argv) {
        fwrite($stdout, ' ');

        if ($printline(array_shift($argv))) {
            return 0;
        }
    }

    if ($last_newline) {
        fwrite($stdout, PHP_EOL);
    }

    return 0;
}

/**
 * @param  string[] $argv
 * @param  resource $stdin
 * @param  resource $stdout
 * @param  resource $stderr
 * @return int UNIX status code
 */
function printf(array $argv, $stdin = STDIN, $stdout = STDOUT, $stderr = STDERR)
{
    $format = array_shift($argv);

    if ($format === null) {
        fwrite($stderr, 'printf: not enough arguments' . PHP_EOL);
        return 1;
    }

    $format = strtr($format, [
        '\\\\' => '\\',
        '\a' => "\a",
        '\b' => "\b",
        '\c' => "\c",
        '\d' => "\d",
        '\e' => "\e",
        '\f' => "\f",
        '\g' => "\g",
        '\h' => "\h",
        '\i' => "\i",
        '\j' => "\j",
        '\k' => "\k",
        '\l' => "\l",
        '\m' => "\m",
        '\n' => "\n",
        '\o' => "\o",
        '\p' => "\p",
        '\q' => "\q",
        '\r' => "\r",
        '\s' => "\s",
        '\t' => "\t",
        '\u' => "\u",
        '\v' => "\v",
        '\w' => "\w",
        '\x' => "\x",
        '\y' => "\y",
        '\z' => "\z",
    ]);

    \vfprintf($stdin, $format, $argv);

    return 0;
}

/**
 * @param  string[] $argv
 * @param  resource $stdin
 * @param  resource $stdout
 * @param  resource $stderr
 * @return int UNIX status code
 */
function pwd(array $argv, $stdin = STDIN, $stdout = STDOUT, $stderr = STDERR)
{
    $pwd = getenv('PWD');
    if (empty($pwd)) {
        return 1;
    }

    fwrite($stdout, $pwd . PHP_EOL);
    return 0;
}

/**
 * @param  string[] $argv
 * @param  resource $stdin
 * @param  resource $stdout
 * @param  resource $stderr
 * @return int UNIX status code
 */
function seq(array $argv, $stdin = STDIN, $stdout = STDOUT, $stderr = STDERR)
{
    $len = count($argv);

    if ($len === 1) {
        $start = 1;
        $step  = 1;
        $last  = array_shift($argv);
    } elseif ($len === 2) {
        $start = array_shift($argv);
        $last  = array_shift($argv);
        $step  = ($start >= $last) ? -1 : 1;
    } elseif ($len === 3) {
        $start = array_shift($argv);
        $step  = array_shift($argv);
        $last  = array_shift($argv);
    } else {
        return 1;
    }

    if ($step == 0) {
        $message = 'seq: zero decrement';
    } elseif (($start < $last) && ($step < 0)) {
        $message = 'seq: needs positive increment';
    } elseif (($start > $last) && ($step > 0)) {
        $message = 'seq: needs negative decrement';
    }

    if (isset($message)) {
        fwrite($stderr, $message . PHP_EOL);
        return 1;
    }

    foreach (range($start, $last, $step) as $n) {
        fwrite($stdout, $n . PHP_EOL);
    }

    return 0;
}

/**
 * @param  string[] $argv
 * @param  resource $stdin
 * @param  resource $stdout
 * @param  resource $stderr
 * @return int UNIX status code
 */
function whoami(array $argv, $stdin = STDIN, $stdout = STDOUT, $stderr = STDERR)
{
    $my = posix_getpwuid(posix_geteuid());
    if (empty($my) || !isset($my['name'])) {
        return 1;
    }

    fwrite($stdout, $my['name'] . PHP_EOL);
    return 0;
}
