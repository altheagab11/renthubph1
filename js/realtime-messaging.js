// Real-time messaging JavaScript
class RealtimeMessaging {
    constructor(options = {}) {
        this.conversationId = options.conversationId || null;
        this.userId = options.userId || null;
        this.pollingInterval = options.pollingInterval || 3000; // 3 seconds
        this.lastCheck = new Date().toISOString().slice(0, 19).replace('T', ' ');
        this.isPolling = false;
        this.pollTimeout = null;
        this.isTyping = false;
        this.typingTimeout = null;
        this.apiBase = options.apiBase || '../api/messages.php';
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        if (this.conversationId) {
            this.startPolling();
            this.markMessagesRead();
        }
    }
    
    bindEvents() {
        // Form submission for new messages
        const messageForm = document.querySelector('form[method="POST"]');
        if (messageForm && messageForm.querySelector('textarea[name="message_content"]')) {
            messageForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.sendMessage(messageForm);
            });
        }
        
        // Auto-scroll to bottom when new messages arrive
        this.setupAutoScroll();
        
        // Handle window focus/blur for polling optimization
        window.addEventListener('focus', () => {
            if (this.conversationId && !this.isPolling) {
                this.startPolling();
            }
        });
        
        window.addEventListener('blur', () => {
            // Don't stop polling completely, just reduce frequency
        });
        
        // Handle page visibility change
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && this.conversationId) {
                this.startPolling();
            } else if (document.visibilityState === 'hidden') {
                this.stopPolling();
            }
        });
    }
    
    async sendMessage(form) {
        const textarea = form.querySelector('textarea[name="message_content"]');
        const messageContent = textarea.value.trim();
        
        if (!messageContent) return;
        
        const sendBtn = form.querySelector('button[type="submit"]');
        const originalBtnContent = sendBtn.innerHTML;
        
        // Disable button and show loading
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        try {
            const response = await fetch(`${this.apiBase}?action=send_message`, {  // <-- Add ?action=send_message here
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    conversation_id: this.conversationId,
                    message_content: messageContent
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Clear textarea
                textarea.value = '';
                textarea.style.height = 'auto';
                
                // Force immediate check for new messages
                await this.checkForNewMessages(true);
                
                this.showAlert('Message sent successfully!', 'success');
            } else {
                throw new Error(result.error || 'Failed to send message');
            }
        } catch (error) {
            console.error('Error sending message:', error);
            this.showAlert('Failed to send message. Please try again.', 'danger');
        } finally {
            // Re-enable button
            sendBtn.disabled = false;
            sendBtn.innerHTML = originalBtnContent;
        }
    }
    
    startPolling() {
        if (this.isPolling || !this.conversationId) return;
        
        this.isPolling = true;
        this.poll();
    }
    
    stopPolling() {
        this.isPolling = false;
        if (this.pollTimeout) {
            clearTimeout(this.pollTimeout);
            this.pollTimeout = null;
        }
    }
    
    async poll() {
        if (!this.isPolling) return;
        
        try {
            await this.checkForNewMessages();
        } catch (error) {
            console.error('Polling error:', error);
        }
        
        // Schedule next poll
        if (this.isPolling) {
            this.pollTimeout = setTimeout(() => this.poll(), this.pollingInterval);
        }
    }
    
    async checkForNewMessages(force = false) {
        if (!this.conversationId) return;
        
        try {
            const params = new URLSearchParams({
                action: 'check_new_messages',
                conversation_id: this.conversationId,
                last_check: this.lastCheck
            });
            
            const response = await fetch(`${this.apiBase}?${params}`);
            const data = await response.json();
            
            if (data.new_messages && data.new_messages.length > 0) {
                this.displayNewMessages(data.new_messages);
                this.scrollToBottom();
            }
            
            // Update unread count in UI
            this.updateUnreadCount(data.unread_count);
            
            // Update last check timestamp
            if (data.timestamp) {
                this.lastCheck = data.timestamp;
            }
            
        } catch (error) {
            console.error('Error checking for new messages:', error);
        }
    }
    
    displayNewMessages(messages) {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return;
        
        messages.forEach(message => {
            const messageElement = this.createMessageElement(message);
            chatMessages.appendChild(messageElement);
            
            // Add fade-in animation
            setTimeout(() => {
                messageElement.style.opacity = '1';
            }, 10);
        });
        
        // Remove "no messages" placeholder if it exists
        const emptyState = chatMessages.querySelector('.empty-state, .text-center');
        if (emptyState && emptyState.textContent.includes('No messages')) {
            emptyState.remove();
        }
    }
    
    createMessageElement(message) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message-bubble ${message.SenderID == this.userId ? 'sent' : 'received'}`;
        messageDiv.style.opacity = '0';
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';
        contentDiv.innerHTML = this.formatMessageContent(message.Msg_Content);
        
        const timeDiv = document.createElement('div');
        timeDiv.className = 'message-time';
        timeDiv.textContent = this.formatMessageTime(message.Msg_CreatedAt);
        
        messageDiv.appendChild(contentDiv);
        messageDiv.appendChild(timeDiv);
        
        return messageDiv;
    }
    
    formatMessageContent(content) {
        // Convert line breaks to <br> tags and escape HTML
        return content
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/\n/g, '<br>');
    }
    
    formatMessageTime(timestamp) {
        const date = new Date(timestamp);
        const options = {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        };
        return date.toLocaleDateString('en-US', options);
    }
    
    updateUnreadCount(count) {
        // Update notification badges
        const badges = document.querySelectorAll('.badge');
        badges.forEach(badge => {
            if (badge.parentElement.querySelector('.fa-bell')) {
                badge.textContent = count;
                badge.style.display = count > 0 ? 'inline' : 'none';
            }
        });
        
        // Update page title with unread count
        const originalTitle = document.title.replace(/^\(\d+\) /, '');
        document.title = count > 0 ? `(${count}) ${originalTitle}` : originalTitle;
    }
    
    async markMessagesRead() {
        if (!this.conversationId) return;
        
        try {
            const params = new URLSearchParams({
                action: 'mark_read',
                conversation_id: this.conversationId
            });
            
            await fetch(`${this.apiBase}?${params}`);
        } catch (error) {
            console.error('Error marking messages as read:', error);
        }
    }
    
    setupAutoScroll() {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return;
        
        // Initial scroll to bottom
        this.scrollToBottom();
        
        // Auto-scroll when new content is added
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    // Only auto-scroll if user is near the bottom
                    if (this.isScrolledToBottom()) {
                        this.scrollToBottom();
                    }
                }
            });
        });
        
        observer.observe(chatMessages, {
            childList: true,
            subtree: true
        });
    }
    
    scrollToBottom() {
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }
    
    isScrolledToBottom() {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return true;
        
        const threshold = 50; // pixels from bottom
        return chatMessages.scrollTop >= (chatMessages.scrollHeight - chatMessages.offsetHeight - threshold);
    }
    
    showAlert(message, type = 'info') {
        // Create alert element
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; border-radius: 15px;';
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.style.transition = 'opacity 0.5s ease';
                alertDiv.style.opacity = '0';
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 500);
            }
        }, 3000);
    }
    
    // Public method to change conversation
    switchConversation(conversationId, userId = null) {
        this.stopPolling();
        this.conversationId = conversationId;
        if (userId) this.userId = userId;
        this.lastCheck = new Date().toISOString().slice(0, 19).replace('T', ' ');
        
        if (conversationId) {
            this.startPolling();
            this.markMessagesRead();
        }
    }
    
    // Cleanup method
    destroy() {
        this.stopPolling();
    }
}

// Initialize messaging when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Extract conversation ID and user ID from page
    const conversationId = new URLSearchParams(window.location.search).get('conversation');
    const userIdElement = document.querySelector('meta[name="user-id"]') || 
                         document.querySelector('[data-user-id]');
    const userId = userIdElement ? 
                   userIdElement.getAttribute('content') || userIdElement.getAttribute('data-user-id') :
                   null;
    
    // Determine API base path based on current location
    const apiBase = window.location.pathname.includes('/owner/') ? '../api/messages.php' : '../api/messages.php';
    
    // Initialize real-time messaging
    window.realtimeMessaging = new RealtimeMessaging({
        conversationId: conversationId,
        userId: userId,
        apiBase: apiBase,
        pollingInterval: 3000
    });
    
    // Handle conversation switching (for links in sidebar)
    document.addEventListener('click', function(e) {
        const conversationLink = e.target.closest('a[href*="conversation="]');
        if (conversationLink) {
            e.preventDefault();
            const url = new URL(conversationLink.href, window.location.origin);
            const newConversationId = url.searchParams.get('conversation');
            
            if (newConversationId !== conversationId) {
                // Update URL without page refresh
                window.history.pushState({}, '', conversationLink.href);
                window.realtimeMessaging.switchConversation(newConversationId, userId);
                
                // Update active state in sidebar
                document.querySelectorAll('.conversation-item').forEach(item => {
                    item.classList.remove('active');
                });
                conversationLink.classList.add('active');
                
                // Reload messages for new conversation
                setTimeout(() => {
                    window.location.reload();
                }, 100);
            }
        }
    });
});

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    if (window.realtimeMessaging) {
        window.realtimeMessaging.destroy();
    }
});