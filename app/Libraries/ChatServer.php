<?php

namespace App\Libraries;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class ChatServer implements MessageComponentInterface
{
    protected $clients;
    protected $sessions;
    protected $pdo;
    
    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->sessions = [];
        
        // Initialize direct PDO connection
        $this->connectDatabase();
    }
    
    private function connectDatabase()
    {
        try {
            $this->pdo = new \PDO(
                'mysql:unix_socket=/Applications/MAMP/tmp/mysql/mysql.sock;dbname=cs_livechat;charset=utf8mb4',
                'root',
                'root',
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                    \PDO::ATTR_PERSISTENT => true,
                    \PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION wait_timeout=28800"
                ]
            );
        } catch (\PDOException $e) {
            echo "Database connection failed: " . $e->getMessage() . "\n";
            $this->pdo = null;
        }
    }
    
    private function ensureDatabaseConnection()
    {
        if (!$this->pdo) {
            $this->connectDatabase();
            return;
        }
        
        try {
            // Test the connection
            $this->pdo->query('SELECT 1');
        } catch (\PDOException $e) {
            echo "Database connection lost, reconnecting...\n";
            $this->connectDatabase();
        }
    }
    
    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }
    
    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        
        switch ($data['type']) {
            case 'register':
                $this->registerConnection($from, $data);
                break;
                
            case 'message':
                $this->handleMessage($from, $data);
                break;
                
            case 'typing':
                $this->handleTyping($from, $data);
                break;
                
            case 'assign_agent':
                $this->handleAgentAssignment($from, $data);
                break;
                
            case 'close_session':
                $this->handleSessionClose($from, $data);
                break;
        }
    }
    
    protected function registerConnection($conn, $data)
    {
        $sessionId = $data['session_id'];
        $userType = $data['user_type'] ?? 'customer';
        
        echo "Registering connection: Session ID = $sessionId, User Type = $userType\n";
        
        if (!isset($this->sessions[$sessionId])) {
            $this->sessions[$sessionId] = [];
        }
        
        $this->sessions[$sessionId][$conn->resourceId] = [
            'connection' => $conn,
            'type' => $userType,
            'user_id' => $data['user_id'] ?? null
        ];
        
        echo "Sessions for $sessionId: " . count($this->sessions[$sessionId]) . " connections\n";
        
        // Send connection success
        $conn->send(json_encode([
            'type' => 'connected',
            'message' => 'Successfully connected to chat'
        ]));
        
        // If agent, send waiting sessions
        if ($userType === 'agent') {
            $this->sendWaitingSessions($conn);
        }
    }
    
    protected function handleMessage($from, $data)
    {
        $sessionId = $data['session_id'];
        $message = $data['message'];
        $senderType = $data['sender_type'];
        $senderId = $data['sender_id'] ?? null;
        
        // Get chat session using PDO
        $this->ensureDatabaseConnection();
        if (!$this->pdo) {
            return;
        }
        
        $stmt = $this->pdo->prepare("SELECT * FROM chat_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $chatSession = $stmt->fetch();
        
        if (!$chatSession) {
            return;
        }
        
        // Save message to database
        $stmt = $this->pdo->prepare("
            INSERT INTO messages (session_id, sender_type, sender_id, message, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$chatSession['id'], $senderType, $senderId, $message]);
        $messageId = $this->pdo->lastInsertId();
        
        // Get the actual timestamp from the database
        $stmt = $this->pdo->prepare("
            SELECT created_at FROM messages WHERE id = ?
        ");
        $stmt->execute([$messageId]);
        $timestamp = $stmt->fetchColumn();
        
        // Prepare response
        $response = [
            'type' => 'message',
            'id' => $messageId,
            'session_id' => $sessionId,
            'sender_type' => $senderType,
            'message' => $message,
            'timestamp' => $timestamp,
            'sender_name' => $senderType === 'customer' ? $chatSession['customer_name'] : null
        ];
        
        // Send to all connections in this session
        if (isset($this->sessions[$sessionId])) {
            echo "Sending message to " . count($this->sessions[$sessionId]) . " connections in session $sessionId\n";
            foreach ($this->sessions[$sessionId] as $client) {
                $client['connection']->send(json_encode($response));
            }
        } else {
            echo "No connections found for session $sessionId\n";
        }
        
        // Notify other agents if customer is waiting
        if ($senderType === 'customer' && $chatSession['status'] === 'waiting') {
            $this->notifyAgents($sessionId, 'new_message');
        }
    }
    
    protected function handleTyping($from, $data)
    {
        $sessionId = $data['session_id'];
        $isTyping = $data['is_typing'];
        $userType = $data['user_type'];
        
        $response = [
            'type' => 'typing',
            'session_id' => $sessionId,
            'user_type' => $userType,
            'is_typing' => $isTyping
        ];
        
        // Send to all other connections in this session
        if (isset($this->sessions[$sessionId])) {
            foreach ($this->sessions[$sessionId] as $resourceId => $client) {
                if ($resourceId !== $from->resourceId) {
                    $client['connection']->send(json_encode($response));
                }
            }
        }
    }
    
    protected function handleAgentAssignment($from, $data)
    {
        $sessionId = $data['session_id'];
        $agentId = $data['agent_id'];
        
        $this->ensureDatabaseConnection();
        if (!$this->pdo) {
            return;
        }
        
        // Update database using PDO
        $stmt = $this->pdo->prepare("
            UPDATE chat_sessions 
            SET agent_id = ?, status = 'active' 
            WHERE session_id = ?
        ");
        $stmt->execute([$agentId, $sessionId]);
        
        // Notify all connections in session
        $response = [
            'type' => 'agent_assigned',
            'session_id' => $sessionId,
            'message' => 'An agent has joined the chat'
        ];
        
        if (isset($this->sessions[$sessionId])) {
            foreach ($this->sessions[$sessionId] as $client) {
                $client['connection']->send(json_encode($response));
            }
        }
        
        // Update waiting sessions for all agents
        $this->broadcastToAgents(['type' => 'update_sessions']);
    }
    
    protected function handleSessionClose($from, $data)
    {
        $sessionId = $data['session_id'];
        
        $this->ensureDatabaseConnection();
        if (!$this->pdo) {
            return;
        }
        
        // Update database using PDO
        $stmt = $this->pdo->prepare("
            UPDATE chat_sessions 
            SET status = 'closed', closed_at = NOW() 
            WHERE session_id = ?
        ");
        $stmt->execute([$sessionId]);
        
        // Notify all connections in session
        $response = [
            'type' => 'session_closed',
            'session_id' => $sessionId,
            'message' => 'Chat session has been closed'
        ];
        
        if (isset($this->sessions[$sessionId])) {
            foreach ($this->sessions[$sessionId] as $client) {
                $client['connection']->send(json_encode($response));
            }
            
            // Remove session
            unset($this->sessions[$sessionId]);
        }
        
        // Update sessions for all agents
        $this->broadcastToAgents(['type' => 'update_sessions']);
    }
    
    protected function sendWaitingSessions($conn)
    {
        $this->ensureDatabaseConnection();
        if (!$this->pdo) {
            return;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM chat_sessions 
            WHERE status = 'waiting' 
            ORDER BY created_at ASC
        ");
        $stmt->execute();
        $waitingSessions = $stmt->fetchAll();
        
        $conn->send(json_encode([
            'type' => 'waiting_sessions',
            'sessions' => $waitingSessions
        ]));
    }
    
    protected function notifyAgents($sessionId, $notificationType)
    {
        $notification = [
            'type' => $notificationType,
            'session_id' => $sessionId
        ];
        
        $this->broadcastToAgents($notification);
    }
    
    protected function broadcastToAgents($data)
    {
        foreach ($this->sessions as $sessionId => $clients) {
            foreach ($clients as $client) {
                if ($client['type'] === 'agent') {
                    $client['connection']->send(json_encode($data));
                }
            }
        }
    }
    
    public function onClose(ConnectionInterface $conn)
    {
        // Remove from all sessions
        foreach ($this->sessions as $sessionId => &$clients) {
            unset($clients[$conn->resourceId]);
            
            if (empty($clients)) {
                unset($this->sessions[$sessionId]);
            }
        }
        
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}