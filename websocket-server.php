<?php

require 'vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\Libraries\ChatServer;
use React\Socket\Server;
use React\Socket\SecureServer;

// Create a secure server with SSL
$loop = \React\EventLoop\Factory::create();

// Try to use your existing SSL certificates (aaPanel usually puts them here)
$sslOptions = [
    'local_cert' => '/www/server/panel/vhost/cert/livechat.kopisugar.cc/fullchain.pem',
    'local_pk' => '/www/server/panel/vhost/cert/livechat.kopisugar.cc/privkey.pem',
    'verify_peer' => false,
    'allow_self_signed' => true,
    'verify_peer_name' => false
];

try {
    // Create secure server
    $tcpServer = new Server('0.0.0.0:39147', $loop);
    $secureServer = new SecureServer($tcpServer, $loop, $sslOptions);
    
    $server = new IoServer(
        new HttpServer(
            new WsServer(
                new ChatServer()
            )
        ),
        $secureServer,
        $loop
    );
    
    echo "Secure WebSocket server started on port 39147 (wss://)\n";
    $loop->run();
    
} catch (Exception $e) {
    echo "SSL setup failed, falling back to regular WebSocket on port 39146\n";
    echo "Error: " . $e->getMessage() . "\n";
    
    // Fallback to regular WebSocket
    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new ChatServer()
            )
        ),
        39146
    );
    
    echo "Regular WebSocket server started on port 39146\n";
    $server->run();
}