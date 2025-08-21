(function() {
    'use strict';
    
    // Prevent multiple widget instances
    if (window.LiveChatWidget) {
        return;
    }
    
    // Default configuration
    const DEFAULT_CONFIG = {
        baseUrl: '',
        apiKey: '',
        theme: 'blue',
        position: 'bottom-right',
        branding: {
            title: 'Live Chat',
            subtitle: 'We\'re here to help!'
        },
        welcomeBubble: {
            enabled: true,
            message: 'Hi! I\'m here to help. Ask me anything!',
            delay: 3000, // Show after 3 seconds
            autoHide: true,
            autoHideDelay: 10000, // Hide after 10 seconds
            avatar: '👋' // Can be emoji, image URL, or false
        },
        user: {
            isLoggedIn: false
        },
        callbacks: {}
    };
    
    // Enhanced CSS with welcome bubble styles
    const WIDGET_CSS = `
        /* Base Widget Styles */
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
        
        /* Chat Button */
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
        
        /* Welcome Bubble */
        .live-chat-welcome-bubble {
            position: absolute;
            bottom: 80px;
            right: 0;
            max-width: 320px;
            min-width: 280px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            padding: 14px 18px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px) scale(0.8);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            z-index: 999998;
            border: 1px solid #e1e5e9;
        }
        
        .live-chat-welcome-bubble.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }
        
        .live-chat-welcome-bubble::after {
            content: '';
            position: absolute;
            bottom: -8px;
            right: 25px;
            width: 16px;
            height: 16px;
            background: white;
            border: 1px solid #e1e5e9;
            border-top: none;
            border-left: none;
            transform: rotate(45deg);
        }
        
        .live-chat-bubble-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .live-chat-bubble-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            background: #f8f9fa;
            flex-shrink: 0;
        }
        
        .live-chat-bubble-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .live-chat-bubble-message {
            flex: 1;
            font-size: 14px;
            line-height: 1.4;
            color: #2c3e50;
            margin: 0;
        }
        
        .live-chat-bubble-close {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            font-size: 16px;
            line-height: 1;
            flex-shrink: 0;
            margin-left: 8px;
        }
        
        .live-chat-bubble-close:hover {
            background: #f8f9fa;
            color: #495057;
        }
        
        /* Position adjustments for left side */
        .live-chat-widget.position-bottom-left .live-chat-welcome-bubble {
            right: auto;
            left: 0;
        }
        
        .live-chat-widget.position-bottom-left .live-chat-welcome-bubble::after {
            right: auto;
            left: 25px;
        }
        
        /* Mobile responsiveness */
        @media (max-width: 480px) {
            .live-chat-welcome-bubble {
                max-width: calc(100vw - 80px);
                right: -10px;
            }
            
            .live-chat-widget.position-bottom-left .live-chat-welcome-bubble {
                left: -10px;
                right: auto;
            }
        }
        
        /* Chat Modal Styles */
        .live-chat-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
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
        }
        
        /* Modal positioning - direct classes */
        .live-chat-modal.position-bottom-right {
            right: 20px;
        }
        
        .live-chat-modal.position-bottom-left {
            left: 20px;
        }
        
        /* Fallback - legacy widget wrapper selectors */
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
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 18px;
            line-height: 1;
        }
        
        .live-chat-minimize-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .live-chat-iframe {
            width: 100%;
            height: calc(100% - 60px);
            border: none;
            display: block;
        }
        
        /* Mobile adjustments - Fullscreen chat */
        @media (max-width: 768px) {
            .live-chat-modal {
                width: 100vw !important;
                height: 100vh !important;
                height: 100dvh !important; /* Dynamic viewport height for mobile keyboards */
                min-height: 100vh !important;
                min-height: 100dvh !important;
                max-height: 100vh !important;
                max-height: 100dvh !important;
                bottom: 0 !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                border-radius: 0;
                box-shadow: none;
                z-index: 999999; /* Higher than chat button */
                display: flex !important;
                flex-direction: column !important;
                position: fixed !important;
            }
            
            .live-chat-modal-header {
                padding: 15px 20px;
                position: sticky;
                top: 0;
                z-index: 1;
                flex-shrink: 0; /* Prevent header from shrinking */
                height: auto;
            }
            
            .live-chat-iframe {
                flex: 1 !important;
                height: auto !important;
                min-height: 0; /* Allow iframe to shrink */
                width: 100% !important;
                border: none !important;
            }
            
            /* Hide overlay on mobile since chat takes full screen */
            .live-chat-overlay {
                display: none;
            }
            
            /* Hide chat button when modal is open on mobile */
            .live-chat-modal.open ~ .live-chat-widget .live-chat-button {
                display: none;
            }
            
            /* Alternative approach - hide the entire widget container when modal is open */
            body:has(.live-chat-modal.open) .live-chat-widget {
                display: none;
            }
            
            /* Fallback for browsers that don't support :has() */
            .live-chat-widget.modal-open {
                display: none;
            }
            
            /* Adjust welcome bubble position on mobile */
            .live-chat-welcome-bubble {
                bottom: 90px;
                right: 10px;
                left: 10px;
                max-width: calc(100vw - 20px);
            }
        }
        
        /* Animation keyframes */
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }
        
        .live-chat-button.has-notification {
            animation: bounce 1s ease-in-out;
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
    `;
    
    class LiveChatWidget {
        constructor(config = {}) {
            // Deep merge config with defaults, especially for nested objects
            this.config = this.mergeDeep(DEFAULT_CONFIG, config);
            this.isOpen = false;
            this.elements = {};
            this.welcomeBubbleShown = false;
            
            this.init();
        }
        
        mergeDeep(target, source) {
            const output = { ...target };
            if (this.isObject(target) && this.isObject(source)) {
                Object.keys(source).forEach(key => {
                    if (this.isObject(source[key])) {
                        if (!(key in target))
                            Object.assign(output, { [key]: source[key] });
                        else
                            output[key] = this.mergeDeep(target[key], source[key]);
                    } else {
                        Object.assign(output, { [key]: source[key] });
                    }
                });
            }
            return output;
        }
        
        isObject(item) {
            return (item && typeof item === 'object' && !Array.isArray(item));
        }
        
        init() {
            this.injectCSS();
            this.createWidget();
            this.bindEvents();
            
            // Show welcome bubble after delay
            if (this.config.welcomeBubble.enabled) {
                setTimeout(() => {
                    this.showWelcomeBubble();
                }, this.config.welcomeBubble.delay);
            }
        }
        
        injectCSS() {
            if (document.getElementById('live-chat-widget-styles')) {
                return;
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
            
            // Create chat button
            this.elements.button = document.createElement('button');
            this.elements.button.className = 'live-chat-button';
            this.elements.button.innerHTML = `
                <span class="icon chat-icon">💬</span>
                <span class="icon close-icon">✕</span>
                <div class="live-chat-notification" id="live-chat-notification"></div>
            `;
            this.elements.button.onclick = () => this.toggle();
            
            // Create welcome bubble
            this.createWelcomeBubble();
            
            // Add to container
            this.elements.container.appendChild(this.elements.welcomeBubble);
            this.elements.container.appendChild(this.elements.button);
            
            // Add to page
            document.body.appendChild(this.elements.container);
        }
        
        createWelcomeBubble() {
            this.elements.welcomeBubble = document.createElement('div');
            this.elements.welcomeBubble.className = 'live-chat-welcome-bubble';
            
            let avatarHtml = '';
            if (this.config.welcomeBubble.avatar) {
                if (this.config.welcomeBubble.avatar.startsWith('http')) {
                    // Image URL
                    avatarHtml = `<img src="${this.config.welcomeBubble.avatar}" alt="Support">`;
                } else {
                    // Emoji or text
                    avatarHtml = this.config.welcomeBubble.avatar;
                }
            }
            
            this.elements.welcomeBubble.innerHTML = `
                <div class="live-chat-bubble-content">
                    <div class="live-chat-bubble-avatar">${avatarHtml}</div>
                    <p class="live-chat-bubble-message">${this.config.welcomeBubble.message}</p>
                    <button class="live-chat-bubble-close" onclick="window.LiveChatWidget.hideWelcomeBubble()">×</button>
                </div>
            `;
        }
        
        showWelcomeBubble() {
            if (this.welcomeBubbleShown || this.isOpen) {
                return;
            }
            
            if (!this.elements.welcomeBubble) {
                return;
            }
            
            this.elements.welcomeBubble.classList.add('show');
            this.elements.button.classList.add('has-notification');
            this.welcomeBubbleShown = true;
            
            // Auto-hide after delay
            if (this.config.welcomeBubble.autoHide) {
                setTimeout(() => {
                    this.hideWelcomeBubble();
                }, this.config.welcomeBubble.autoHideDelay);
            }
        }
        
        hideWelcomeBubble() {
            this.elements.welcomeBubble.classList.remove('show');
            this.elements.button.classList.remove('has-notification');
        }
        
        showWelcomeBubbleAgain() {
            this.welcomeBubbleShown = false;
            this.showWelcomeBubble();
        }
        
        bindEvents() {
            // Close on escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });
            
            // Listen for iframe messages
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
        
        async validateApiKey() {
            const apiKey = this.config.apiKey;
            
            if (!apiKey) {
                console.error('LiveChat: No API key provided');
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
                    console.error(`LiveChat: ${result.error}`);
                    return false;
                }
                
                return true;
            } catch (error) {
                console.error('LiveChat: API validation failed', error);
                return false;
            }
        }
        
        generateChatUrl() {
            let url = `${this.config.baseUrl}/chat`;
            
            const params = new URLSearchParams({
                'iframe': '1',
                'api_key': this.config.apiKey
            });
            
            if (this.config.user && this.config.user.isLoggedIn) {
                params.append('external_username', this.config.user.username || '');
                params.append('external_fullname', this.config.user.fullname || '');
                params.append('external_system_id', this.config.user.systemId || '');
                params.append('user_role', 'loggedUser');
            }
            
            return url + '?' + params.toString();
        }
        
        async open() {
            if (this.isOpen) return;
            
            // Validate API key before opening
            const isValid = await this.validateApiKey();
            if (!isValid) return;
            
            // Hide welcome bubble when opening chat
            this.hideWelcomeBubble();
            
            // Create modal if it doesn't exist
            if (!this.elements.modal) {
                this.createModal();
            }
            
            // Update iframe URL and show modal
            this.iframe.src = this.generateChatUrl();
            this.elements.modal.classList.add('open');
            this.elements.overlay.classList.add('open');
            this.elements.button.classList.add('open');
            
            // Hide widget on mobile when modal is open (for browsers that don't support :has())
            if (window.innerWidth <= 768) {
                this.elements.container.classList.add('modal-open');
            }
            
            this.isOpen = true;
            
            if (this.config.callbacks.onOpen) {
                this.config.callbacks.onOpen();
            }
        }
        
        createModal() {
            // Create overlay
            this.elements.overlay = document.createElement('div');
            this.elements.overlay.className = 'live-chat-overlay';
            this.elements.overlay.onclick = () => this.close();
            
            // Create modal with positioning classes from widget container
            this.elements.modal = document.createElement('div');
            this.elements.modal.className = `live-chat-modal ${this.elements.container.className.replace('live-chat-widget', '').trim()}`;
            this.elements.modal.innerHTML = `
                <div class="live-chat-modal-header">
                    <h3>${this.config.branding.title}</h3>
                    <button class="live-chat-minimize-btn" onclick="window.LiveChatWidget.close()">×</button>
                </div>
                <iframe class="live-chat-iframe" src=""></iframe>
            `;
            
            document.body.appendChild(this.elements.overlay);
            document.body.appendChild(this.elements.modal);
            
            this.iframe = this.elements.modal.querySelector('.live-chat-iframe');
        }
        
        close() {
            if (!this.isOpen) return;
            
            if (this.elements.modal) {
                this.elements.modal.classList.remove('open');
                this.elements.overlay.classList.remove('open');
            }
            this.elements.button.classList.remove('open');
            
            // Show widget again on mobile when modal is closed
            this.elements.container.classList.remove('modal-open');
            
            this.isOpen = false;
            
            if (this.config.callbacks.onClose) {
                this.config.callbacks.onClose();
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
            if (this.isOpen && this.iframe) {
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
        // Get config from window
        const config = window.LiveChatConfig || {};
        
        // Validate required config
        if (!config.baseUrl || !config.apiKey) {
            console.error('LiveChat: Missing required config - baseUrl or apiKey');
            return;
        }
        
        // Initialize with global config if available
        window.LiveChatWidget = new LiveChatWidget(config);
    }
    
    // Wait for both DOM and config to be ready
    function waitForInit() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                // Add small delay to ensure config is fully set
                setTimeout(initWidget, 150);
            });
        } else {
            // DOM is already ready, just wait a bit for config
            setTimeout(initWidget, 150);
        }
    }
    
    waitForInit();
    
})();