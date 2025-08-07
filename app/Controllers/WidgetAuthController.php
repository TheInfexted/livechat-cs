<?php

namespace App\Controllers;

use App\Models\ApiKeyModel;

class WidgetAuthController extends BaseController
{
    protected $apiKeyModel;
    
    public function __construct()
    {
        $this->apiKeyModel = new ApiKeyModel();
    }
    
    public function validateWidget()
    {
        // Handle JSON request body
        $input = $this->request->getJSON(true); // Get as associative array
        
        // Handle both POST and GET requests for flexibility
        $apiKey = $input['api_key'] ?? $this->request->getPost('api_key') ?? $this->request->getGet('api_key');
        $domain = $input['domain'] ?? $this->request->getPost('domain') ?? $this->request->getServer('HTTP_ORIGIN');
        
        if (!$apiKey) {
            return $this->response->setJSON([
                'valid' => false,
                'error' => 'API key is required'
            ])->setStatusCode(400);
        }
        
        // Parse domain from URL if needed
        if ($domain) {
            $parsedUrl = parse_url($domain);
            $domain = $parsedUrl['host'] ?? $domain;
        }
        
        $validation = $this->apiKeyModel->validateApiKey($apiKey, $domain);
        
        if (!$validation['valid']) {
            return $this->response->setJSON($validation)->setStatusCode(403);
        }
        
        $keyData = $validation['key_data'];
        
        return $this->response->setJSON([
            'valid' => true,
            'client_name' => $keyData['client_name']
        ]);
    }
    
    public function validateChatStart()
    {
        $apiKey = $this->request->getPost('api_key');
        $sessionId = $this->request->getPost('session_id');
        
        $domain = $this->request->getServer('HTTP_ORIGIN');
        if ($domain) {
            $parsedUrl = parse_url($domain);
            $domain = $parsedUrl['host'] ?? $domain;
        }
        
        if (!$apiKey) {
            return $this->response->setJSON([
                'valid' => false,
                'error' => 'API key is required'
            ])->setStatusCode(400);
        }
        
        $validation = $this->apiKeyModel->validateApiKey($apiKey, $domain);
        
        if (!$validation['valid']) {
            return $this->response->setJSON($validation)->setStatusCode(403);
        }
        
        $keyData = $validation['key_data'];
        
        return $this->response->setJSON(['valid' => true]);
    }
    
    public function logMessageSent()
    {
        $apiKey = $this->request->getPost('api_key');
        $sessionId = $this->request->getPost('session_id');
        
        $domain = $this->request->getServer('HTTP_ORIGIN');
        if ($domain) {
            $parsedUrl = parse_url($domain);
            $domain = $parsedUrl['host'] ?? $domain;
        }
        
        if (!$apiKey) {
            return $this->response->setJSON([
                'valid' => false,
                'error' => 'API key is required'
            ])->setStatusCode(400);
        }
        
        $validation = $this->apiKeyModel->validateApiKey($apiKey, $domain);
        
        if (!$validation['valid']) {
            return $this->response->setJSON($validation)->setStatusCode(403);
        }
        
        $keyData = $validation['key_data'];
        
        return $this->response->setJSON(['valid' => true]);
    }
}
