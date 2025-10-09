/**
 * Voice Recording for Live Chat
 * Supports recording, playback, and sending voice messages
 */

// Voice recording state
let mediaRecorder = null;
let audioChunks = [];
let recordingStartTime = null;
let recordingTimer = null;
let recordedAudioBlob = null;
let recordingDuration = 0;
const MAX_RECORDING_DURATION = 30; // 30 seconds

/**
 * Check microphone permission status
 */
async function checkMicrophonePermission() {
    try {
        // Check if getUserMedia is supported
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            return 'not-supported';
        }
        
        // Try to get permission state
        if (navigator.permissions && navigator.permissions.query) {
            const permission = await navigator.permissions.query({ name: 'microphone' });
            return permission.state; // 'granted', 'denied', or 'prompt'
        }
        
        // Fallback: try to access microphone briefly
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        stream.getTracks().forEach(track => track.stop()); // Stop immediately
        return 'granted';
    } catch (error) {
        if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
            return 'denied';
        }
        return 'prompt'; // Default to prompt state
    }
}

/**
 * Initialize voice recording UI based on permission status
 */
async function initializeVoiceRecording() {
    
    const permissionStatus = await checkMicrophonePermission();
    const voiceBtn = document.getElementById('voiceRecordBtn') || document.getElementById('voice-record-btn');
    
    if (!voiceBtn) return;
    
    // Check if we're in iframe mode
    const isIframe = window.self !== window.top;
    
    if (isIframe) {
        // Try to enable voice recording in iframe mode
        voiceBtn.disabled = false;
        voiceBtn.title = 'Record voice message (may require additional permissions in iframe)';
        voiceBtn.style.opacity = '1';
        voiceBtn.style.cursor = 'pointer';
    } else if (permissionStatus === 'denied') {
        // Show that permission is needed
        voiceBtn.title = 'Microphone access denied. Click to see how to enable it.';
        voiceBtn.style.opacity = '0.6';
    } else if (permissionStatus === 'granted') {
        // Ready to record
        voiceBtn.title = 'Record voice message';
        voiceBtn.style.opacity = '1';
    } else {
        // Will prompt when clicked
        voiceBtn.title = 'Record voice message (click to allow microphone access)';
        voiceBtn.style.opacity = '1';
    }
}

/**
 * Toggle voice recording on/off
 */
function toggleVoiceRecording() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        // Stop recording
        stopVoiceRecording();
    } else {
        // Start recording
        startVoiceRecording();
    }
}

/**
 * Start voice recording
 */
