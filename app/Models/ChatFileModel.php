<?php

namespace App\Models;

use CodeIgniter\Model;

class ChatFileModel extends Model
{
    protected $table = 'chat_files';
    protected $primaryKey = 'id';
    protected $allowedFields = ['message_id', 'original_name', 'file_path', 'file_size', 'mime_type'];
    
    public function getMessageFiles($messageId)
    {
        return $this->where('message_id', $messageId)->findAll();
    }
    
    public function getSessionFiles($sessionId)
    {
        return $this->select('chat_files.*, messages.created_at as uploaded_at')
                    ->join('messages', 'messages.id = chat_files.message_id')
                    ->join('chat_sessions', 'chat_sessions.id = messages.session_id')
                    ->where('chat_sessions.session_id', $sessionId)
                    ->orderBy('messages.created_at', 'DESC')
                    ->findAll();
    }
    
    public function deleteFile($fileId)
    {
        $file = $this->find($fileId);
        if ($file && file_exists(WRITEPATH . $file['file_path'])) {
            unlink(WRITEPATH . $file['file_path']);
        }
        return $this->delete($fileId);
    }
}