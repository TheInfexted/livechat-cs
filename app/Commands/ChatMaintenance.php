<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ChatMaintenance extends BaseCommand
{
    protected $group = 'chat';
    protected $name = 'chat:maintenance';
    protected $description = 'Performs maintenance tasks for the chat system';
    
    protected $usage = 'chat:maintenance [options]';
    protected $arguments = [];
    protected $options = [
        '--cleanup' => 'Clean up old sessions and files',
        '--analytics' => 'Update analytics data',

    ];
    
    public function run(array $params)
    {
        $cleanup = CLI::getOption('cleanup');
        $analytics = CLI::getOption('analytics');
        if (!$cleanup && !$analytics) {
            // Run all maintenance tasks
            $this->cleanupOldSessions();
            $this->updateAnalytics();
        } else {
            if ($cleanup) $this->cleanupOldSessions();
            if ($analytics) $this->updateAnalytics();
        }
        
        CLI::write('Chat maintenance completed successfully!', 'green');
    }
    
    private function cleanupOldSessions()
    {
        CLI::write('Cleaning up old sessions...', 'yellow');
        
        $chatModel = new \App\Models\ChatModel();
        
        // Close sessions inactive for more than 2 hours
        $inactiveCutoff = date('Y-m-d H:i:s', strtotime('-2 hours'));
        $updated = $chatModel->where('status', 'active')
                            ->where('updated_at <', $inactiveCutoff)
                            ->set(['status' => 'closed', 'closed_at' => date('Y-m-d H:i:s')])
                            ->update();
        
        CLI::write("Closed {$updated} inactive sessions", 'green');
        
        // Delete old files (older than 90 days)
        $oldFileCutoff = date('Y-m-d H:i:s', strtotime('-90 days'));
        $fileModel = new \App\Models\ChatFileModel();
        $oldFiles = $fileModel->where('uploaded_at <', $oldFileCutoff)->findAll();
        
        $deletedFiles = 0;
        foreach ($oldFiles as $file) {
            if (file_exists(WRITEPATH . $file['file_path'])) {
                unlink(WRITEPATH . $file['file_path']);
                $deletedFiles++;
            }
        }
        
        $fileModel->where('uploaded_at <', $oldFileCutoff)->delete();
        CLI::write("Deleted {$deletedFiles} old files", 'green');
    }
    
    private function updateAnalytics()
    {
        CLI::write('Updating analytics data...', 'yellow');
        
        $analyticsModel = new \App\Models\ChatAnalyticsModel();
        $chatModel = new \App\Models\ChatModel();
        
        // Get completed sessions without analytics
        $sessions = $chatModel->select('id')
                             ->where('status', 'closed')
                             ->whereNotExists(function($builder) {
                                 $builder->select('1')
                                        ->from('chat_analytics')
                                        ->where('chat_analytics.session_id = chat_sessions.id');
                             })
                             ->findAll();
        
        $processed = 0;
        foreach ($sessions as $session) {
            $analyticsModel->calculateSessionAnalytics($session['id']);
            $processed++;
        }
        
        CLI::write("Processed analytics for {$processed} sessions", 'green');
        

    }
    

}