async function startVoiceRecording() {
    try {
        // Request microphone permission
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        
        // Initialize MediaRecorder
        const options = { mimeType: 'audio/webm' };
        
        // Fallback for browsers that don't support webm
        if (!MediaRecorder.isTypeSupported('audio/webm')) {
            if (MediaRecorder.isTypeSupported('audio/mp4')) {
                options.mimeType = 'audio/mp4';
            } else if (MediaRecorder.isTypeSupported('audio/ogg')) {
                options.mimeType = 'audio/ogg';
            } else {
                options.mimeType = ''; // Let browser decide
            }
        }
        
        mediaRecorder = new MediaRecorder(stream, options);
        audioChunks = [];
        
        // Handle data available event
        mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                audioChunks.push(event.data);
            }
        };
        
        // Handle recording stop
        mediaRecorder.onstop = () => {
            // Stop all audio tracks
            stream.getTracks().forEach(track => track.stop());
            
            // Create blob from chunks
            const mimeType = mediaRecorder.mimeType || 'audio/webm';
            recordedAudioBlob = new Blob(audioChunks, { type: mimeType });
            
            // Show preview and confirmation
            showVoicePreview(recordingDuration);
        };
        
        // Start recording
        mediaRecorder.start();
        recordingStartTime = Date.now();
        
        // Update UI
        showRecordingUI();
        startRecordingTimer();
        
        // Disable text input during recording (handle both customer and client interfaces)
        const messageInput = document.getElementById('messageInput') || document.getElementById('message-input');
        if (messageInput) {
            messageInput.disabled = true;
            messageInput.placeholder = 'Recording voice message...';
        }
        
        // Change voice button appearance (handle both customer and client interfaces)
        const voiceBtn = document.getElementById('voiceRecordBtn') || document.getElementById('voice-record-btn');
        if (voiceBtn) {
            voiceBtn.classList.add('recording');
            voiceBtn.title = 'Stop recording';
        }
        
    } catch (error) {
        console.error('Error accessing microphone:', error);
        
        if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
            // Check if this is a permissions policy violation
            const isIframe = window.self !== window.top;
            let instructions = '';
            
            if (isIframe) {
                instructions = '\n\nVoice recording in iframe mode requires special setup.\n\nTo enable voice recording:\n1. Ask your website administrator to add allow="microphone" to the iframe tag\n2. Or open the chat in a new tab/window for full functionality\n3. Or try refreshing the page after allowing microphone access';
            } else {
                // Show browser-specific instructions
                const userAgent = navigator.userAgent.toLowerCase();
                
                if (userAgent.includes('chrome')) {
                    instructions = '\n\nTo enable microphone access in Chrome:\n1. Click the microphone icon in the address bar\n2. Select "Allow" for this site\n3. Refresh the page';
                } else if (userAgent.includes('firefox')) {
                    instructions = '\n\nTo enable microphone access in Firefox:\n1. Click the shield icon in the address bar\n2. Select "Allow" for microphone\n3. Refresh the page';
                } else if (userAgent.includes('safari')) {
                    instructions = '\n\nTo enable microphone access in Safari:\n1. Go to Safari > Preferences > Websites > Microphone\n2. Allow microphone for this site\n3. Refresh the page';
                } else {
                    instructions = '\n\nPlease check your browser settings to allow microphone access for this site.';
                }
            }
            
            alert('Microphone access denied. Please allow microphone access to record voice messages.' + instructions);
        } else if (error.name === 'NotFoundError') {
            alert('No microphone found. Please connect a microphone to record voice messages.');
        } else if (error.name === 'NotSupportedError') {
            alert('Voice recording is not supported in your browser. Please use a modern browser like Chrome, Firefox, or Safari.');
        } else {
            alert('Failed to access microphone: ' + error.message);
        }
        
        // Shake the button to indicate error
        const voiceBtn = document.getElementById('voiceRecordBtn') || document.getElementById('voice-record-btn');
        if (voiceBtn) {
            voiceBtn.classList.add('mic-permission-needed');
            setTimeout(() => voiceBtn.classList.remove('mic-permission-needed'), 500);
        }
    }
}

/**
 * Stop voice recording
 */
function stopVoiceRecording() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
        stopRecordingTimer();
        hideRecordingUI();
        
        // Re-enable text input
        const messageInput = document.getElementById('messageInput') || document.getElementById('message-input');
        if (messageInput) {
            messageInput.disabled = false;
            messageInput.placeholder = 'Type your message...';
        }
        
        // Reset voice button appearance
        const voiceBtn = document.getElementById('voiceRecordBtn') || document.getElementById('voice-record-btn');
        if (voiceBtn) {
            voiceBtn.classList.remove('recording');
            voiceBtn.title = 'Record voice message';
        }
    }
}

/**
 * Cancel voice recording without saving
 */
function cancelVoiceRecording() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        // Stop the recording
        const stream = mediaRecorder.stream;
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
        
        // Reset state without triggering onstop handler
        mediaRecorder.onstop = null;
        mediaRecorder.stop();
        
        // Clean up
        audioChunks = [];
        recordedAudioBlob = null;
        recordingDuration = 0;
        
        stopRecordingTimer();
        hideRecordingUI();
        
        // Re-enable text input
        const messageInput = document.getElementById('messageInput') || document.getElementById('message-input');
        if (messageInput) {
            messageInput.disabled = false;
            messageInput.placeholder = 'Type your message...';
        }
        
        // Reset voice button appearance
        const voiceBtn = document.getElementById('voiceRecordBtn') || document.getElementById('voice-record-btn');
        if (voiceBtn) {
            voiceBtn.classList.remove('recording');
            voiceBtn.title = 'Record voice message';
        }
        
    }
}

/**
 * Show recording UI
 */
function showRecordingUI() {
    const recordingUI = document.getElementById('voiceRecordingUI') || document.getElementById('voice-recording-ui');
    if (recordingUI) {
        recordingUI.style.display = 'block';
    }
}

/**
 * Hide recording UI
 */
