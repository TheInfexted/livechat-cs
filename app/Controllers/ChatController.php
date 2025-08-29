<?php

namespace App\Controllers;

use Exception;

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
        
        // Handle iframe integration parameters
        $isIframe = $this->request->getGet('iframe') === '1';
        $isFullscreen = $this->request->getGet('fullscreen') === '1';
        $apiKey = $this->sanitizeInput($this->request->getGet('api_key'));
        $externalUsername = $this->sanitizeInput($this->request->getGet('external_username'));
        $externalFullname = $this->sanitizeInput($this->request->getGet('external_fullname'));
        $externalSystemId = $this->sanitizeInput($this->request->getGet('external_system_id'));
        $userRole = $this->sanitizeInput($this->request->getGet('user_role')) ?: 'anonymous';
        
        // Validate API key for iframe integrations
        if ($isIframe && $apiKey) {
            $apiKeyModel = new \App\Models\ApiKeyModel();
            $domain = $this->request->getServer('HTTP_REFERER') ? parse_url($this->request->getServer('HTTP_REFERER'), PHP_URL_HOST) : null;
            $validation = $apiKeyModel->validateApiKey($apiKey, $domain);
            
            if (!$validation['valid']) {
                // Show error page for invalid API key
                return view('errors/api_key_invalid', ['error' => $validation['error']]);
            }
        }
        
        $data = [
            'title' => 'Customer Support Chat',
            'session_id' => $validSession,
            'is_iframe' => $isIframe,
            'is_fullscreen' => $isFullscreen,
            'api_key' => $apiKey,
            'external_username' => $externalUsername,
            'external_fullname' => $externalFullname,
            'external_system_id' => $externalSystemId,
            'user_role' => $userRole
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
        
        // Role-based parameters
        $userRole = $this->sanitizeInput($this->request->getPost('user_role')) ?: 'anonymous';
        $externalUsername = $this->sanitizeInput($this->request->getPost('external_username'));
        $externalFullname = $this->sanitizeInput($this->request->getPost('external_fullname'));
        $externalSystemId = $this->sanitizeInput($this->request->getPost('external_system_id'));
        
        // API key for iframe integrations (can come from POST or session)
        $apiKey = $this->sanitizeInput($this->request->getPost('api_key')) ?: 
                 $this->sanitizeInput($this->request->getGet('api_key'));
        
        // Validate API key and get client_id if provided
        $clientId = null;
        if ($apiKey) {
            $apiKeyModel = new \App\Models\ApiKeyModel();
            $domain = $this->request->getServer('HTTP_REFERER') ? parse_url($this->request->getServer('HTTP_REFERER'), PHP_URL_HOST) : null;
            $validation = $apiKeyModel->validateApiKey($apiKey, $domain);
            
            if (!$validation['valid']) {
                return $this->jsonResponse(['error' => $validation['error']], 400);
            }
            
            // Get client_id directly from the API key data
            $keyData = $validation['key_data'];
            
            // Debug logging to track client_id
            error_log('DEBUG - API Key validation successful');
            error_log('DEBUG - Key data: ' . print_r($keyData, true));
            
            // The API key model should already have the client_id from the validation
            if (isset($keyData['client_id']) && $keyData['client_id']) {
                $clientId = (int)$keyData['client_id'];
                error_log('DEBUG - Client ID extracted from key data: ' . $clientId);
            } else {
                error_log('DEBUG - No client_id found in key data or client_id is empty');
            }
        }
        
        // Validate role exists
        if (!$this->userRoleModel->getRoleByName($userRole)) {
            return $this->jsonResponse(['error' => 'Invalid user role specified'], 400);
        }
        
        // Check if role can access chat
        if (!$this->userRoleModel->canAccessChat($userRole)) {
            return $this->jsonResponse(['error' => 'This role is not allowed to access chat'], 403);
        }
        
        // Determine topic (required)
        $topic = $topicInput ?: $legacyNameInput;
        if (empty($topic)) {
            return $this->jsonResponse(['error' => 'Please describe what you need help with'], 400);
        }
        
        $sessionId = $this->generateSessionId();
        
        // Determine customer name based on role
        $customerName = 'Anonymous';
        $customerFullName = 'Anonymous';
        
        if ($userRole === 'loggedUser') {
            // For logged users, prioritize external user info
            if (!empty($externalFullname)) {
                $customerName = $externalFullname;
                $customerFullName = $externalFullname;
            } elseif (!empty($externalUsername)) {
                $customerName = $externalUsername;
                $customerFullName = $externalUsername;
            } elseif (!empty($customerNameInput)) {
                $customerName = $customerNameInput;
                $customerFullName = $customerNameInput;
            }
        } else {
            // For anonymous users, use provided name or extract from email
            if (!empty($customerNameInput)) {
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
        }
        
        $data = [
            'session_id' => $sessionId,
            'customer_name' => $customerName,
            'customer_fullname' => $customerFullName,
            'chat_topic' => $topic,
            'customer_email' => $email,
            'user_role' => $userRole,
            'external_username' => $externalUsername,
            'external_fullname' => $externalFullname,
            'external_system_id' => $externalSystemId,
            'api_key' => $apiKey,
            'client_id' => $clientId,
            'status' => 'waiting'
        ];
        
        // Debug logging for database insertion
        error_log('DEBUG - About to insert chat session with data: ' . print_r($data, true));
        error_log('DEBUG - Client ID specifically: ' . var_export($clientId, true));
        
        $chatId = $this->chatModel->insert($data);
        
        // Debug logging after insertion
        if ($chatId) {
            error_log('DEBUG - Chat session inserted successfully with ID: ' . $chatId);
            
            // Verify what was actually inserted
            $insertedData = $this->chatModel->find($chatId);
            if ($insertedData) {
                error_log('DEBUG - Inserted data verification - client_id: ' . var_export($insertedData['client_id'], true));
            }
        } else {
            error_log('DEBUG - Failed to insert chat session');
        }
        
        if ($chatId) {
            $this->session->set('chat_session_id', $sessionId);
            $this->session->set('user_role', $userRole);
            return $this->jsonResponse([
                'success' => true,
                'session_id' => $sessionId,
                'chat_id' => $chatId,
                'user_role' => $userRole
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
     * Customer leaves session - CLOSES the session completely for both customer and admin
     */
    public function endCustomerSession()
    {
        $sessionId = $this->request->getPost('session_id');
        
        if (!$sessionId) {
            return $this->jsonResponse(['error' => 'Session ID is required'], 400);
        }
        
        // Get the chat session to verify it exists
        $chatSession = $this->chatModel->getSessionBySessionId($sessionId);
        
        if (!$chatSession) {
            // Check if session ID exists in PHP session vs what was passed
            $phpSessionId = $this->session->get('chat_session_id');
            return $this->jsonResponse(['error' => 'Session not found', 'debug' => ['requested' => $sessionId, 'php_session' => $phpSessionId]], 404);
        }
        
        // Check if session is already closed
        if ($chatSession['status'] === 'closed') {
            // Still clear PHP session and return success
            $this->session->remove('chat_session_id');
            return $this->jsonResponse([
                'success' => true, 
                'message' => 'Chat session was already closed',
                'customer_left' => true
            ]);
        }
        
        // Close the session completely when customer leaves
        $sessionClosed = $this->chatModel->closeSession($sessionId);
        
        if ($sessionClosed) {
            // Add a system message that customer left and session is closed
            $messageData = [
                'session_id' => $chatSession['id'],
                'sender_type' => 'agent',
                'sender_id' => null,
                'message' => 'Customer left the chat - Session closed',
                'message_type' => 'system'
            ];
            
            $messageInserted = $this->messageModel->insert($messageData);
            
            // Send WebSocket notification to close the session for all participants
            if ($messageInserted) {
                $this->notifySessionClosed($sessionId);
            }
        }
        
        // Clear the customer's PHP session so they can't access this chat anymore
        $this->session->remove('chat_session_id');
        
        return $this->jsonResponse([
            'success' => true, 
            'message' => 'You have left the chat. The session has been closed.',
            'customer_left' => true,
            'session_closed' => true
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

    

    // Send canned response - Proxy to backend
    public function sendCannedResponse()
    {
        if (!$this->isAuthenticated()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        // Frontend no longer handles canned responses directly
        // This should be handled by the backend admin system
        return $this->jsonResponse([
            'error' => 'Canned responses are managed by the admin system (livechat-bo)',
            'redirect' => 'Please use the admin backend for canned response functionality'
        ], 501);
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
        // Set CORS headers if needed for cross-origin requests
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Headers: Content-Type');
        
        try {
            // Get API key from query parameter or header
            $apiKey = $this->request->getGet('api_key') ?: $this->request->getHeaderLine('X-API-Key');
            
            // Debug logging
            error_log('DEBUG - getQuickActions called with API key: ' . ($apiKey ? 'present' : 'not present'));
            
            $quickActions = [];
            
            if ($apiKey) {
                // Use ApiKeyModel to validate the API key and get client_id
                $apiKeyModel = new \App\Models\ApiKeyModel();
                $validation = $apiKeyModel->validateApiKey($apiKey);
                
                error_log('DEBUG - API key validation result: ' . ($validation['valid'] ? 'valid' : 'invalid'));
                
                if ($validation['valid']) {
                    $keyData = $validation['key_data'];
                    $clientId = $keyData['client_id'];
                    
                    error_log('DEBUG - Client ID from API key: ' . $clientId);
                    
                    // Get keyword responses for this specific client
                    $keywordResponses = $this->keywordResponseModel->getActiveResponsesForClient($clientId);
                    
                    error_log('DEBUG - Found ' . count($keywordResponses) . ' keyword responses for client ' . $clientId);
                    
                    // Transform the data to match the expected format for frontend
                    foreach ($keywordResponses as $response) {
                        $quickActions[] = [
                            'keyword' => $response['keyword'],
                            'display_name' => $response['keyword'], // Using keyword as display name
                            'response' => $response['response']
                        ];
                    }
                } else {
                    error_log('DEBUG - API key validation failed: ' . ($validation['error'] ?? 'unknown error'));
                }
            } else {
                error_log('DEBUG - No API key provided');
            }
            
            error_log('DEBUG - Returning ' . count($quickActions) . ' quick actions');
            
        } catch (\Exception $e) {
            // Log the exception for debugging
            error_log('DEBUG - Exception in getQuickActions: ' . $e->getMessage());
            error_log('DEBUG - Exception trace: ' . $e->getTraceAsString());
            $quickActions = [];
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
     * Get available user roles
     */
    public function getRoles()
    {
        $roles = $this->userRoleModel->getActiveRoles();
        return $this->jsonResponse($roles);
    }
    
    /**
     * Get session with role information
     */
    public function getSessionWithRole($sessionId)
    {
        $session = $this->chatModel->select('chat_sessions.*, user_roles.role_description, user_roles.can_see_chat_history')
                                  ->join('user_roles', 'user_roles.role_name = chat_sessions.user_role', 'left')
                                  ->where('chat_sessions.session_id', $sessionId)
                                  ->first();
        
        if ($session) {
            return $this->jsonResponse($session);
        }
        
        return $this->jsonResponse(['error' => 'Session not found'], 404);
    }
    
    /**
     * Get chatroom link for frontend integration
     * This is the actual endpoint that generates chatroom links
     */
    public function getChatroomLink()
    {
        // Explicitly set CORS headers for this endpoint
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');
        header('Access-Control-Max-Age: 86400');
        
        try {
            // Get request data (support both POST and GET)
            $userId = $this->sanitizeInput($this->request->getPost('user_id')) ?: 
                     $this->sanitizeInput($this->request->getGet('user_id'));
            
            $sessionInfo = $this->sanitizeInput($this->request->getPost('session_info')) ?: 
                          $this->sanitizeInput($this->request->getGet('session_info'));
            
            $apiKey = $this->sanitizeInput($this->request->getPost('api_key')) ?: 
                     $this->sanitizeInput($this->request->getGet('api_key'));
            
            // Get domain for API key validation (if API key provided)
            $domain = $this->request->getServer('HTTP_ORIGIN') ?: $this->request->getServer('HTTP_REFERER');
            if ($domain) {
                $parsedUrl = parse_url($domain);
                $domain = $parsedUrl['host'] ?? $domain;
            }
            
            // Basic validation - at least user_id should be provided
            if (empty($userId)) {
                $userId = 'anonymous_' . uniqid();
            }
            
            // For now, generate a simple chatroom link based on user_id
            // This creates a direct link to the livechat system with the user context
            $baseUrl = rtrim(config('App')->baseURL, '/');
            
            // Generate chatroom parameters
            $chatroomParams = [
                'user_id' => $userId,
                'session_info' => $sessionInfo,
                'timestamp' => time(),
                'iframe' => '1' // Enable iframe mode for external integration
            ];
            
            // Add API key to params if provided
            if ($apiKey) {
                $chatroomParams['api_key'] = $apiKey;
            }
            
            // Add role information if it can be inferred
            if (!empty($sessionInfo) && strpos($sessionInfo, 'logged_user') !== false) {
                $chatroomParams['user_role'] = 'loggedUser';
                // Parse user info from session_info if structured
                if (strpos($sessionInfo, '|') !== false) {
                    $parts = explode('|', $sessionInfo);
                    foreach ($parts as $part) {
                        if (strpos($part, 'name:') === 0) {
                            $chatroomParams['external_fullname'] = substr($part, 5);
                        } elseif (strpos($part, 'username:') === 0) {
                            $chatroomParams['external_username'] = substr($part, 9);
                        } elseif (strpos($part, 'id:') === 0) {
                            $chatroomParams['external_system_id'] = substr($part, 3);
                        }
                    }
                }
            } else {
                $chatroomParams['user_role'] = 'anonymous';
            }
            
            // Build the chatroom URL
            $chatroomLink = $baseUrl . '/?' . http_build_query($chatroomParams);
            
            // Return successful response
            return $this->jsonResponse([
                'success' => true,
                'chatroom_link' => $chatroomLink,
                'user_id' => $userId,
                'timestamp' => time()
            ]);
            
        } catch (Exception $e) {
            // Log error but return a working fallback response
            error_log("getChatroomLink Error: " . $e->getMessage());
            
            $baseUrl = rtrim(config('App')->baseURL, '/');
            $fallbackUserId = $userId ?? 'anonymous_' . uniqid();
            
            return $this->jsonResponse([
                'success' => true,
                'chatroom_link' => $baseUrl . '/?user_id=' . urlencode($fallbackUserId) . '&iframe=1',
                'user_id' => $fallbackUserId,
                'timestamp' => time(),
                'note' => 'Fallback response - system error handled'
            ]);
        }
    }
    /**
     * Send message via API
     */
    public function sendMessage()
    {
        if (!$this->isAuthenticated()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $sessionId = $this->request->getPost('session_id');
        $message = $this->request->getPost('message');
        $messageType = $this->request->getPost('message_type') ?: 'text';
        $agentId = $this->session->get('user_id');

        if (!$sessionId || !$message) {
            return $this->jsonResponse(['error' => 'Session ID and message are required'], 400);
        }

        // Get chat session
        $chatSession = $this->chatModel->getSessionBySessionId($sessionId);
        if (!$chatSession) {
            return $this->jsonResponse(['error' => 'Chat session not found'], 404);
        }

        $messageData = [
            'session_id' => $chatSession['id'],
            'sender_type' => 'agent',
            'sender_id' => $agentId,
            'message' => $message,
            'message_type' => $messageType
        ];

        $messageId = $this->messageModel->insert($messageData);

        if ($messageId) {
            return $this->jsonResponse([
                'success' => true,
                'message_id' => $messageId
            ]);
        }

        return $this->jsonResponse(['error' => 'Failed to send message'], 500);
    }

    /**
     * Check chat status via API
     */
    public function checkStatus($sessionId)
    {
        $session = $this->chatModel->getSessionBySessionId($sessionId);
        
        if ($session) {
            return $this->jsonResponse([
                'status' => $session['status'],
                'session_id' => $sessionId,
                'agent_id' => $session['agent_id'] ?? null
            ]);
        }
        
        return $this->jsonResponse(['error' => 'Session not found'], 404);
    }

    /**
     * Send WebSocket notification to agents when customer leaves
     */
    private function notifyAgentsOfCustomerLeft($sessionId, $messageId)
    {
        // For now, we'll rely on the real-time message loading when admin refreshes
        // or we can implement a server-sent events or polling mechanism
        // The system message is already saved to database and will show in chat history
        
        // Future enhancement: Implement proper WebSocket broadcasting here
        // For now, the admin will see the message when they reload the chat history
    }
    
    /**
     * Send WebSocket notification to close session for all participants
     */
    private function notifySessionClosed($sessionId)
    {
        // This method can be extended to send WebSocket notifications
        // Currently, the session close will be detected when admin checks session status
        // or when the database is queried for session updates
        
        // Future enhancement: Send WebSocket message to close session for all connected clients
        // For now, admins will see the session as closed when they refresh or check status
    }
    
    /**
     * Helper method to get client ID from backend API
     */
    private function getClientIdFromBackend($clientEmail)
    {
        try {
            // Backend API endpoint URL
            $backendUrl = 'https://kiosk-chat.kopisugar.cc/api/client/get-id-by-email';
            
            $postData = [
                'email' => $clientEmail
            ];
            
            // Initialize cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $backendUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                curl_close($ch);
                error_log('Client ID lookup failed: cURL error - ' . curl_error($ch));
                return null;
            }
            
            curl_close($ch);
            
            if ($httpCode === 200) {
                $responseData = json_decode($response, true);
                if ($responseData && $responseData['success'] && isset($responseData['client_id'])) {
                    return $responseData['client_id'];
                }
            }
            
            error_log("Client ID lookup failed: HTTP {$httpCode}, Response: {$response}");
            return null;
            
        } catch (Exception $e) {
            error_log('Client ID lookup exception: ' . $e->getMessage());
            return null;
        }
    }
}
