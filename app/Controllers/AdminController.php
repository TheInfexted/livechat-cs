<?php

namespace App\Controllers;

class AdminController extends General
{
    public function chat()
    {
        if (!$this->isAuthenticated()) {
            return redirect()->to('/login');
        }
        
        $data = [
            'title' => 'Admin Chat Dashboard',
            'user' => $this->getCurrentUser(),
            'activeSessions' => $this->chatModel->getActiveSessions(),
            'waitingSessions' => $this->chatModel->getWaitingSessions()
        ];
        
        return view('chat/admin', $data);
    }
    
    public function dashboard()
    {
        if (!$this->isAuthenticated()) {
            return redirect()->to('/login');
        }
        
        $currentUser = $this->getCurrentUser();
        
        // Additional admin dashboard functionality can be added here
        $data = [
            'title' => $currentUser['role'] === 'admin' ? 'Admin Dashboard' : 'Support Dashboard',
            'user' => $currentUser,
            'totalSessions' => $this->chatModel->countAll(),
            'activeSessions' => $this->chatModel->where('status', 'active')->countAllResults(),
            'waitingSessions' => $this->chatModel->where('status', 'waiting')->countAllResults(),
            'closedSessions' => $this->chatModel->where('status', 'closed')->countAllResults()
        ];
        
        return view('admin/dashboard', $data);
    }
    
    public function agents()
    {
        if (!$this->isAuthenticated()) {
            return redirect()->to('/login');
        }
        
        // Only admins can access agent management
        if (!$this->isAdmin()) {
            return redirect()->to('/admin')->with('error', 'Access denied. Only administrators can manage agents.');
        }
        
        $data = [
            'title' => 'Manage Agents',
            'user' => $this->getCurrentUser(),
            'agents' => $this->userModel->whereIn('role', ['admin', 'support'])->findAll()
        ];
        
        return view('admin/agents', $data);
    }
    
    public function editAgent()
    {
        if (!$this->isAuthenticated()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }
        
        // Only admins can edit agents
        if (!$this->isAdmin()) {
            return $this->jsonResponse(['error' => 'Access denied. Only administrators can edit agents.'], 403);
        }
        
        $agentId = $this->request->getPost('agent_id');
        $username = $this->request->getPost('username');
        $email = $this->request->getPost('email');
        $role = $this->request->getPost('role');
        
        if (!$agentId || !$username || !$email || !$role) {
            return $this->jsonResponse(['error' => 'Missing required fields'], 400);
        }
        
        // Validate role
        if (!in_array($role, ['admin', 'support'])) {
            return $this->jsonResponse(['error' => 'Invalid role'], 400);
        }
        
        $data = [
            'username' => $username,
            'email' => $email,
            'role' => $role
        ];
        
        $updated = $this->userModel->update($agentId, $data);
        
        if ($updated) {
            return $this->jsonResponse(['success' => true, 'message' => 'Agent updated successfully']);
        }
        
        return $this->jsonResponse(['error' => 'Failed to update agent'], 500);
    }
    
    public function deleteAgent()
    {
        if (!$this->isAuthenticated()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }
        
        // Only admins can delete agents
        if (!$this->isAdmin()) {
            return $this->jsonResponse(['error' => 'Access denied. Only administrators can delete agents.'], 403);
        }
        
        $agentId = $this->request->getPost('agent_id');
        
        if (!$agentId) {
            return $this->jsonResponse(['error' => 'Agent ID is required'], 400);
        }
        
        // Prevent self-deletion
        $currentUser = $this->getCurrentUser();
        if ($agentId == $currentUser['id']) {
            return $this->jsonResponse(['error' => 'You cannot delete your own account'], 400);
        }
        
        $deleted = $this->userModel->delete($agentId);
        
        if ($deleted) {
            return $this->jsonResponse(['success' => true, 'message' => 'Agent deleted successfully']);
        }
        
        return $this->jsonResponse(['error' => 'Failed to delete agent'], 500);
    }
    
    public function reports()
    {
        if (!$this->isAuthenticated()) {
            return redirect()->to('/login');
        }
        
        // Add reporting functionality
        $data = [
            'title' => 'Chat Reports',
            'user' => $this->getCurrentUser()
        ];
        
        return view('admin/reports', $data);
    }

    // Advanced analytics
    public function analytics()
    {
        if (!$this->isAuthenticated()) {
            return redirect()->to('/login');
        }

        $data = [
            'title' => 'Chat Analytics',
            'user' => $this->getCurrentUser()
        ];

        return view('admin/analytics', $data);
    }

    // Manage canned responses
    public function cannedResponses()
    {
        if (!$this->isAuthenticated()) {
            return redirect()->to('/login');
        }

        $data = [
            'title' => 'Canned Responses',
            'responses' => $this->cannedResponseModel->findAll()
        ];

        return view('admin/canned_responses', $data);
    }

    // Save canned response
    public function saveCannedResponse()
    {
        if (!$this->isAuthenticated()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $data = [
            'title' => $this->request->getPost('title'),
            'content' => $this->request->getPost('content'),
            'category' => $this->request->getPost('category'),
            'is_global' => $this->request->getPost('is_global') ? 1 : 0,
            'agent_id' => $this->request->getPost('is_global') ? null : $this->session->get('user_id')
        ];

        $id = $this->cannedResponseModel->insert($data);

        if ($id) {
            return $this->jsonResponse(['success' => true, 'id' => $id]);
        }

        return $this->jsonResponse(['error' => 'Failed to save response'], 500);
    }

    // System settings
    public function settings()
    {
        if (!$this->isAuthenticated()) {
            return redirect()->to('/login');
        }

        $data = [
            'title' => 'System Settings',
            'settings' => $this->getSystemSettings()
        ];

        return view('admin/settings', $data);
    }

    private function getSystemSettings()
    {
        // This would typically come from a settings table
        return [
            'max_queue_size' => 50,
            'auto_close_inactive' => 30, // minutes
            'file_upload_max_size' => 5, // MB
            'allowed_file_types' => ['jpg', 'png', 'pdf', 'txt'],
            'business_hours_start' => '09:00',
            'business_hours_end' => '17:00',
            'timezone' => 'Asia/Kuala_Lumpur'
        ];
    }
}