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
        
        return $this->select('messages.*, users.username as sender_name')
                    ->join('users', 'users.id = messages.sender_id', 'left')
                    ->where('session_id', $chatSession['id'])
                    ->orderBy('created_at', 'ASC')
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