<?php

if (!function_exists('formatChatDate')) {
    /**
     * Format date for chat display
     * 
     * @param string $timestamp
     * @return string
     */
    function formatChatDate($timestamp) {
        $date = new DateTime($timestamp);
        $today = new DateTime();
        $yesterday = new DateTime('-1 day');
        
        if ($date->format('Y-m-d') === $today->format('Y-m-d')) {
            return 'Today, ' . $date->format('d-m-Y');
        } elseif ($date->format('Y-m-d') === $yesterday->format('Y-m-d')) {
            return 'Yesterday, ' . $date->format('d-m-Y');
        } else {
            return $date->format('l, M j Y'); // Sunday, Aug 17 2025
        }
    }
}

if (!function_exists('groupMessagesByDate')) {
    /**
     * Group messages by date and add date separators
     * 
     * @param array $messages
     * @return array
     */
    function groupMessagesByDate($messages) {
        $groupedMessages = [];
        $currentDate = '';
        
        foreach ($messages as $message) {
            $messageDate = date('Y-m-d', strtotime($message['created_at']));
            
            if ($messageDate !== $currentDate) {
                $currentDate = $messageDate;
                
                // Add date separator
                $groupedMessages[] = [
                    'type' => 'date_separator',
                    'date' => formatChatDate($message['created_at']),
                    'created_at' => $message['created_at'],
                    'id' => 'date_' . $messageDate
                ];
            }
            
            $groupedMessages[] = $message;
        }
        
        return $groupedMessages;
    }
}

if (!function_exists('shouldShowDateSeparator')) {
    /**
     * Check if a date separator should be shown between messages
     * 
     * @param array $currentMessage
     * @param array|null $previousMessage
     * @return bool
     */
    function shouldShowDateSeparator($currentMessage, $previousMessage) {
        if (!$previousMessage) {
            return true; // Always show for first message
        }
        
        $currentDate = date('Y-m-d', strtotime($currentMessage['created_at']));
        $previousDate = date('Y-m-d', strtotime($previousMessage['created_at']));
        
        return $currentDate !== $previousDate;
    }
}

if (!function_exists('addDateSeparatorsToMessages')) {
    /**
     * Add date separators to a flat array of messages
     * This is useful for views that need to display messages with separators
     * 
     * @param array $messages
     * @return array
     */
    function addDateSeparatorsToMessages($messages) {
        if (empty($messages)) {
            return [];
        }
        
        $result = [];
        $previousMessage = null;
        
        foreach ($messages as $message) {
            // Check if we need a date separator
            if (shouldShowDateSeparator($message, $previousMessage)) {
                $result[] = [
                    'type' => 'date_separator',
                    'date' => formatChatDate($message['created_at']),
                    'created_at' => $message['created_at'],
                    'id' => 'date_' . date('Y-m-d', strtotime($message['created_at']))
                ];
            }
            
            $result[] = $message;
            $previousMessage = $message;
        }
        
        return $result;
    }
}