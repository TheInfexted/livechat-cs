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
        
        // Additional admin dashboard functionality can be added here
        $data = [
            'title' => 'Admin Dashboard',
            'user' => $this->getCurrentUser(),
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
        
        $data = [
            'title' => 'Manage Agents',
            'agents' => $this->userModel->whereIn('role', ['admin', 'support'])->findAll()
        ];
        
        return view('admin/agents', $data);
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
}