function hideRecordingUI() {
    const recordingUI = document.getElementById('voiceRecordingUI') || document.getElementById('voice-recording-ui');
    if (recordingUI) {
        recordingUI.style.display = 'none';
    }
    
    // Reset timer display (handle both customer and client interfaces)
    const timerDisplay = document.getElementById('recordingTimer') || document.getElementById('recording-timer');
    if (timerDisplay) {
        timerDisplay.textContent = '00:00';
    }
}

/**
 * Start recording timer
 */
function startRecordingTimer() {
    const timerDisplay = document.getElementById('recordingTimer') || document.getElementById('recording-timer');
    
    recordingTimer = setInterval(() => {
        const elapsed = Math.floor((Date.now() - recordingStartTime) / 1000);
        recordingDuration = elapsed;
        
        // Format time as MM:SS
        const minutes = Math.floor(elapsed / 60);
        const seconds = elapsed % 60;
        const timeString = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        
        if (timerDisplay) {
            timerDisplay.textContent = timeString;
        }
        
        // Auto-stop at max duration
        if (elapsed >= MAX_RECORDING_DURATION) {
            stopVoiceRecording();
            alert(`Maximum recording duration of ${MAX_RECORDING_DURATION} seconds reached.`);
        }
    }, 1000);
}

/**
 * Stop recording timer
 */
function stopRecordingTimer() {
    if (recordingTimer) {
        clearInterval(recordingTimer);
        recordingTimer = null;
    }
}

/**
 * Show voice preview with confirmation
 */
function showVoicePreview(duration) {
    const voicePreview = document.getElementById('voicePreview') || document.getElementById('voice-preview');
    const voiceDuration = document.getElementById('voiceDuration') || document.getElementById('voice-duration');
    
    if (voicePreview && voiceDuration) {
        // Format duration
        const minutes = Math.floor(duration / 60);
        const seconds = duration % 60;
        const durationString = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        
        voiceDuration.textContent = durationString;
        voicePreview.style.display = 'block';
        
        // Show confirmation dialog
        setTimeout(() => {
            if (confirm('Send this voice message?')) {
                sendVoiceMessage();
            } else {
                removeVoicePreview();
            }
        }, 100);
    }
}

/**
 * Remove voice preview
 */
function removeVoicePreview() {
    const voicePreview = document.getElementById('voicePreview') || document.getElementById('voice-preview');
    if (voicePreview) {
        voicePreview.style.display = 'none';
    }
    
    // Clean up
    recordedAudioBlob = null;
    recordingDuration = 0;
    audioChunks = [];
}

/**
 * Send voice message
 */
