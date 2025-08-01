<?php

namespace App\Models;

use CodeIgniter\Model;

class ChatQueueModel extends Model
{
    protected $table = 'chat_queue';
    protected $primaryKey = 'id';
    protected $allowedFields = ['session_id', 'priority', 'estimated_wait_time', 'queue_position'];
    
    public function addToQueue($sessionId, $priority = 1)
    {
        $position = $this->getNextPosition();
        $estimatedWait = $this->calculateEstimatedWait($position);
        
        return $this->insert([
            'session_id' => $sessionId,
            'priority' => $priority,
            'queue_position' => $position,
            'estimated_wait_time' => $estimatedWait
        ]);
    }
    
    public function removeFromQueue($sessionId)
    {
        $removed = $this->where('session_id', $sessionId)->delete();
        if ($removed) {
            $this->reorderQueue();
        }
        return $removed;
    }
    
    private function getNextPosition()
    {
        $lastPosition = $this->selectMax('queue_position')->first();
        return ($lastPosition['queue_position'] ?? 0) + 1;
    }
    
    private function reorderQueue()
    {
        $queue = $this->orderBy('priority', 'DESC')
                     ->orderBy('created_at', 'ASC')
                     ->findAll();
        
        foreach ($queue as $index => $item) {
            $this->update($item['id'], ['queue_position' => $index + 1]);
        }
    }
    
    private function calculateEstimatedWait($position)
    {
        // Simple calculation: 5 minutes per position
        return $position * 5;
    }
}