<?php

/**
 * UNIX-like command implementations
 *
 * @author    USAMI KENTA <tadsan@zonu.me>
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @copyright 2015 USAMI Kenta
 */

namespace zonuexe\UnixCommand;

/**
 * @param  string[] $argv
 * @return int UNIX status code
 */
function cat(array $argv)
{
    $files  = $argv ?: ['-'];
    $failed = false;
    $stdin  = null;

    foreach ($files as $f) {
        $file = null;
        if ($f === '-') {
            if ($stdin !== null) {
                echo $stdin;
                continue;
            }

            $file = STDIN;
        } elseif (!is_file($f)) {
            $failed = true;
            fwrite(STDERR, "cat: ${f}: No such file or directory" . PHP_EOL);
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
                $stdin .= $data;
            }

            echo $data;
        }
        fclose($file);
    }

    return $failed ? 1 : 0;
}

/**
 * @param  string[] $argv
 * @return int UNIX status code
 */
function cp(array $argv)
{
    $failed = false;

    $len = count($argv);
    if ($len === 0) {
        fwrite(STDERR, 'cp: missing file operand' . PHP_EOL);
        return 1;
    }

    $dest = array_pop($argv);
    $is_dir = is_dir($dest);

    if ($len === 1) {
        $message = "cp: missing destination file operand after '{$dest}'";
        fwrite(STDERR, $message . PHP_EOL);
        return 1;
    }

    if (count($argv) > 1 && !$is_dir) {
        $message = "cp: target '{$dest}' is not a directory";
        fwrite(STDERR, $message . PHP_EOL);
        return 1;
    }

    if ($is_dir) {
        $pathinfo = pathinfo($dest);
        $dest = $pathinfo['dirname'] . '/' . $pathinfo['basename'];
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
            $new_file = $dest . '/' . basename($f);
        } else {
            $new_file = $dest;
        }

        if (!is_file($new_file)) {
            touch($new_file);
        } elseif (!is_writable($dest)) {
            $message = "cp: cannot create regular file '{$new_file}': Permission denied";
        }

        if (isset($message)) {
            fwrite(STDERR, $message . PHP_EOL);
            $failed = true;
            continue;
        }

        try {
            copy($f, $new_file);
        } catch (\ErrorException $e) {
            $message = preg_replace('@\Acopy@', 'cp', $e->getMessage());
            fwrite(STDERR, $message . PHP_EOL);
            $failed = true;
            continue;
        }
    }

    return $failed ? 1 : 0;
}

/**
 * @param  string[] $argv
 * @return int UNIX status code
 */
function _echo(array $argv)
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


    $printline = function ($l) use ($tr_table) {
        $terminate = false;
        $line = strtr($l, $tr_table);
        if (strpos($line, "\c") !== false) {
            $terminate = true;
            list($line, $_) = explode("\c", $line, 2);
        }
        echo $line;

        return $terminate;
    };

    if ($printline($first)) {
        return 0;
    }

    while ($argv) {
        echo ' ';

        if ($printline(array_shift($argv))) {
            return 0;
        }
    }

    if ($last_newline) {
        echo PHP_EOL;
    }

    return 0;
}

/**
 * @param  string[] $argv
 * @return int UNIX status code
 */
function printf(array $argv)
{
    $format = array_shift($argv);

    if ($format === null || strlen($format) < 1) {
        fwrite(STDERR, 'printf: not enough arguments' . PHP_EOL);
        return 1;
    }

    array_unshift($argv, strtr($format, [
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
    ]));

    \vfprintf(STDIN, $format, $argv);

    return 0;
}

/**
 * @param  string[] $argv
 * @return int UNIX status code
 */
function pwd(array $argv)
{
    $pwd = getenv('PWD');
    if (empty($pwd)) {
        return 1;
    }

    echo $pwd, PHP_EOL;
    return 0;
}

/**
 * @param  string[] $argv
 * @return int UNIX status code
 */
function seq(array $argv)
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
        fwrite(STDERR, $message . PHP_EOL);
        return 1;
    }

    foreach (range($start, $last, $step) as $n) {
        echo $n, PHP_EOL;
    }

    return 0;
}

/**
 * @param  string[] $argv
 * @return int UNIX status code
 */
function whoami(array $argv)
{
    $my = posix_getpwuid(posix_geteuid());
    if (empty($my) || !isset($my['name'])) {
        return 1;
    }

    echo $my['name'], PHP_EOL;
    return 0;
}
