/**
 * LiveChat Helper - API Integration for External Websites
 * Version: 1.0
 * 
 * This helper allows external websites to integrate with our LiveChat system
 * without creating any widget buttons. Websites use their own buttons/links
 * and call our API to open chat sessions.
 * 
 * Usage:
 * <script src="https://livechat.kopisugar.cc/assets/js/livechat-helper.js"></script>
 * <script>
 * LiveChatHelper.init({
 *     apiKey: 'api-key',
 * });
 * 
 * // From any button click:
 * LiveChatHelper.openChat({
 *     userId: 'user123',
 *     name: 'John Doe',
 *     email: 'john@example.com'
 * });
 * </script>
 */

(function() {
    'use strict';
    
    // Prevent multiple instances
    if (window.LiveChatHelper) {
        return;
    }
    
    const LiveChatHelper = {
        config: {
            apiKey: '',
            baseUrl: '',
            apiEndpoint: '/api/getChatroomLink',
            windowFeatures: 'width=500,height=700,scrollbars=yes,resizable=yes,menubar=no,toolbar=no,location=no,status=no',
            windowName: 'livechat',
            debug: false
        },
        
        // Initialize the helper with configuration
        init: function(userConfig = {}) {
            // Merge user config with defaults
            this.config = Object.assign(this.config, userConfig);
            
            // Auto-detect base URL if not provided
            if (!this.config.baseUrl) {
                this.config.baseUrl = this.detectBaseUrl();
            }
            
            // Ensure baseURL doesn't end with slash
            this.config.baseUrl = this.config.baseUrl.replace(/\/$/, '');
            
            // Validate required config
            if (!this.config.apiKey) {
                console.error('LiveChatHelper: API key is required');
                return false;
            }
            
            this.log('Initialized with config:', this.config);
            return true;
        },
        
        // Auto-detect base URL from script tag
        detectBaseUrl: function() {
            const scripts = document.querySelectorAll('script[src*="livechat-helper.js"]');
            if (scripts.length > 0) {
                const scriptSrc = scripts[0].src;
                const url = new URL(scriptSrc);
                return `${url.protocol}//${url.host}`;
            }
            
            // Fallback - this should be updated to your actual domain
            console.warn('LiveChatHelper: Could not auto-detect base URL, using fallback');
            return 'https://livechat.kopisugar.cc';
        },
        
        // Main function to open chat
        openChat: async function(userData = {}) {
            if (!this.config.apiKey) {
                console.error('LiveChatHelper: Not initialized. Call LiveChatHelper.init() first.');
                return null;
            }
            
            try {
                this.log('Opening chat for user:', userData);
                
                // Prepare request data
                const requestData = this.prepareRequestData(userData);
                
                // Call API to get chatroom link
                const chatLink = await this.getChatroomLink(requestData);
                
                if (chatLink) {
                    // Open chat in new window
                    const chatWindow = this.openChatWindow(chatLink);
                    
                    if (!chatWindow) {
                        this.showError('Please allow pop-ups for this site to use live chat.');
                        return null;
                    }
                    
                    this.log('Chat window opened successfully');
                    return chatWindow;
                } else {
                    this.showError('Unable to start chat session.');
                    return null;
                }
                
            } catch (error) {
                console.error('LiveChatHelper: Error opening chat:', error);
                this.showError('Unable to start chat. Please try again later.');
                return null;
            }
        },
        
        // Prepare request data for API call
        prepareRequestData: function(userData) {
            const requestData = {
                user_id: userData.userId || userData.id || 'anonymous_' + Date.now(),
                api_key: this.config.apiKey
            };
            
            // Build session_info string
            if (userData.name || userData.email || userData.userId || userData.id) {
                const sessionParts = ['logged_user'];
                
                if (userData.name || userData.fullName) {
                    sessionParts.push('name:' + (userData.name || userData.fullName));
                }
                
                if (userData.email || userData.username) {
                    sessionParts.push('username:' + (userData.email || userData.username));
                }
                
                if (userData.userId || userData.id || userData.systemId) {
                    sessionParts.push('id:' + (userData.userId || userData.id || userData.systemId));
                }
                
                requestData.session_info = sessionParts.join('|');
            } else {
                requestData.session_info = 'anonymous';
            }
            
            return requestData;
        },
        
        // Call the getChatroomLink API
        getChatroomLink: async function(requestData) {
            const apiUrl = this.config.baseUrl + this.config.apiEndpoint;
            
            this.log('Calling API:', apiUrl, requestData);
            
            try {
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                this.log('API response:', data);
                
                if (data.success && data.chatroom_link) {
                    return data.chatroom_link;
                } else {
                    throw new Error(data.error || 'Invalid API response');
                }
                
            } catch (error) {
                console.error('LiveChatHelper: API call failed:', error);
                throw error;
            }
        },
        
        // Open chat window
        openChatWindow: function(chatLink) {
            this.log('Opening chat window:', chatLink);
            
            try {
                const chatWindow = window.open(
                    chatLink,
                    this.config.windowName,
                    this.config.windowFeatures
                );
                
                // Focus the new window if it opened successfully
                if (chatWindow) {
                    chatWindow.focus();
                }
                
                return chatWindow;
                
            } catch (error) {
                console.error('LiveChatHelper: Failed to open window:', error);
                return null;
            }
        },
        
        // Quick method for anonymous chat
        openAnonymousChat: function() {
            return this.openChat({});
        },
        
        // Quick method for logged-in user chat
        openUserChat: function(userId, name, email) {
            return this.openChat({
                userId: userId,
                name: name,
                email: email
            });
        },
        
        // Show error to user
        showError: function(message) {
            // You can customize this to use your preferred notification method
            if (typeof this.config.onError === 'function') {
                this.config.onError(message);
            } else {
                alert('LiveChat: ' + message);
            }
        },
        
        // Debug logging
        log: function() {
            if (this.config.debug) {
                console.log('LiveChatHelper:', ...arguments);
            }
        },
        
        // Update configuration
        updateConfig: function(newConfig) {
            this.config = Object.assign(this.config, newConfig);
            this.log('Config updated:', this.config);
        },
        
        // Get current configuration (for debugging)
        getConfig: function() {
            return { ...this.config };
        },
        
        // Validate if helper is properly configured
        isReady: function() {
            return !!(this.config.apiKey && this.config.baseUrl);
        }
    };
    
    // Make LiveChatHelper globally available
    window.LiveChatHelper = LiveChatHelper;
    
    // Auto-initialization if config is already available
    if (window.LiveChatConfig) {
        LiveChatHelper.init(window.LiveChatConfig);
    }
    
})();
