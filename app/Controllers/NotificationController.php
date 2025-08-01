<?php
namespace App\Controllers;

class NotificationController extends BaseController
{
    public function poll()
    {
        if (!$this->session->get('user_id') && !$this->session->get('chat_session_id')) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }
        
        $lastCheck = $this->request->getGet('last_check');
        $userType = $this->request->getGet('user_type');
        $sessionId = $this->request->getGet('session_id');
        
        $notifications = [];
        
        if ($userType === 'agent') {
            $notifications = $this->getAgentNotifications($lastCheck);
        } else {
            $notifications = $this->getCustomerNotifications($sessionId, $lastCheck);
        }
        
        return $this->jsonResponse([
            'notifications' => $notifications,
            'timestamp' => time()
        ]);
    }
    
    private function getAgentNotifications($lastCheck)
    {
        // Get new waiting sessions, transfers, etc.
        $notifications = [];
        
        // New sessions waiting
        $waitingSessions = $this->chatModel->where('status', 'waiting')
                                          ->where('created_at >', date('Y-m-d H:i:s', $lastCheck))
                                          ->findAll();
        
        foreach ($waitingSessions as $session) {
            $notifications[] = [
                'type' => 'new_chat',
                'message' => "New chat from {$session['customer_name']}",
                'session_id' => $session['session_id'],
                'timestamp' => strtotime($session['created_at'])
            ];
        }
        
        return $notifications;
    }
    
    private function getCustomerNotifications($sessionId, $lastCheck)
    {
        // Get new messages, status changes, etc.
        $notifications = [];
        
        if ($sessionId) {
            $session = $this->chatModel->getSessionBySessionId($sessionId);
            if ($session) {
                // Check for new agent messages
                $newMessages = $this->messageModel->where('session_id', $session['id'])
                                                 ->where('sender_type', 'agent')
                                                 ->where('created_at >', date('Y-m-d H:i:s', $lastCheck))
                                                 ->findAll();
                
                foreach ($newMessages as $message) {
                    $notifications[] = [
                        'type' => 'new_message',
                        'message' => 'New message from agent',
                        'content' => $message['message'],
                        'timestamp' => strtotime($message['created_at'])
                    ];
                }
            }
        }
        
        return $notifications;
    }
}