<?php

namespace App\Libraries;

class ChatQueue
{
    private $queueModel;
    private $chatModel;
    private $config;
    
    public function __construct()
    {
        $this->queueModel = new \App\Models\ChatQueueModel();
        $this->chatModel = new \App\Models\ChatModel();
        $this->config = config('Chat');
    }
    
    public function addToQueue($sessionId, $priority = 1)
    {
        // Check if queue is full
        $queueSize = $this->queueModel->countAllResults();
        if ($queueSize >= $this->config->maxQueueSize) {
            return false;
        }
        
        // Add to queue
        $result = $this->queueModel->addToQueue($sessionId, $priority);
        
        if ($result) {
            // Update chat session status
            $this->chatModel->where('session_id', $sessionId)
                           ->set(['status' => 'waiting'])
                           ->update();
            
            // Notify available agents
            $this->notifyAvailableAgents();
        }
        
        return $result;
    }
    
    public function removeFromQueue($sessionId)
    {
        $result = $this->queueModel->removeFromQueue($sessionId);
        
        if ($result) {
            // Reorder remaining queue
            $this->reorderQueue();
        }
        
        return $result;
    }
    
    public function getQueuePosition($sessionId)
    {
        $item = $this->queueModel->where('session_id', $sessionId)->first();
        return $item ? $item['queue_position'] : null;
    }
    
    public function assignNextInQueue($agentId)
    {
        // Get next priority item from queue
        $nextItem = $this->queueModel->orderBy('priority', 'DESC')
                                    ->orderBy('created_at', 'ASC')
                                    ->first();
        
        if (!$nextItem) {
            return null;
        }
        
        // Assign to agent
        $this->chatModel->where('session_id', $nextItem['session_id'])
                       ->set(['agent_id' => $agentId, 'status' => 'active'])
                       ->update();
        
        // Remove from queue
        $this->removeFromQueue($nextItem['session_id']);
        
        return $nextItem['session_id'];
    }
    
    private function reorderQueue()
    {
        $queue = $this->queueModel->orderBy('priority', 'DESC')
                                 ->orderBy('created_at', 'ASC')
                                 ->findAll();
        
        foreach ($queue as $index => $item) {
            $position = $index + 1;
            $estimatedWait = $position * $this->config->estimatedWaitPerPosition;
            
            $this->queueModel->update($item['id'], [
                'queue_position' => $position,
                'estimated_wait_time' => $estimatedWait
            ]);
        }
    }
    
    private function notifyAvailableAgents()
    {
        // This would integrate with your WebSocket server or notification system
        // For now, we'll just log it
        log_message('info', 'New chat added to queue - notifying available agents');
    }
}