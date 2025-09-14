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
    
    /**
     * Get chat history for a logged user across all sessions within specified days
     */
    public function getUserChatHistory($externalUsername, $externalFullname, $externalSystemId, $daysBack = 30, $excludeCurrentSession = null)
    {
        $timeThreshold = date('Y-m-d H:i:s', strtotime("-{$daysBack} days"));
        
        // Build the query to find all sessions for this user
        $chatModel = model('ChatModel');
        $sessionQuery = $chatModel->where('user_role', 'loggedUser')
                                 ->where('created_at >=', $timeThreshold);
        
        // Build user matching conditions
        if ($externalUsername && $externalFullname) {
            $sessionQuery->groupStart()
                        ->where('external_username', $externalUsername)
                        ->orWhere('external_fullname', $externalFullname)
                        ->groupEnd();
        } elseif ($externalUsername) {
            $sessionQuery->where('external_username', $externalUsername);
        } elseif ($externalFullname) {
            $sessionQuery->where('external_fullname', $externalFullname);
        } else {
            return []; // No user identifier provided
        }
        
        // Add external_system_id if available for extra verification
        if ($externalSystemId) {
            $sessionQuery->where('external_system_id', $externalSystemId);
        }
        
        // Exclude current session if specified
        if ($excludeCurrentSession) {
            $sessionQuery->where('session_id !=', $excludeCurrentSession);
        }
        
        $userSessions = $sessionQuery->findAll();
        
        if (empty($userSessions)) {
            return [];
        }
        
        // Get session IDs
        $sessionIds = array_column($userSessions, 'id');
        
        // Get all messages from these sessions
        $messages = $this->select('messages.*, 
                                 users.username as agent_name,
                                 chat_sessions.customer_name,
                                 chat_sessions.customer_fullname,
                                 chat_sessions.session_id as chat_session_id')
                        ->join('users', 'users.id = messages.sender_id', 'left')
                        ->join('chat_sessions', 'chat_sessions.id = messages.session_id', 'left')
                        ->whereIn('messages.session_id', $sessionIds)
                        ->orderBy('messages.created_at', 'ASC')
                        ->findAll();
        
        return $messages;
    }
    
    /**
     * Get session messages with optional historical context for logged users
     */
    public function getSessionMessagesWithHistory($sessionId, $includeHistory = false, $externalUsername = null, $externalFullname = null, $externalSystemId = null)
    {
        $currentMessages = $this->getSessionMessages($sessionId);
        
        if (!$includeHistory || (!$externalUsername && !$externalFullname)) {
            return $currentMessages;
        }
        
        // Get historical messages (exclude current session)
        $historicalMessages = $this->getUserChatHistory($externalUsername, $externalFullname, $externalSystemId, 30, $sessionId);
        
        // Combine and sort all messages by timestamp
        $allMessages = array_merge($historicalMessages, $currentMessages);
        
        // Sort by created_at timestamp
        usort($allMessages, function($a, $b) {
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        });
        
        return $allMessages;
    }
}
