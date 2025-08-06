<?php

namespace App\Models;

use CodeIgniter\Model;

class ChatModel extends Model
{
    protected $table = 'chat_sessions';
    protected $primaryKey = 'id';
    protected $allowedFields = ['session_id', 'customer_name', 'customer_fullname', 'chat_topic', 'customer_email', 'agent_id', 'status', 'closed_at'];
    
    public function getActiveSessions()
    {
        return $this->select('chat_sessions.*, users.username as agent_name')
                    ->join('users', 'users.id = chat_sessions.agent_id', 'left')
                    ->where('chat_sessions.status', 'active')
                    ->orderBy('chat_sessions.created_at', 'DESC')
                    ->findAll();
    }
    
    public function getWaitingSessions()
    {
        return $this->where('status', 'waiting')
                    ->orderBy('created_at', 'ASC')
                    ->findAll();
    }
    
    public function assignAgent($sessionId, $agentId)
    {
        return $this->where('session_id', $sessionId)
                    ->set(['agent_id' => $agentId, 'status' => 'active'])
                    ->update();
    }
    
    public function closeSession($sessionId)
    {
        return $this->where('session_id', $sessionId)
                    ->set(['status' => 'closed', 'closed_at' => date('Y-m-d H:i:s')])
                    ->update();
    }
    
    public function getSessionBySessionId($sessionId)
    {
        return $this->where('session_id', $sessionId)->first();
    }
}