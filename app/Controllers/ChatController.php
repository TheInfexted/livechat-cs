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

    // File upload handling
    public function uploadFile()
    {
        // Allow both authenticated users (agents) and customers with active sessions
        $sessionId = $this->request->getPost('session_id');
        if (!$this->isAuthenticated() && !$sessionId) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $file = $this->request->getFile('file');
        $sessionId = $this->request->getPost('session_id');
        $messageId = $this->request->getPost('message_id');

        if (!$file) {
            return $this->jsonResponse(['error' => 'No file uploaded'], 400);
        }

        if (!$file->isValid()) {
            return $this->jsonResponse(['error' => 'Invalid file: ' . $file->getError()], 400);
        }

        // Get file properties before moving
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();
        $originalName = $file->getClientName();
        
        // Debug logging
        log_message('info', 'File upload attempt - Name: ' . $originalName . ', Size: ' . $fileSize . ', Type: ' . $mimeType);
        
        // Validate file type and size
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($mimeType, $allowedTypes)) {
            return $this->jsonResponse(['error' => 'File type not allowed'], 400);
        }

        if ($fileSize > $maxSize) {
            return $this->jsonResponse(['error' => 'File too large'], 400);
        }

        // Create upload directory if not exists
        $uploadPath = WRITEPATH . 'uploads/chat/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $fileName = $file->getRandomName();
        
        try {
            $file->move($uploadPath, $fileName);
        } catch (\Exception $e) {
            log_message('error', 'File move failed: ' . $e->getMessage());
            return $this->jsonResponse(['error' => 'Failed to save file'], 500);
        }

        // Save file info to database
        $fileData = [
            'message_id' => $messageId,
            'original_name' => $originalName,
            'file_path' => 'uploads/chat/' . $fileName,
            'file_size' => $fileSize,
            'mime_type' => $mimeType
        ];

        $fileId = $this->chatFileModel->insert($fileData);

        return $this->jsonResponse([
            'success' => true,
            'file_id' => $fileId,
            'file_url' => base_url('uploads/chat/' . $fileName),
            'file_name' => $file->getClientName()
        ]);
    }

    // Get chat history for a customer
    public function getCustomerHistory($customerId)
    {
        if (!$this->isAuthenticated()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $history = $this->chatModel->select('chat_sessions.*, users.username as agent_name')
                                 ->join('users', 'users.id = chat_sessions.agent_id', 'left')
                                 ->where('customer_id', $customerId)
                                 ->orderBy('created_at', 'DESC')
                                 ->findAll();

        return $this->jsonResponse($history);
    }

    // Rate chat session
    public function rateSession()
    {
        $sessionId = $this->request->getPost('session_id');
        $rating = $this->request->getPost('rating');
        $feedback = $this->request->getPost('feedback');

        if (!$sessionId || !$rating || $rating < 1 || $rating > 5) {
            return $this->jsonResponse(['error' => 'Invalid rating data'], 400);
        }

        $updated = $this->chatModel->where('session_id', $sessionId)
                                  ->set([
                                      'rating' => $rating,
                                      'feedback' => $feedback
                                  ])
                                  ->update();

        if ($updated) {
            return $this->jsonResponse(['success' => true]);
        }

        return $this->jsonResponse(['error' => 'Failed to save rating'], 500);
    }

    // Get queue position
    public function getQueuePosition($sessionId)
    {
        $position = $this->chatQueueModel->where('session_id', $sessionId)->first();
        
        if ($position) {
            return $this->jsonResponse([
                'position' => $position['queue_position'],
                'estimated_wait' => $position['estimated_wait_time']
            ]);
        }

        return $this->jsonResponse(['error' => 'Not in queue'], 404);
    }

    // Send canned response
    public function sendCannedResponse()
    {
        if (!$this->isAuthenticated()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $sessionId = $this->request->getPost('session_id');
        $responseId = $this->request->getPost('response_id');
        $agentId = $this->session->get('user_id');

        $cannedResponse = $this->cannedResponseModel->find($responseId);
        if (!$cannedResponse) {
            return $this->jsonResponse(['error' => 'Canned response not found'], 404);
        }

        // Check if agent can use this response
        if (!$cannedResponse['is_global'] && $cannedResponse['agent_id'] != $agentId) {
            return $this->jsonResponse(['error' => 'Access denied'], 403);
        }

        // Send the message
        $chatSession = $this->chatModel->getSessionBySessionId($sessionId);
        if (!$chatSession) {
            return $this->jsonResponse(['error' => 'Chat session not found'], 404);
        }

        $messageData = [
            'session_id' => $chatSession['id'],
            'sender_type' => 'agent',
            'sender_id' => $agentId,
            'message' => $cannedResponse['content'],
            'message_type' => 'text'
        ];

        $messageId = $this->messageModel->insert($messageData);

        return $this->jsonResponse([
            'success' => true,
            'message_id' => $messageId,
            'message' => $cannedResponse['content']
        ]);
    }

    // Get agent workload
    public function getAgentWorkload()
    {
        if (!$this->isAuthenticated()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $agents = $this->userModel->select('id, username, current_chats, max_concurrent_chats, status, is_online')
                                 ->whereIn('role', ['admin', 'support'])
                                 ->findAll();

        return $this->jsonResponse($agents);
    }



    // Bulk close inactive sessions
    public function closeInactiveSessions()
    {
        if (!$this->isAuthenticated()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        // Close sessions inactive for more than 30 minutes
        $cutoffTime = date('Y-m-d H:i:s', strtotime('-30 minutes'));
        
        $inactiveSessions = $this->chatModel->where('status', 'active')
                                           ->where('updated_at <', $cutoffTime)
                                           ->findAll();

        $closedCount = 0;
        foreach ($inactiveSessions as $session) {
            $this->chatModel->update($session['id'], [
                'status' => 'closed',
                'closed_at' => date('Y-m-d H:i:s')
            ]);
            $closedCount++;
        }

        return $this->jsonResponse([
            'success' => true,
            'closed_sessions' => $closedCount
        ]);
    }
}