function sendVoiceMessage() {
    if (!recordedAudioBlob) {
        return;
    }
    
    // Get session ID - handle both customer and client interfaces
    const currentSessionId = typeof sessionId !== 'undefined' ? sessionId : 
                             (typeof currentSessionId !== 'undefined' ? currentSessionId : null);
    
    if (!currentSessionId) {
        return;
    }
    
    // Create a file from the blob
    const mimeType = recordedAudioBlob.type;
    let extension = 'webm';
    
    if (mimeType.includes('mp4')) {
        extension = 'm4a';
    } else if (mimeType.includes('ogg')) {
        extension = 'ogg';
    } else if (mimeType.includes('wav')) {
        extension = 'wav';
    }
    
    const timestamp = Date.now();
    const fileName = `voice_message_${timestamp}.${extension}`;
    const voiceFile = new File([recordedAudioBlob], fileName, { type: mimeType });
    
    // Determine upload URL based on interface
    const currentBaseUrl = typeof baseUrl !== 'undefined' ? baseUrl : (typeof window.location !== 'undefined' ? window.location.origin + '/' : '/');
    let uploadUrl = currentBaseUrl + 'chat/upload-file';
    let senderType = 'customer';
    let senderName = 'Customer';
    
    // Check if we're in client interface
    if (typeof window.clientConfig !== 'undefined' && window.clientConfig.uploadFileUrl) {
        uploadUrl = window.clientConfig.uploadFileUrl;
        senderType = 'agent';
        senderName = window.clientConfig.currentUsername || 'Agent';
    } else if (typeof userType !== 'undefined' && userType === 'agent') {
        // Admin interface
        uploadUrl = currentBaseUrl + 'chat/upload-file';
        senderType = 'agent';
        senderName = typeof currentUsername !== 'undefined' ? currentUsername : 'Agent';
    }
    
    // Upload using existing file upload system
    const formData = new FormData();
    formData.append('file', voiceFile);
    formData.append('session_id', currentSessionId);
    formData.append('is_voice_message', '1'); // Flag to identify voice messages
    formData.append('sender_type', senderType);
    formData.append('sender_name', senderName);
    
    // Show progress
    if (typeof showUploadProgress === 'function') {
        showUploadProgress();
    } else if (typeof showFileUploadProgress === 'function') {
        showFileUploadProgress();
    }
    
    fetch(uploadUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Hide progress indicators
        if (typeof hideUploadProgress === 'function') {
            hideUploadProgress();
        } else if (typeof hideFileUploadProgress === 'function') {
            hideFileUploadProgress();
        }
        
        if (data.success) {
            // Remove preview
            removeVoicePreview();
            
            // Send WebSocket notification for real-time updates
            if (ws && ws.readyState === WebSocket.OPEN && data.file_data) {
                const fileMessage = {
                    type: 'file_message',
                    id: data.message_id,
                    session_id: currentSessionId,
                    sender_type: senderType,
                    sender_id: typeof userId !== 'undefined' ? userId : null,
                    sender_name: senderName,
                    message: '', // No text message, just the voice file
                    message_type: 'voice',
                    file_data: data.file_data,
                    timestamp: new Date().toISOString(),
                    created_at: new Date().toISOString()
                };
                
                ws.send(JSON.stringify(fileMessage));
            }
            
        } else {
            alert('Failed to send voice message: ' + (data.error || 'Unknown error'));
            removeVoicePreview();
        }
    })
    .catch(error => {
        // Hide progress indicators
        if (typeof hideUploadProgress === 'function') {
            hideUploadProgress();
        } else if (typeof hideFileUploadProgress === 'function') {
            hideFileUploadProgress();
        }
        alert('Failed to send voice message. Please try again.');
        removeVoicePreview();
    });
}

/**
 * Create voice message player UI for displaying in chat
 */
function createVoiceMessagePlayer(fileData, messageId) {
    const player = document.createElement('div');
    player.className = 'voice-message-player';
    player.dataset.messageId = messageId;
    
    // Construct the correct file URL
    let audioUrl = '';
    if (fileData.file_url) {
        audioUrl = fileData.file_url;
    } else if (fileData.file_path) {
        // Use the centralized file URL from FileCompressionService
        // The file_path is relative, so we need to construct the full URL
        audioUrl = 'https://files.kopisugar.cc/livechat/default/chat/' + fileData.file_path;
    } else {
        // Fallback to download endpoint
        const currentBaseUrl = typeof baseUrl !== 'undefined' ? baseUrl : (typeof window.location !== 'undefined' ? window.location.origin + '/' : '/');
        audioUrl = currentBaseUrl + 'chat/download-file/' + messageId;
    }
    
    // Play button
    const playBtn = document.createElement('button');
    playBtn.className = 'voice-play-btn';
    playBtn.innerHTML = '<i class="bi bi-play-fill"></i><i class="fas fa-play" style="display: none;"></i>';
    playBtn.onclick = () => toggleVoicePlayback(messageId, audioUrl);
    
    // Progress container
    const progressContainer = document.createElement('div');
    progressContainer.className = 'voice-progress-container';
    
    // Progress bar
    const progressBar = document.createElement('div');
    progressBar.className = 'voice-progress-bar';
    progressBar.onclick = (e) => seekVoiceMessage(e, messageId);
    
    const progressFill = document.createElement('div');
    progressFill.className = 'voice-progress-fill';
    progressFill.id = `voice-progress-${messageId}`;
    progressBar.appendChild(progressFill);
    
    // Time info
    const timeInfo = document.createElement('div');
    timeInfo.className = 'voice-time-info';
    timeInfo.innerHTML = `
        <span id="voice-current-${messageId}">00:00</span>
        <span id="voice-duration-${messageId}">00:00</span>
    `;
    
    progressContainer.appendChild(progressBar);
    progressContainer.appendChild(timeInfo);
    
    player.appendChild(playBtn);
    player.appendChild(progressContainer);
    
    return player;
}

// Store audio elements for playback
const audioPlayers = {};

/**
 * Toggle voice message playback
 */
