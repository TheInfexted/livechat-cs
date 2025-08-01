<?php

namespace App\Models;

use CodeIgniter\Model;

class ChatAnalyticsModel extends Model
{
    protected $table = 'chat_analytics';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'session_id', 'total_messages', 'customer_messages', 'agent_messages',
        'session_duration', 'first_response_time', 'avg_response_time', 'resolution_time'
    ];
    
    public function calculateSessionAnalytics($sessionId)
    {
        // Get chat session details
        $chatModel = new ChatModel();
        $session = $chatModel->find($sessionId);
        
        if (!$session) {
            return false;
        }
        
        // Get all messages for this session
        $messageModel = new MessageModel();
        $messages = $messageModel->where('session_id', $sessionId)
                                ->orderBy('created_at', 'ASC')
                                ->findAll();
        
        if (empty($messages)) {
            return false;
        }
        
        $totalMessages = count($messages);
        $customerMessages = array_filter($messages, fn($m) => $m['sender_type'] === 'customer');
        $agentMessages = array_filter($messages, fn($m) => $m['sender_type'] === 'agent');
        
        // Calculate response times
        $responseTimes = [];
        $firstResponseTime = null;
        
        for ($i = 0; $i < count($messages) - 1; $i++) {
            $current = $messages[$i];
            $next = $messages[$i + 1];
            
            if ($current['sender_type'] === 'customer' && $next['sender_type'] === 'agent') {
                $responseTime = strtotime($next['created_at']) - strtotime($current['created_at']);
                $responseTimes[] = $responseTime;
                
                if ($firstResponseTime === null) {
                    $firstResponseTime = $responseTime;
                }
            }
        }
        
        $sessionDuration = $session['closed_at'] 
            ? strtotime($session['closed_at']) - strtotime($session['created_at'])
            : time() - strtotime($session['created_at']);
        
        $analytics = [
            'session_id' => $sessionId,
            'total_messages' => $totalMessages,
            'customer_messages' => count($customerMessages),
            'agent_messages' => count($agentMessages),
            'session_duration' => $sessionDuration,
            'first_response_time' => $firstResponseTime ?? 0,
            'avg_response_time' => empty($responseTimes) ? 0 : array_sum($responseTimes) / count($responseTimes),
            'resolution_time' => $session['status'] === 'closed' ? $sessionDuration : 0
        ];
        
        // Insert or update analytics
        $existing = $this->where('session_id', $sessionId)->first();
        if ($existing) {
            $this->update($existing['id'], $analytics);
        } else {
            $this->insert($analytics);
        }
        
        return $analytics;
    }
}