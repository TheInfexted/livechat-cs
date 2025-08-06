<?php

namespace App\Controllers;

class ChatController extends General
{
    public function index()
    {
        $sessionId = $this->session->get('chat_session_id');
        $validSession = null;
        
        // Check if session is still valid (exists and not closed)
        if ($sessionId) {
            $chatSession = $this->chatModel->getSessionBySessionId($sessionId);
            if ($chatSession && $chatSession['status'] !== 'closed') {
                $validSession = $sessionId;
            } else {
                // Clear invalid session from PHP session
                $this->session->remove('chat_session_id');
            }
        }
        
        $data = [
            'title' => 'Customer Support Chat',
            'session_id' => $validSession
        ];
        
        return view('chat/customer', $data);
    }
    
    public function startSession()
    {
        // Handle both new format (customer_name + chat_topic) and legacy format (name for topic)
        $customerNameInput = $this->sanitizeInput($this->request->getPost('customer_name'));
        $topicInput = $this->sanitizeInput($this->request->getPost('chat_topic'));
        $legacyNameInput = $this->sanitizeInput($this->request->getPost('name')); // For backwards compatibility
        $email = $this->sanitizeInput($this->request->getPost('email'));
        
        // Determine topic (required)
        $topic = $topicInput ?: $legacyNameInput;
        if (empty($topic)) {
            return $this->jsonResponse(['error' => 'Please describe what you need help with'], 400);
        }
        
        $sessionId = $this->generateSessionId();
        
        // Determine customer name
        $customerName = 'Anonymous';
        $customerFullName = 'Anonymous';
        
        if (!empty($customerNameInput)) {
            // Use provided name
            $customerName = $customerNameInput;
            $customerFullName = $customerNameInput;
        } elseif (!empty($email)) {
            // Try to extract name from email (part before @)
            $emailParts = explode('@', $email);
            if (!empty($emailParts[0])) {
                $customerName = ucfirst($emailParts[0]);
                $customerFullName = ucfirst($emailParts[0]);
            }
        }
        
        $data = [
            'session_id' => $sessionId,
            'customer_name' => $customerName,
            'customer_fullname' => $customerFullName,
            'chat_topic' => $topic,
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
    
    
    /**
     * Customer leaves session - does NOT close it, just notifies and clears customer access
     */
    public function endCustomerSession()
    {
        $sessionId = $this->request->getPost('session_id');
        
        // Log the request for debugging
        log_message('info', 'endCustomerSession called with session_id: ' . ($sessionId ?: 'null'));
        
        if (!$sessionId) {
            log_message('error', 'endCustomerSession: No session ID provided');
            return $this->jsonResponse(['error' => 'Session ID is required'], 400);
        }
        
        // Get the chat session to verify it exists
        $chatSession = $this->chatModel->getSessionBySessionId($sessionId);
        log_message('info', 'endCustomerSession: Chat session lookup result: ' . ($chatSession ? 'found' : 'not found'));
        
        if (!$chatSession) {
            // Check if session ID exists in PHP session vs what was passed
            $phpSessionId = $this->session->get('chat_session_id');
            log_message('error', 'endCustomerSession: Session not found in DB. Requested: ' . $sessionId . ', PHP Session: ' . ($phpSessionId ?: 'null'));
            return $this->jsonResponse(['error' => 'Session not found', 'debug' => ['requested' => $sessionId, 'php_session' => $phpSessionId]], 404);
        }
        
        // Check if session is already closed
        if ($chatSession['status'] === 'closed') {
            log_message('info', 'endCustomerSession: Session already closed: ' . $sessionId);
            // Still clear PHP session and return success
            $this->session->remove('chat_session_id');
            return $this->jsonResponse([
                'success' => true, 
                'message' => 'Chat session was already closed',
                'customer_left' => true
            ]);
        }
        
        // Customer leaves but session stays open for admin
        // Add a system message that customer left the chat
        $messageData = [
            'session_id' => $chatSession['id'],
            'sender_type' => 'agent',
            'sender_id' => null,
            'message' => 'Customer left the chat',
            'message_type' => 'system'
        ];
        
        $messageInserted = $this->messageModel->insert($messageData);
        log_message('info', 'endCustomerSession: System message inserted: ' . ($messageInserted ? 'success' : 'failed'));
        
        // Send WebSocket notification to agents viewing this session
        if ($messageInserted) {
            $this->notifyAgentsOfCustomerLeft($sessionId, $messageInserted);
        }
        
        // Clear the customer's PHP session so they can't access this chat anymore
        $this->session->remove('chat_session_id');
        
        log_message('info', 'Customer left session (session remains open): ' . $sessionId);
        
        return $this->jsonResponse([
            'success' => true, 
            'message' => 'You have left the chat. The session remains available for the agent.',
            'customer_left' => true
        ]);
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
    
    // Get active keyword responses for quick actions
    public function getQuickActions()
    {
        if (!isset($this->keywordResponseModel)) {
            $this->keywordResponseModel = new \App\Models\KeywordResponseModel();
        }
        
        $activeResponses = $this->keywordResponseModel->where('is_active', 1)
                                                    ->orderBy('keyword', 'ASC')
                                                    ->findAll();
        
        // Format for frontend
        $quickActions = [];
        foreach ($activeResponses as $response) {
            $quickActions[] = [
                'id' => $response['id'],
                'keyword' => $response['keyword'],
                'display_name' => ucfirst($response['keyword'])
            ];
        }
        
        return $this->jsonResponse($quickActions);
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
    
    /**
     * Send WebSocket notification to agents when customer leaves
     */
    private function notifyAgentsOfCustomerLeft($sessionId, $messageId)
    {
        // For now, we'll rely on the real-time message loading when admin refreshes
        // or we can implement a server-sent events or polling mechanism
        // The system message is already saved to database and will show in chat history
        
        // Log for debugging
        log_message('info', 'Customer left notification: Session=' . $sessionId . ', Message=' . $messageId);
        
        // Future enhancement: Implement proper WebSocket broadcasting here
        // For now, the admin will see the message when they reload the chat history
    }
}
