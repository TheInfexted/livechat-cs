<?php

namespace App\Controllers;

class ChatController extends General
{
    public function index()
    {
        $data = [
            'title' => 'Customer Support Chat',
            'session_id' => $this->session->get('chat_session_id') ?? null
        ];
        
        return view('chat/customer', $data);
    }
    
    public function startSession()
    {
        $name = $this->sanitizeInput($this->request->getPost('name'));
        $email = $this->sanitizeInput($this->request->getPost('email'));
        
        if (empty($name)) {
            return $this->jsonResponse(['error' => 'Name is required'], 400);
        }
        
        $sessionId = $this->generateSessionId();
        
        $data = [
            'session_id' => $sessionId,
            'customer_name' => $name,
            'customer_email' => $email,
            'status' => 'waiting'
        ];
        
        $chatId = $this->chatModel->insert($data);
        
        if ($chatId) {
            $this->session->set('chat_session_id', $sessionId);
            return $this->jsonResponse([
                'success' => true,
                'session_id' => $sessionId,
                'chat_id' => $chatId
            ]);
        }
        
        return $this->jsonResponse(['error' => 'Failed to start chat session'], 500);
    }
    
    public function assignAgent()
    {
        if (!$this->isAuthenticated()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }
        
        $sessionId = $this->request->getPost('session_id');
        $agentId = $this->session->get('user_id');
        
        $updated = $this->chatModel->assignAgent($sessionId, $agentId);
        
        if ($updated) {
            return $this->jsonResponse(['success' => true]);
        }
        
        return $this->jsonResponse(['error' => 'Failed to assign agent'], 500);
    }
    
    public function getMessages($sessionId)
    {
        $messages = $this->messageModel->getSessionMessages($sessionId);
        return $this->jsonResponse($messages);
    }
    
    public function closeSession()
    {
        $sessionId = $this->request->getPost('session_id');
        
        $updated = $this->chatModel->closeSession($sessionId);
        
        if ($updated) {
            return $this->jsonResponse(['success' => true]);
        }
        
        return $this->jsonResponse(['error' => 'Failed to close session'], 500);
    }
    
    public function checkSessionStatus($sessionId)
    {
        $session = $this->chatModel->getSessionBySessionId($sessionId);
        
        if ($session) {
            return $this->jsonResponse([
                'status' => $session['status'],
                'session_id' => $sessionId
            ]);
        }
        
        return $this->jsonResponse(['error' => 'Session not found'], 404);
    }
}