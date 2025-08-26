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
            delay: 3000,
            autoHide: true,
            autoHideDelay: 10000,
            avatar: '👋'
        },
        user: {
            isLoggedIn: false
        },
        callbacks: {}
    };
    
    class LiveChatWidget {
        constructor(config = {}) {
            // Check if position is in widgetConfig and move it to top level
            if (config.widgetConfig && config.widgetConfig.position) {
                config.position = config.widgetConfig.position;
            }
            
            // Deep merge config with defaults
            this.config = this.mergeDeep(DEFAULT_CONFIG, config);
            
            // Ensure position from widgetConfig takes precedence
            if (config.widgetConfig && config.widgetConfig.position) {
                this.config.position = config.widgetConfig.position;
            }
            
            this.isOpen = false;
            this.elements = {};
            this.welcomeBubbleShown = false;
            
            console.log('Widget initialized with position:', this.config.position);
            
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
            
            if (this.config.welcomeBubble.enabled) {
                setTimeout(() => {
                    this.showWelcomeBubble();
                }, this.config.welcomeBubble.delay);
            }
        }
        
        injectCSS() {
            if (document.getElementById('live-chat-widget-styles')) {
                document.getElementById('live-chat-widget-styles').remove();
            }
            
            const style = document.createElement('style');
            style.id = 'live-chat-widget-styles';
            style.textContent = this.generateCSS();
            document.head.appendChild(style);
        }
        
        generateCSS() {
            return `
                /* Base Widget Styles */
                .live-chat-widget {
                    position: fixed;
                    z-index: 999999;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                }
                
                /* Widget Button Positions */
                .live-chat-widget.position-bottom-right {
                    bottom: 20px;
                    right: 20px;
                }
                
                .live-chat-widget.position-bottom-left {
                    bottom: 20px;
                    left: 20px;
                }
                
                .live-chat-widget.position-top-right {
                    top: 20px;
                    right: 20px;
                }
                
                .live-chat-widget.position-top-left {
                    top: 20px;
                    left: 20px;
                }
                
                .live-chat-widget.position-center-right {
                    top: 50%;
                    right: 20px;
                    transform: translateY(-50%);
                }
                
                .live-chat-widget.position-center-left {
                    top: 50%;
                    left: 20px;
                    transform: translateY(-50%);
                }
                
                .live-chat-widget.position-bottom-center {
                    bottom: 20px;
                    left: 50%;
                    transform: translateX(-50%);
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
                
                /* Welcome Bubble - Default (for bottom positions) */
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
                
                /* Bubble arrow for bottom positions */
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
                
                /* Welcome bubble for TOP positions */
                .live-chat-widget.position-top-right .live-chat-welcome-bubble,
                .live-chat-widget.position-top-left .live-chat-welcome-bubble {
                    bottom: auto;
                    top: 80px;
                    transform: translateY(-20px) scale(0.8);
                }
                
                .live-chat-widget.position-top-right .live-chat-welcome-bubble.show,
                .live-chat-widget.position-top-left .live-chat-welcome-bubble.show {
                    transform: translateY(0) scale(1);
                }
                
                /* Bubble arrow for top positions */
                .live-chat-widget.position-top-right .live-chat-welcome-bubble::after,
                .live-chat-widget.position-top-left .live-chat-welcome-bubble::after {
                    bottom: auto;
                    top: -8px;
                    border: 1px solid #e1e5e9;
                    border-bottom: none;
                    border-right: none;
                    transform: rotate(-135deg);
                }
                
                /* Welcome bubble for LEFT side positions (but not center-left) */
                .live-chat-widget.position-bottom-left .live-chat-welcome-bubble {
                    right: auto;
                    left: 0;
                }
                
                .live-chat-widget.position-bottom-left .live-chat-welcome-bubble::after {
                    right: auto;
                    left: 25px;
                }
                
                .live-chat-widget.position-top-left .live-chat-welcome-bubble {
                    right: auto;
                    left: 0;
                }
                
                .live-chat-widget.position-top-left .live-chat-welcome-bubble::after {
                    right: auto;
                    left: 25px;
                }
                
                /* Welcome bubble for CENTER RIGHT position - appears to the left of button */
                .live-chat-widget.position-center-right .live-chat-welcome-bubble {
                    bottom: auto !important;
                    top: 50% !important;
                    right: 80px !important;
                    left: auto !important;
                    margin-top: -40px;
                    transform: translateX(20px) scale(0.8);
                }
                
                .live-chat-widget.position-center-right .live-chat-welcome-bubble.show {
                    transform: translateX(0) scale(1);
                }
                
                /* Welcome bubble for CENTER LEFT position - appears to the right of button */
                .live-chat-widget.position-center-left .live-chat-welcome-bubble {
                    bottom: auto !important;
                    top: 50% !important;
                    left: 80px !important;
                    right: auto !important;
                    margin-top: -40px;
                    transform: translateX(-20px) scale(0.8);
                }
                
                .live-chat-widget.position-center-left .live-chat-welcome-bubble.show {
                    transform: translateX(0) scale(1);
                }
                
                /* Arrow for center-right position - pointing right to the button */
                .live-chat-widget.position-center-right .live-chat-welcome-bubble::after {
                    bottom: auto;
                    top: 50%;
                    right: -8px;
                    left: auto;
                    margin-top: -8px;
                    border: 1px solid #e1e5e9;
                    border-left: none;
                    border-bottom: none;
                    transform: rotate(-45deg);
                }
                
                /* Arrow for center-left position - pointing left to the button */
                .live-chat-widget.position-center-left .live-chat-welcome-bubble::after {
                    bottom: auto;
                    top: 50%;
                    left: -8px;
                    right: auto;
                    margin-top: -8px;
                    border: 1px solid #e1e5e9;
                    border-right: none;
                    border-top: none;
                    transform: rotate(-45deg);
                }
                
                /* Welcome bubble for BOTTOM CENTER position */
                .live-chat-widget.position-bottom-center .live-chat-welcome-bubble {
                    left: 50%;
                    right: auto;
                    transform: translateX(-50%) translateY(20px) scale(0.8);
                }
                
                .live-chat-widget.position-bottom-center .live-chat-welcome-bubble.show {
                    transform: translateX(-50%) translateY(0) scale(1);
                }
                
                .live-chat-widget.position-bottom-center .live-chat-welcome-bubble::after {
                    left: 50%;
                    right: auto;
                    transform: translateX(-50%) rotate(45deg);
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
                
                /* Chat Overlay */
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
                
                /* Base Chat Modal - NO POSITION STYLES HERE */
                .live-chat-modal {
                    width: 400px;
                    height: 600px;
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                    z-index: 999998;
                    opacity: 0;
                    visibility: hidden;
                    overflow: hidden;
                    transition: opacity 0.3s ease, visibility 0.3s ease, transform 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                }
                
                .live-chat-modal.open {
                    opacity: 1;
                    visibility: visible;
                }
                
                /* Modal Header */
                .live-chat-modal-header {
                    background: linear-gradient(135deg, #007bff, #0056b3);
                    color: white;
                    padding: 15px 20px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
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
                
                .live-chat-iframe {
                    width: 100%;
                    height: calc(100% - 60px);
                    border: none;
                    display: block;
                }
                
                /* Mobile Responsiveness */
                @media (max-width: 768px) {
                    .live-chat-modal {
                        width: 100vw !important;
                        height: 100vh !important;
                        bottom: 0 !important;
                        top: 0 !important;
                        left: 0 !important;
                        right: 0 !important;
                        border-radius: 0;
                        transform: none !important;
                    }
                    
                    .live-chat-overlay {
                        display: none;
                    }
                    
                    /* Adjust welcome bubble for mobile */
                    .live-chat-welcome-bubble {
                        max-width: calc(100vw - 100px);
                    }
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
                }
            `;
        }
        
        createWidget() {
            this.elements.container = document.createElement('div');
            this.elements.container.className = `live-chat-widget position-${this.config.position} theme-${this.config.theme}`;
            
            this.elements.button = document.createElement('button');
            this.elements.button.className = 'live-chat-button';
            this.elements.button.innerHTML = `
                <span class="icon chat-icon">💬</span>
                <span class="icon close-icon">✕</span>
                <div class="live-chat-notification" id="live-chat-notification"></div>
            `;
            this.elements.button.onclick = () => this.toggle();
            
            this.createWelcomeBubble();
            
            this.elements.container.appendChild(this.elements.welcomeBubble);
            this.elements.container.appendChild(this.elements.button);
            
            document.body.appendChild(this.elements.container);
        }
        
        createWelcomeBubble() {
            this.elements.welcomeBubble = document.createElement('div');
            this.elements.welcomeBubble.className = 'live-chat-welcome-bubble';
            
            let avatarHtml = '';
            if (this.config.welcomeBubble.avatar) {
                if (this.config.welcomeBubble.avatar.startsWith('http')) {
                    avatarHtml = `<img src="${this.config.welcomeBubble.avatar}" alt="Support">`;
                } else {
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
            
            if (this.config.welcomeBubble.autoHide) {
                setTimeout(() => {
                    this.hideWelcomeBubble();
                }, this.config.welcomeBubble.autoHideDelay);
            }
        }
        
        hideWelcomeBubble() {
            if (this.elements.welcomeBubble) {
                this.elements.welcomeBubble.classList.remove('show');
            }
            if (this.elements.button) {
                this.elements.button.classList.remove('has-notification');
            }
        }
        
        bindEvents() {
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });
            
            window.addEventListener('message', (event) => {
                if (event.origin !== new URL(this.config.baseUrl).origin) {
                    return;
                }
                
                switch (event.data.type) {
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
            
            const isValid = await this.validateApiKey();
            if (!isValid) return;
            
            this.hideWelcomeBubble();
            
            if (!this.elements.modal) {
                this.createModal();
            }
            
            // Force apply position before opening
            this.applyModalPosition(this.config.position);
            
            this.iframe.src = this.generateChatUrl();
            
            // Add open class after a small delay to ensure position is set
            setTimeout(() => {
                this.elements.modal.classList.add('open');
                this.elements.overlay.classList.add('open');
                this.elements.button.classList.add('open');
                
                // Re-apply position with open state
                this.isOpen = true;
                this.applyModalPosition(this.config.position);
            }, 10);
            
            if (window.innerWidth <= 768) {
                this.elements.container.classList.add('modal-open');
            }
            
            if (this.config.callbacks.onOpen) {
                this.config.callbacks.onOpen();
            }
        }
        
        createModal() {
            this.elements.overlay = document.createElement('div');
            this.elements.overlay.className = 'live-chat-overlay';
            this.elements.overlay.onclick = () => this.close();
            
            this.elements.modal = document.createElement('div');
            // Only add base class, no position classes
            this.elements.modal.className = 'live-chat-modal';
            this.elements.modal.id = 'live-chat-modal-' + Date.now(); // Add unique ID for debugging
            
            console.log('Creating modal with position:', this.config.position);
            
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
            
            // Apply position immediately after adding to DOM
            this.applyModalPosition(this.config.position);
            
            // Debug: Check what styles are actually applied
            setTimeout(() => {
                const computedStyle = window.getComputedStyle(this.elements.modal);
                console.log('Modal computed position after creation:', {
                    position: computedStyle.position,
                    top: computedStyle.top,
                    bottom: computedStyle.bottom,
                    left: computedStyle.left,
                    right: computedStyle.right,
                    transform: computedStyle.transform
                });
            }, 100);
        }
        
        applyModalPosition(position) {
            if (!this.elements.modal) return;
            
            // Reset all position styles first
            this.elements.modal.style.position = 'fixed';
            this.elements.modal.style.top = 'auto';
            this.elements.modal.style.bottom = 'auto';
            this.elements.modal.style.left = 'auto';
            this.elements.modal.style.right = 'auto';
            this.elements.modal.style.transform = '';
            
            // Force browser to recalculate styles
            void this.elements.modal.offsetHeight;
            
            // Apply position-specific styles with !important equivalent (inline styles have highest priority)
            switch(position) {
                case 'bottom-right':
                    this.elements.modal.style.bottom = '90px';
                    this.elements.modal.style.right = '20px';
                    this.elements.modal.style.transform = this.isOpen ? 'translateY(0) scale(1)' : 'translateY(20px) scale(0.9)';
                    break;
                case 'bottom-left':
                    this.elements.modal.style.bottom = '90px';
                    this.elements.modal.style.left = '20px';
                    this.elements.modal.style.transform = this.isOpen ? 'translateY(0) scale(1)' : 'translateY(20px) scale(0.9)';
                    break;
                case 'bottom-center':
                    this.elements.modal.style.bottom = '90px';
                    this.elements.modal.style.left = '50%';
                    this.elements.modal.style.transform = this.isOpen ? 'translateX(-50%) translateY(0) scale(1)' : 'translateX(-50%) translateY(20px) scale(0.9)';
                    break;
                case 'top-right':
                    this.elements.modal.style.top = '90px';
                    this.elements.modal.style.right = '20px';
                    this.elements.modal.style.transform = this.isOpen ? 'translateY(0) scale(1)' : 'translateY(-20px) scale(0.9)';
                    break;
                case 'top-left':
                    this.elements.modal.style.top = '90px';
                    this.elements.modal.style.left = '20px';
                    this.elements.modal.style.transform = this.isOpen ? 'translateY(0) scale(1)' : 'translateY(-20px) scale(0.9)';
                    break;
                case 'center-right':
                    this.elements.modal.style.top = '50%';
                    this.elements.modal.style.right = '90px';
                    this.elements.modal.style.transform = this.isOpen ? 'translateY(-50%) translateX(0) scale(1)' : 'translateY(-50%) translateX(20px) scale(0.9)';
                    break;
                case 'center-left':
                    this.elements.modal.style.top = '50%';
                    this.elements.modal.style.left = '90px';
                    this.elements.modal.style.transform = this.isOpen ? 'translateY(-50%) translateX(0) scale(1)' : 'translateY(-50%) translateX(-20px) scale(0.9)';
                    break;
                default:
                    // Default to bottom-right
                    this.elements.modal.style.bottom = '90px';
                    this.elements.modal.style.right = '20px';
                    this.elements.modal.style.transform = this.isOpen ? 'translateY(0) scale(1)' : 'translateY(20px) scale(0.9)';
            }
            
            // Force the styles to apply
            this.elements.modal.style.setProperty('position', 'fixed', 'important');
            
            console.log('Applied modal position:', position, 'Modal element:', this.elements.modal);
            console.log('Computed styles:', window.getComputedStyle(this.elements.modal).top, 
                        window.getComputedStyle(this.elements.modal).bottom,
                        window.getComputedStyle(this.elements.modal).left,
                        window.getComputedStyle(this.elements.modal).right);
        }
        
        close() {
            if (!this.isOpen) return;
            
            if (this.elements.modal) {
                this.elements.modal.classList.remove('open');
                this.elements.overlay.classList.remove('open');
            }
            this.elements.button.classList.remove('open');
            
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
        
        updateUser(userInfo) {
            this.config.user = userInfo;
            if (this.isOpen && this.iframe) {
                this.iframe.src = this.generateChatUrl();
            }
        }
        
        updatePosition(newPosition) {
            console.log('UpdatePosition called with:', newPosition);
            this.config.position = newPosition;
            
            // Update widget container position
            if (this.elements.container) {
                const classes = this.elements.container.className.split(' ');
                const filteredClasses = classes.filter(cls => !cls.startsWith('position-'));
                this.elements.container.className = filteredClasses.join(' ').trim();
                this.elements.container.classList.add(`position-${newPosition}`);
            }
            
            // If modal exists, update its position
            if (this.elements.modal) {
                // Force remove and recreate the modal to ensure clean position
                const wasOpen = this.isOpen;
                
                if (wasOpen) {
                    // Close without animation
                    this.elements.modal.classList.remove('open');
                    this.elements.overlay.classList.remove('open');
                    this.isOpen = false;
                }
                
                // Remove modal
                this.elements.modal.remove();
                this.elements.overlay.remove();
                this.elements.modal = null;
                this.elements.overlay = null;
                this.iframe = null;
                
                // If was open, reopen with new position
                if (wasOpen) {
                    setTimeout(() => {
                        this.open();
                    }, 100);
                }
            }
            
            console.log('Position updated to:', newPosition);
        }
        
        // Add this method to expose widget for debugging
        debugModalPosition() {
            if (!this.elements.modal) {
                console.log('No modal exists yet');
                return;
            }
            
            const modal = this.elements.modal;
            const computedStyle = window.getComputedStyle(modal);
            
            console.log('=== MODAL POSITION DEBUG ===');
            console.log('Config position:', this.config.position);
            console.log('Modal element:', modal);
            console.log('Inline styles:', {
                position: modal.style.position,
                top: modal.style.top,
                bottom: modal.style.bottom,
                left: modal.style.left,
                right: modal.style.right,
                transform: modal.style.transform
            });
            console.log('Computed styles:', {
                position: computedStyle.position,
                top: computedStyle.top,
                bottom: computedStyle.bottom,
                left: computedStyle.left,
                right: computedStyle.right,
                transform: computedStyle.transform
            });
            console.log('Modal classes:', modal.className);
            console.log('Modal ID:', modal.id);
            
            // Check if any stylesheets are overriding
            const sheets = document.styleSheets;
            for (let i = 0; i < sheets.length; i++) {
                try {
                    const rules = sheets[i].cssRules || sheets[i].rules;
                    for (let j = 0; j < rules.length; j++) {
                        const rule = rules[j];
                        if (rule.selectorText && modal.matches(rule.selectorText)) {
                            console.log('Matching CSS rule:', rule.selectorText, rule.style.cssText);
                        }
                    }
                } catch (e) {
                    // Cross-origin stylesheets will throw an error
                }
            }
        }
        
        destroy() {
            if (this.elements.container) {
                this.elements.container.remove();
            }
            if (this.elements.overlay) {
                this.elements.overlay.remove();
            }
            if (this.elements.modal) {
                this.elements.modal.remove();
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
        const config = window.LiveChatConfig || {};
        
        if (!config.baseUrl || !config.apiKey) {
            console.error('LiveChat: Missing required config - baseUrl or apiKey');
            return;
        }
        
        // Ensure position is properly extracted from config
        if (config.widgetConfig && config.widgetConfig.position) {
            config.position = config.widgetConfig.position;
        }
        
        console.log('Initializing widget with config:', config);
        
        window.LiveChatWidget = new LiveChatWidget(config);
    }
    
    // Wait for both DOM and config to be ready
    function waitForInit() {
        if (window.LiveChatWidget) {
            return;
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(() => {
                    if (!window.LiveChatWidget) {
                        initWidget();
                    }
                }, 150);
            });
        } else {
            setTimeout(() => {
                if (!window.LiveChatWidget) {
                    initWidget();
                }
            }, 150);
        }
    }
    
    // Add global debug function
    window.debugLiveChatPosition = function() {
        if (window.LiveChatWidget && window.LiveChatWidget.debugModalPosition) {
            window.LiveChatWidget.debugModalPosition();
        } else {
            console.log('LiveChatWidget not initialized or debug method not available');
        }
    };
    
    waitForInit();
    
})();