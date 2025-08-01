<?php

// app/Config/Chat.php - Chat system configuration
namespace Config;

use CodeIgniter\Config\BaseConfig;

class Chat extends BaseConfig
{

    

    
    // Session settings
    public int $sessionTimeout = 1800; // 30 minutes
    public int $inactiveSessionTimeout = 3600; // 1 hour
    public bool $autoCloseInactiveSessions = true;
    
    // Agent settings
    public int $defaultMaxConcurrentChats = 5;
    public array $agentStatuses = ['available', 'busy', 'away'];

    
    // Business hours (24-hour format)
    public string $businessHoursStart = '09:00';
    public string $businessHoursEnd = '17:00';
    public array $businessDays = [1, 2, 3, 4, 5]; // Monday to Friday
    public string $timezone = 'Asia/Kuala_Lumpur';
    
    // WebSocket settings
    public string $websocketHost = 'localhost';
    public int $websocketPort = 8081;
    public int $heartbeatInterval = 30; // seconds
    public int $reconnectAttempts = 5;
    
    // Notification settings
    public bool $enableEmailNotifications = true;
    public bool $enableSlackIntegration = false;
    public bool $enablePushNotifications = false;
    
    // Analytics settings
    public bool $enableAnalytics = true;
    public bool $trackResponseTimes = true;
    public bool $trackCustomerSatisfaction = true;
    public int $analyticsRetentionDays = 365;
}
