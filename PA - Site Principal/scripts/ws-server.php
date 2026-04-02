<?php
// WebSocket server for the chatting system in real time, CLI-only script
// Usage: php ws-server..php [port]

$host = '0.0.0.0';
$port = 65432;

if (PHP_SAPI === 'cli' && isset($argv[1]) && is_numeric($argv[1])) {
    $port = (int) $argv[1];
}

$address = "tcp://{$host}:{$port}";
$server = @stream_socket_server($address, $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "Failed to start WebSocket server on {$address}: {$errstr} ({$errno})\n");

    // Try common alternate ports if initial one fails.
    $fallbackPorts = [8080, 9000, 10000];
    foreach ($fallbackPorts as $candidate) {
        if ($candidate === $port) {
            continue;
        }
        $address = "tcp://{$host}:{$candidate}";
        $server = @stream_socket_server($address, $errno, $errstr);
        if ($server) {
            $port = $candidate;
            echo "Fallback to port {$port} successful\n";
            break;
        }
        fwrite(STDERR, "Fallback failed on {$address}: {$errstr} ({$errno})\n");
    }
}

if (!$server) {
    fwrite(STDERR, "Could not bind any port. Please run as administrator or stop the process using this port.\n");
    fwrite(STDERR, "Check used ports with: netstat -ano | findstr \"{$port}\"\n");
    exit(1);
}

stream_set_blocking($server, false);
$clients = [];

echo "WebSocket server started on {$host}:{$port}\n";

define('WS_MAGIC_STRING', '258EAFA5-E914-47DA-95CA-C5AB0DC85B11');

function wsHandshake($client, $request) {
    if (!preg_match('/Sec-WebSocket-Key: (.*)\r\n/', $request, $matches)) {
        return false;
    }

    $key = trim($matches[1]);
    $accept = base64_encode(sha1($key . WS_MAGIC_STRING, true));

    $upgrade = "HTTP/1.1 101 Switching Protocols\r\n"
        . "Upgrade: websocket\r\n"
        . "Connection: Upgrade\r\n"
        . "Sec-WebSocket-Accept: {$accept}\r\n\r\n";

    fwrite($client, $upgrade);
    return true;
}

function wsDecode($data) {
    $payloadLength = ord($data[1]) & 127;
    $masks = '';
    $payload = '';

    if ($payloadLength === 126) {
        $masks = substr($data, 4, 4);
        $payload = substr($data, 8);
    } elseif ($payloadLength === 127) {
        $masks = substr($data, 10, 4);
        $payload = substr($data, 14);
    } else {
        $masks = substr($data, 2, 4);
        $payload = substr($data, 6);
    }

    $text = '';
    for ($i = 0, $len = strlen($payload); $i < $len; ++$i) {
        $text .= $payload[$i] ^ $masks[$i % 4];
    }
    return $text;
}

function wsEncode($payload, $type = 'text') {
    $frameHead = [];
    $payloadLength = strlen($payload);

    $frameHead[0] = ($type === 'text') ? 129 : 130;

    if ($payloadLength <= 125) {
        $frameHead[1] = $payloadLength;
    } elseif ($payloadLength <= 65535) {
        $frameHead[1] = 126;
        $frameHead[2] = ($payloadLength >> 8) & 255;
        $frameHead[3] = $payloadLength & 255;
    } else {
        $frameHead[1] = 127;
        for ($i = 0; $i < 8; $i++) {
            $frameHead[2 + $i] = ($payloadLength >> (56 - $i * 8)) & 255;
        }
    }

    $frame = '';
    foreach ($frameHead as $byte) {
        $frame .= chr($byte);
    }

    return $frame . $payload;
}

while (true) {
    $read = [$server];
    foreach ($clients as $clientData) {
        $read[] = $clientData['stream'];
    }

    $write = $except = null;
    if (@stream_select($read, $write, $except, 0, 200000)) {
        if (in_array($server, $read, true)) {
            $connection = @stream_socket_accept($server, 0);
            if ($connection) {
                stream_set_blocking($connection, false);
                $clients[(int) $connection] = ['stream' => $connection, 'handshake' => false];
                echo "Client connected: " . (int) $connection . "\n";
            }

            $index = array_search($server, $read, true);
            if ($index !== false) {
                unset($read[$index]);
            }
        }

        foreach ($read as $clientStream) {
            $clientKey = (int) $clientStream;
            $data = @fread($clientStream, 4096);

            if ($data === false || $data === '') {
                if (isset($clients[$clientKey])) {
                    fclose($clients[$clientKey]['stream']);
                    unset($clients[$clientKey]);
                    echo "Client disconnected: {$clientKey}\n";
                }
                continue;
            }

            if (!$clients[$clientKey]['handshake']) {
                if (wsHandshake($clientStream, $data)) {
                    $clients[$clientKey]['handshake'] = true;
                    echo "Handshake complete: {$clientKey}\n";
                } else {
                    fclose($clientStream);
                    unset($clients[$clientKey]);
                    echo "Handshake failed: {$clientKey}\n";
                }
                continue;
            }

            $message = wsDecode($data);
            if ($message === '') {
                continue;
            }

            echo "Recv from {$clientKey}: {$message}\n";
            $out = wsEncode("Echo: {$message}");

            foreach ($clients as $client) {
                @fwrite($client['stream'], $out);
            }
        }
    }
}
