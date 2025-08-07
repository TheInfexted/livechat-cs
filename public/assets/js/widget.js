(function() {
    'use strict';
    
    // Prevent multiple widget instances
    if (window.LiveChatWidget) {
        return;
    }
    
    // Widget configuration
    const WIDGET_CONFIG = {
        // Base URL of your chat system - CHANGE THIS TO YOUR DOMAIN
        baseUrl: window.LiveChatConfig?.baseUrl || 'http://localhost',
        
        // API Key for validation
        apiKey: window.LiveChatConfig?.apiKey || '',
        
        // Widget appearance
        position: window.LiveChatConfig?.position || 'bottom-right', // bottom-right, bottom-left
        theme: window.LiveChatConfig?.theme || 'blue', // blue, green, purple, custom
        
        // User information (will be populated by host website)
        user: window.LiveChatConfig?.user || null,
        
        // Custom branding
        branding: window.LiveChatConfig?.branding || {
            title: 'Customer Support',
            welcomeMessage: 'How can we help you today?'
        }
    };
    
    // Widget CSS - Injected dynamically
    const WIDGET_CSS = `
        /* Live Chat Widget - Embedded Styles */
        .live-chat-widget {
            position: fixed;
            z-index: 999999;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .live-chat-widget.position-bottom-right {
            bottom: 20px;
            right: 20px;
        }
        
        .live-chat-widget.position-bottom-left {
            bottom: 20px;
            left: 20px;
        }
        
        .live-chat-button {
            width: 60px;
            height: 60px;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            position: relative;
            outline: none;
        }
        
        .live-chat-widget.theme-blue .live-chat-button {
            background: linear-gradient(135deg, #007bff, #0056b3);
        }
        
        .live-chat-widget.theme-green .live-chat-button {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }
        
        .live-chat-widget.theme-purple .live-chat-button {
            background: linear-gradient(135deg, #6f42c1, #5a32a3);
        }
        
        .live-chat-button:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.4);
        }
        
        .live-chat-button:active {
            transform: scale(0.95);
        }
        
        .live-chat-button .icon {
            transition: all 0.3s ease;
            user-select: none;
        }
        
        .live-chat-button .close-icon {
            display: none;
        }
        
        .live-chat-button.open .chat-icon {
            display: none;
        }
        
        .live-chat-button.open .close-icon {
            display: block;
        }
        
        .live-chat-button.open {
            background: linear-gradient(135deg, #dc3545, #c82333) !important;
        }
        
        .live-chat-modal {
            position: fixed;
            bottom: 90px;
            width: 400px;
            height: 600px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            z-index: 999998;
            transform: translateY(20px) scale(0.9);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            overflow: hidden;
            border: 1px solid #e0e0e0;
        }
        
        .live-chat-widget.position-bottom-right .live-chat-modal {
            right: 20px;
        }
        
        .live-chat-widget.position-bottom-left .live-chat-modal {
            left: 20px;
        }
        
        .live-chat-modal.open {
            transform: translateY(0) scale(1);
            opacity: 1;
            visibility: visible;
        }
        
        .live-chat-modal-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .live-chat-widget.theme-green .live-chat-modal-header {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }
        
        .live-chat-widget.theme-purple .live-chat-modal-header {
            background: linear-gradient(135deg, #6f42c1, #5a32a3);
        }
        
        .live-chat-modal-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .live-chat-minimize-btn {
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            padding: 5px 8px;
            border-radius: 4px;
            transition: background 0.2s ease;
            outline: none;
        }
        
        .live-chat-minimize-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .live-chat-iframe {
            width: 100%;
            height: calc(100% - 60px);
            border: none;
            display: block;
        }
        
        .live-chat-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999997;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .live-chat-overlay.open {
            opacity: 1;
            visibility: visible;
        }
        
        /* Notification badge */
        .live-chat-notification {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            animation: live-chat-pulse 2s infinite;
        }
        
        @keyframes live-chat-pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .live-chat-modal {
                width: calc(100vw - 40px) !important;
                height: calc(100vh - 140px) !important;
                bottom: 90px !important;
            }
            
            .live-chat-widget.position-bottom-right .live-chat-modal,
            .live-chat-widget.position-bottom-left .live-chat-modal {
                left: 20px !important;
                right: 20px !important;
            }
        }
        
        /* Animations */
        .live-chat-widget * {
            box-sizing: border-box;
        }
    `;
    
    // Main Widget Class
    class LiveChatWidget {
        constructor(config) {
            this.config = { ...WIDGET_CONFIG, ...config };
            this.isOpen = false;
            this.iframe = null;
            this.elements = {};
            
            this.init();
        }
        
        init() {
            this.injectCSS();
            this.createWidget();
            this.bindEvents();
            
            // Auto-open if configured
            if (this.config.autoOpen) {
                setTimeout(() => this.open(), this.config.autoOpenDelay || 3000);
            }
        }
        
        injectCSS() {
            if (document.getElementById('live-chat-widget-styles')) {
                return; // Already injected
            }
            
            const style = document.createElement('style');
            style.id = 'live-chat-widget-styles';
            style.textContent = WIDGET_CSS;
            document.head.appendChild(style);
        }
        
        createWidget() {
            // Create main container
            this.elements.container = document.createElement('div');
            this.elements.container.className = `live-chat-widget position-${this.config.position} theme-${this.config.theme}`;
            
            // Create overlay
            this.elements.overlay = document.createElement('div');
            this.elements.overlay.className = 'live-chat-overlay';
            this.elements.overlay.onclick = () => this.close();
            
            // Create chat button
            this.elements.button = document.createElement('button');
            this.elements.button.className = 'live-chat-button';
            this.elements.button.innerHTML = `
                <span class="icon chat-icon">💬</span>
                <span class="icon close-icon">✕</span>
                <div class="live-chat-notification" id="live-chat-notification"></div>
            `;
            this.elements.button.onclick = () => this.toggle();
            
            // Create modal
            this.elements.modal = document.createElement('div');
            this.elements.modal.className = 'live-chat-modal';
            this.elements.modal.innerHTML = `
                <div class="live-chat-modal-header">
                    <h3>${this.config.branding.title}</h3>
                    <button class="live-chat-minimize-btn" onclick="window.LiveChatWidget.close()">−</button>
                </div>
                <iframe class="live-chat-iframe" src=""></iframe>
            `;
            
            // Add to page
            this.elements.container.appendChild(this.elements.button);
            this.elements.container.appendChild(this.elements.modal);
            
            document.body.appendChild(this.elements.overlay);
            document.body.appendChild(this.elements.container);
            
            // Store iframe reference
            this.iframe = this.elements.modal.querySelector('.live-chat-iframe');
        }
        
        bindEvents() {
            // Close on escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });
            
            // Listen for iframe messages (optional)
            window.addEventListener('message', (event) => {
                if (event.origin !== new URL(this.config.baseUrl).origin) {
                    return;
                }
                
                // Handle messages from chat iframe
                switch (event.data.type) {
                    case 'chat_started':
                        break;
                    case 'chat_ended':
                        break;
                    case 'new_message':
                        if (!this.isOpen) {
                            this.showNotification();
                        }
                        break;
                }
            });
        }
        
        generateChatUrl() {
            let url = `${this.config.baseUrl}/chat`;
            
            const params = new URLSearchParams({
                'iframe': '1',
                'api_key': window.LiveChatConfig?.apiKey || '' // Pass API key
            });
            
            if (this.config.user && this.config.user.isLoggedIn) {
                params.append('external_username', this.config.user.username || '');
                params.append('external_fullname', this.config.user.fullname || '');
                params.append('external_system_id', this.config.user.systemId || '');
                params.append('user_role', 'loggedUser');
            }
            
            url += '?' + params.toString();
            return url;
        }
        
        async validateApiKey() {
            const apiKey = window.LiveChatConfig?.apiKey || this.config.apiKey;
            
            if (!apiKey) {
                alert('No API key found. Please check your configuration.');
                return false;
            }
            
            try {
                const response = await fetch(`${this.config.baseUrl}/api/widget/validate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        api_key: apiKey,
                        domain: window.location.hostname
                    })
                });
                
                const result = await response.json();
                if (!result.valid) {
                    alert(`Chat Widget Error: ${result.error}`);
                    return false;
                }
                
                return true;
            } catch (error) {
                alert('Chat Widget Error: Unable to validate API key');
                return false;
            }
        }
        
        async open() {
            if (this.isOpen) return;
            
            // Validate API key before opening
            const isValid = await this.validateApiKey();
            if (!isValid) {
                return; // Don't open if API key is invalid
            }
            
            // Update iframe URL
            this.iframe.src = this.generateChatUrl();
            
            // Show modal
            this.elements.modal.classList.add('open');
            this.elements.button.classList.add('open');
            this.elements.overlay.classList.add('open');
            
            this.isOpen = true;
            this.hideNotification();
            
            // Trigger callback
            if (this.config.onOpen) {
                this.config.onOpen();
            }
        }
        
        close() {
            if (!this.isOpen) return;
            
            this.elements.modal.classList.remove('open');
            this.elements.button.classList.remove('open');
            this.elements.overlay.classList.remove('open');
            
            this.isOpen = false;
            
            // Trigger callback
            if (this.config.onClose) {
                this.config.onClose();
            }
        }
        
        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }
        
        showNotification() {
            const notification = document.getElementById('live-chat-notification');
            if (notification) {
                notification.style.display = 'flex';
            }
        }
        
        hideNotification() {
            const notification = document.getElementById('live-chat-notification');
            if (notification) {
                notification.style.display = 'none';
            }
        }
        
        updateUser(userInfo) {
            this.config.user = userInfo;
            if (this.isOpen) {
                this.iframe.src = this.generateChatUrl();
            }
        }
        
        destroy() {
            if (this.elements.container) {
                this.elements.container.remove();
            }
            if (this.elements.overlay) {
                this.elements.overlay.remove();
            }
            
            const styles = document.getElementById('live-chat-widget-styles');
            if (styles) {
                styles.remove();
            }
            
            delete window.LiveChatWidget;
        }
    }
    
    // Auto-initialize when DOM is ready
    function initWidget() {
        // Ensure we have the latest config from the page
        const config = window.LiveChatConfig || {};
        
        // Initialize with global config if available
        window.LiveChatWidget = new LiveChatWidget(config);
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWidget);
    } else {
        // Small delay to ensure config is set
        setTimeout(initWidget, 100);
    }
    
})();
