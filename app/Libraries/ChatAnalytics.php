<?php

namespace App\Libraries;

class ChatAnalytics
{
    private $db;
    private $analyticsModel;
    
    public function __construct()
    {
        $this->db = \Config\Database::connect();
        $this->analyticsModel = new \App\Models\ChatAnalyticsModel();
    }
    
    public function recordSessionEnd($sessionId)
    {
        $this->analyticsModel->calculateSessionAnalytics($sessionId);
    }
    
    public function getDashboardStats($dateFrom = null, $dateTo = null)
    {
        if (!$dateFrom) $dateFrom = date('Y-m-d', strtotime('-30 days'));
        if (!$dateTo) $dateTo = date('Y-m-d');
        
        $stats = [];
        
        // Total sessions
        $stats['total_sessions'] = $this->db->table('chat_sessions')
            ->where('DATE(created_at) >=', $dateFrom)
            ->where('DATE(created_at) <=', $dateTo)
            ->countAllResults();
        
        // Average session duration
        $avgDuration = $this->db->table('chat_analytics')
            ->join('chat_sessions', 'chat_sessions.id = chat_analytics.session_id')
            ->where('DATE(chat_sessions.created_at) >=', $dateFrom)
            ->where('DATE(chat_sessions.created_at) <=', $dateTo)
            ->selectAvg('session_duration')
            ->get()
            ->getRow();
        
        $stats['avg_session_duration'] = $avgDuration ? round($avgDuration->session_duration / 60, 2) : 0;
        
        // Customer satisfaction
        $satisfaction = $this->db->table('chat_sessions')
            ->where('DATE(created_at) >=', $dateFrom)
            ->where('DATE(created_at) <=', $dateTo)
            ->where('rating IS NOT NULL')
            ->selectAvg('rating')
            ->get()
            ->getRow();
        
        $stats['avg_satisfaction'] = $satisfaction ? round($satisfaction->rating, 2) : 0;
        
        // Response times
        $responseTime = $this->db->table('chat_analytics')
            ->join('chat_sessions', 'chat_sessions.id = chat_analytics.session_id')
            ->where('DATE(chat_sessions.created_at) >=', $dateFrom)
            ->where('DATE(chat_sessions.created_at) <=', $dateTo)
            ->selectAvg('first_response_time')
            ->get()
            ->getRow();
        
        $stats['avg_first_response'] = $responseTime ? round($responseTime->first_response_time / 60, 2) : 0;
        
        return $stats;
    }
    
    public function getHourlyDistribution($dateFrom = null, $dateTo = null)
    {
        if (!$dateFrom) $dateFrom = date('Y-m-d', strtotime('-7 days'));
        if (!$dateTo) $dateTo = date('Y-m-d');
        
        return $this->db->query("
            SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as chat_count
            FROM chat_sessions 
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY HOUR(created_at)
            ORDER BY hour
        ", [$dateFrom, $dateTo])->getResultArray();
    }
    
    public function getTopPerformingAgents($limit = 10)
    {
        return $this->db->query("
            SELECT 
                u.username,
                COUNT(cs.id) as total_chats,
                AVG(cs.rating) as avg_rating,
                AVG(ca.first_response_time) as avg_first_response,
                AVG(ca.session_duration) as avg_duration
            FROM users u
            LEFT JOIN chat_sessions cs ON u.id = cs.agent_id
            LEFT JOIN chat_analytics ca ON cs.id = ca.session_id
            WHERE u.role IN ('admin', 'support')
            AND cs.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY u.id, u.username
            HAVING total_chats > 0
            ORDER BY avg_rating DESC, total_chats DESC
            LIMIT ?
        ", [$limit])->getResultArray();
    }
}