function toggleVoicePlayback(messageId, audioUrl) {
    const playBtn = document.querySelector(`[data-message-id="${messageId}"] .voice-play-btn`);
    if (!playBtn) return;
    
    // Stop other playing audio
    Object.keys(audioPlayers).forEach(id => {
        if (id !== messageId && audioPlayers[id]) {
            audioPlayers[id].pause();
            audioPlayers[id].currentTime = 0;
            updatePlayButton(id, false);
        }
    });
    
    // Create audio element if it doesn't exist
    if (!audioPlayers[messageId]) {
        const audio = new Audio(audioUrl);
        audioPlayers[messageId] = audio;
        
        // Add error handling
        audio.addEventListener('error', (e) => {
            updatePlayButton(messageId, false);
            alert('Failed to load voice message. Please try again.');
        });
        
        // Update progress during playback
        audio.addEventListener('timeupdate', () => {
            updateVoiceProgress(messageId);
        });
        
        // Handle playback end
        audio.addEventListener('ended', () => {
            updatePlayButton(messageId, false);
            audio.currentTime = 0;
            updateVoiceProgress(messageId);
        });
        
        // Load metadata to get duration
        audio.addEventListener('loadedmetadata', () => {
            const durationEl = document.getElementById(`voice-duration-${messageId}`);
            if (durationEl) {
                durationEl.textContent = formatAudioDuration(audio.duration);
            }
        });
        
    }
    
    const audio = audioPlayers[messageId];
    
    // Toggle play/pause
    if (audio.paused) {
        audio.play();
        updatePlayButton(messageId, true);
    } else {
        audio.pause();
        updatePlayButton(messageId, false);
    }
}

/**
 * Update play button appearance
 */
function updatePlayButton(messageId, isPlaying) {
    const playBtn = document.querySelector(`[data-message-id="${messageId}"] .voice-play-btn`);
    if (playBtn) {
        if (isPlaying) {
            playBtn.classList.add('playing');
            playBtn.innerHTML = '<i class="bi bi-pause-fill"></i><i class="fas fa-pause" style="display: none;"></i>';
        } else {
            playBtn.classList.remove('playing');
            playBtn.innerHTML = '<i class="bi bi-play-fill"></i><i class="fas fa-play" style="display: none;"></i>';
        }
    }
}

/**
 * Update voice message progress
 */
function updateVoiceProgress(messageId) {
    const audio = audioPlayers[messageId];
    if (!audio) return;
    
    const progressFill = document.getElementById(`voice-progress-${messageId}`);
    const currentTime = document.getElementById(`voice-current-${messageId}`);
    
    if (progressFill) {
        const progress = (audio.currentTime / audio.duration) * 100;
        progressFill.style.width = `${progress}%`;
    }
    
    if (currentTime) {
        currentTime.textContent = formatAudioDuration(audio.currentTime);
    }
}

/**
 * Seek voice message to specific position
 */
function seekVoiceMessage(event, messageId) {
    const audio = audioPlayers[messageId];
    if (!audio) return;
    
    const progressBar = event.currentTarget;
    const rect = progressBar.getBoundingClientRect();
    const clickX = event.clientX - rect.left;
    const percentage = clickX / rect.width;
    
    audio.currentTime = percentage * audio.duration;
    updateVoiceProgress(messageId);
}

/**
 * Format time in MM:SS
 */
function formatTime(seconds) {
    if (isNaN(seconds)) return '00:00';
    
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

/**
 * Format audio duration in MM:SS (same as formatTime but with a different name for clarity)
 */
function formatAudioDuration(seconds) {
    if (isNaN(seconds)) return '00:00';
    
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

// Export functions for global use
window.toggleVoiceRecording = toggleVoiceRecording;
window.cancelVoiceRecording = cancelVoiceRecording;
window.removeVoicePreview = removeVoicePreview;
window.createVoiceMessagePlayer = createVoiceMessagePlayer;
window.toggleVoicePlayback = toggleVoicePlayback;
window.initializeVoiceRecording = initializeVoiceRecording;

// Initialize voice recording when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize immediately if elements are available, otherwise wait briefly
    if (document.getElementById('voiceRecordBtn') || document.getElementById('voice-record-btn')) {
        initializeVoiceRecording();
    } else {
        // Small delay to ensure dynamically loaded elements are available
        setTimeout(initializeVoiceRecording, 100);
    }
});
