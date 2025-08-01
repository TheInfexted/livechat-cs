<?php

namespace App\Models;

use CodeIgniter\Model;

class CannedResponseModel extends Model
{
    protected $table = 'canned_responses';
    protected $primaryKey = 'id';
    protected $allowedFields = ['title', 'content', 'category', 'agent_id', 'is_global'];
    
    public function getGlobalResponses()
    {
        return $this->where('is_global', 1)->findAll();
    }
    
    public function getAgentResponses($agentId)
    {
        return $this->where('agent_id', $agentId)->findAll();
    }
    
    public function getByCategory($category)
    {
        return $this->where('category', $category)->findAll();
    }
    
    public function getAvailableResponses($agentId)
    {
        return $this->groupStart()
                    ->where('is_global', 1)
                    ->orWhere('agent_id', $agentId)
                    ->groupEnd()
                    ->orderBy('category')
                    ->orderBy('title')
                    ->findAll();
    }
}