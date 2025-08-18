<?php

namespace App\Models;

use CodeIgniter\Model;

class MessageModel extends Model
{
    protected $table = 'messages';
    protected $primaryKey = 'id';
    protected $allowedFields = ['session_id', 'sender_type', 'sender_id', 'message', 'message_type', 'is_read'];
    
    public function getSessionMessages($sessionId)
    {
        $chatSession = model('ChatModel')->getSessionBySessionId($sessionId);
        
        if (!$chatSession) {
            return [];
        }
        
        return $this->select('messages.*, 
                           users.username as agent_name,
                           chat_sessions.customer_name,
                           chat_sessions.customer_fullname')
                    ->join('users', 'users.id = messages.sender_id', 'left')
                    ->join('chat_sessions', 'chat_sessions.id = messages.session_id', 'left')
                    ->where('messages.session_id', $chatSession['id'])
                    ->orderBy('messages.created_at', 'ASC')
                    ->findAll();
    }
    
    public function markAsRead($sessionId, $senderType)
    {
        $chatSession = model('ChatModel')->getSessionBySessionId($sessionId);
        
        if ($chatSession) {
            return $this->where('session_id', $chatSession['id'])
                        ->where('sender_type !=', $senderType)
                        ->set(['is_read' => 1])
                        ->update();
        }
        
        return false;
    }
}