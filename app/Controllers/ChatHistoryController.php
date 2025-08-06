<?php

namespace App\Controllers;

class ChatHistoryController extends General
{
    /**
     * Display chat history index page
     */
    public function index()
    {
        if (!$this->isAuthenticated()) {
            return redirect()->to('/login');
        }
        
        $perPage = 20;
        $page = $this->request->getVar('page') ?? 1;
        
        // Build query with filters
        $builder = $this->chatModel->builder();
        
        // Join with users table to get agent information
        $builder->select('
            chat_sessions.id,
            chat_sessions.session_id,
            chat_sessions.customer_name as username,
            chat_sessions.customer_fullname as fullname,
            chat_sessions.created_at,
            chat_sessions.closed_at,
            chat_sessions.status,
            users.username as agent_name,
            (SELECT created_at FROM messages 
             WHERE session_id = chat_sessions.id 
             AND sender_type = "customer" 
             ORDER BY created_at DESC LIMIT 1) as client_last_reply,
            (SELECT created_at FROM messages 
             WHERE session_id = chat_sessions.id 
             AND sender_type = "agent" 
             ORDER BY created_at DESC LIMIT 1) as agent_last_reply
        ');
        
        $builder->join('users', 'users.id = chat_sessions.agent_id', 'left');
        
        // Apply filters if provided
        if ($this->request->getVar('status')) {
            $builder->where('chat_sessions.status', $this->request->getVar('status'));
        }
        
        if ($this->request->getVar('date_from')) {
            $builder->where('chat_sessions.created_at >=', $this->request->getVar('date_from') . ' 00:00:00');
        }
        
        if ($this->request->getVar('date_to')) {
            $builder->where('chat_sessions.created_at <=', $this->request->getVar('date_to') . ' 23:59:59');
        }
        
        if ($this->request->getVar('search')) {
            $search = $this->request->getVar('search');
            $builder->groupStart()
                   ->like('chat_sessions.customer_name', $search)
                   ->orLike('chat_sessions.customer_fullname', $search)
                   ->orLike('users.username', $search)
                   ->groupEnd();
        }
        
        // Order by created_at DESC
        $builder->orderBy('chat_sessions.created_at', 'DESC');
        
        // Get results with pagination
        $offset = ($page - 1) * $perPage;
        $totalRecords = $builder->countAllResults(false);
        $results = $builder->limit($perPage, $offset)->get()->getResultArray();
        
        // Create pagination links
        $pager = \Config\Services::pager();
        $paginateData = $pager->makeLinks($page, $perPage, $totalRecords, 'default_full');
        
        $data = [
            'title' => 'Chat History',
            'user' => $this->getCurrentUser(),
            'chats' => $results,
            'pager' => $paginateData,
            'filters' => [
                'status' => $this->request->getVar('status'),
                'date_from' => $this->request->getVar('date_from'),
                'date_to' => $this->request->getVar('date_to'),
                'search' => $this->request->getVar('search')
            ]
        ];
        
        return view('chat_history/index', $data);
    }
    
    /**
     * View specific chat session with full message history
     */
    public function view($sessionId = null)
    {
        if (!$this->isAuthenticated()) {
            return redirect()->to('/login');
        }
        
        if (!$sessionId) {
            return redirect()->to('/chat-history')->with('error', 'Session ID is required');
        }
        
        // Get chat session details
        $chatSession = $this->chatModel->select('
            chat_sessions.*,
            users.username as agent_name,
            users.email as agent_email
        ')
        ->join('users', 'users.id = chat_sessions.agent_id', 'left')
        ->where('chat_sessions.id', $sessionId)
        ->first();
        
        if (!$chatSession) {
            return redirect()->to('/chat-history')->with('error', 'Chat session not found');
        }
        
        // Get all messages for this session
        $messages = $this->messageModel->select('
            messages.*,
            users.username as sender_name
        ')
        ->join('users', 'users.id = messages.sender_id', 'left')
        ->where('session_id', $sessionId)
        ->orderBy('created_at', 'ASC')
        ->findAll();
        
        // Calculate session duration if closed
        $duration = null;
        if ($chatSession['closed_at']) {
            $start = new \DateTime($chatSession['created_at']);
            $end = new \DateTime($chatSession['closed_at']);
            $duration = $start->diff($end);
        }
        
        // Get message statistics
        $messageStats = [
            'total_messages' => count($messages),
            'customer_messages' => count(array_filter($messages, function($msg) { return $msg['sender_type'] === 'customer'; })),
            'agent_messages' => count(array_filter($messages, function($msg) { return $msg['sender_type'] === 'agent'; })),
            'system_messages' => count(array_filter($messages, function($msg) { return $msg['message_type'] === 'system'; }))
        ];
        
        $data = [
            'title' => 'Chat Session #' . $sessionId,
            'user' => $this->getCurrentUser(),
            'chatSession' => $chatSession,
            'messages' => $messages,
            'duration' => $duration,
            'messageStats' => $messageStats
        ];
        
        return view('chat_history/view', $data);
    }
    
    /**
     * Export chat history to CSV
     */
    public function export()
    {
        if (!$this->isAuthenticated()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }
        
        // Build query similar to index but without pagination
        $builder = $this->chatModel->builder();
        
        $builder->select('
            chat_sessions.id,
            chat_sessions.session_id,
            chat_sessions.customer_name as username,
            chat_sessions.customer_fullname as fullname,
            chat_sessions.created_at,
            chat_sessions.closed_at,
            chat_sessions.status,
            users.username as agent_name
        ');
        
        $builder->join('users', 'users.id = chat_sessions.agent_id', 'left');
        
        // Apply same filters as index
        if ($this->request->getVar('status')) {
            $builder->where('chat_sessions.status', $this->request->getVar('status'));
        }
        
        if ($this->request->getVar('date_from')) {
            $builder->where('chat_sessions.created_at >=', $this->request->getVar('date_from') . ' 00:00:00');
        }
        
        if ($this->request->getVar('date_to')) {
            $builder->where('chat_sessions.created_at <=', $this->request->getVar('date_to') . ' 23:59:59');
        }
        
        $builder->orderBy('chat_sessions.created_at', 'DESC');
        
        $results = $builder->get()->getResultArray();
        
        // Generate CSV content
        $filename = 'chat_history_' . date('Y-m-d_H-i-s') . '.csv';
        
        $this->response->setHeader('Content-Type', 'text/csv');
        $this->response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Session ID',
            'Username', 
            'Full Name',
            'Agent',
            'Status',
            'Created At',
            'Closed At',
            'Duration (minutes)'
        ]);
        
        // CSV data rows
        foreach ($results as $row) {
            $duration = '';
            if ($row['closed_at']) {
                $start = new \DateTime($row['created_at']);
                $end = new \DateTime($row['closed_at']);
                $diff = $start->diff($end);
                $duration = $diff->days * 24 * 60 + $diff->h * 60 + $diff->i;
            }
            
            fputcsv($output, [
                $row['session_id'],
                $row['username'],
                $row['fullname'] ?? 'N/A',
                $row['agent_name'] ?? 'Unassigned',
                ucfirst($row['status']),
                $row['created_at'],
                $row['closed_at'] ?? 'N/A',
                $duration
            ]);
        }
        
        fclose($output);
        return $this->response;
    }
    
    /**
     * Get chat statistics for dashboard
     */
    public function getStats()
    {
        if (!$this->isAuthenticated()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }
        
        // Get various statistics
        $totalChats = $this->chatModel->countAll();
        $todayChats = $this->chatModel->where('DATE(created_at)', date('Y-m-d'))->countAllResults();
        $activeChats = $this->chatModel->where('status', 'active')->countAllResults();
        $avgDuration = $this->chatModel->select('AVG(TIMESTAMPDIFF(MINUTE, created_at, closed_at)) as avg_duration')
                                      ->where('status', 'closed')
                                      ->where('closed_at IS NOT NULL')
                                      ->first()['avg_duration'] ?? 0;
        
        return $this->jsonResponse([
            'total_chats' => $totalChats,
            'today_chats' => $todayChats,
            'active_chats' => $activeChats,
            'avg_duration' => round($avgDuration, 1)
        ]);
    }
}
