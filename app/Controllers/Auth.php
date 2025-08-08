<?php

namespace App\Controllers;

class Auth extends BaseController
{
    public function login()
    {
        // If already logged in, redirect to backend admin dashboard
        if ($this->session->has('user_id')) {
            $config = new \Config\App();
            return redirect()->to($config->backendURL . '/admin');
        }
        
        return view('auth/login');
    }
    
    public function attemptLogin()
    {
        $username = $this->request->getPost('username');
        $password = $this->request->getPost('password');
        
        if (!$username || !$password) {
            return redirect()->back()->with('error', 'Username and password are required');
        }
        
        // Find user by username
        $user = $this->userModel->where('username', $username)->first();
        
        if (!$user) {
            return redirect()->back()->with('error', 'Invalid credentials');
        }
        
        // Check if user is admin or support
        if (!in_array($user['role'], ['admin', 'support'])) {
            return redirect()->back()->with('error', 'Access denied');
        }
        
        // Verify password (assuming passwords are hashed)
        if (password_verify($password, $user['password'])) {
            // Set session
            $this->session->set([
                'user_id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ]);
            
            // Redirect to backend admin dashboard
            $config = new \Config\App();
            return redirect()->to($config->backendURL . '/admin');
        } else {
            return redirect()->back()->with('error', 'Invalid credentials');
        }
    }
    
    public function logout()
    {
        $this->session->destroy();
        return redirect()->to('/login');
    }
} 