// Real-time messaging JavaScript (SIMPLIFIED - same logic for both)
class RealtimeMessaging {
    constructor(options = {}) {
        this.conversationId = options.conversationId || null;
        this.userId = options.userId || null;
        this.pollingInterval = options.pollingInterval || 2000;
        // Simple timestamp - 15 seconds ago to catch recent messages
        const now = new Date();
        now.setSeconds(now.getSeconds() - 15);
        this.lastCheck = now.toISOString().slice(0, 19).replace('T', ' ');
        this.isPolling = false;
        this.pollTimeout = null;
        this.apiBase = options.apiBase || '../api/messages.php';
        this.isFetching = false;
        
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
        
        this.setupAutoScroll();
        
        window.addEventListener('focus', () => {
            if (this.conversationId && !this.isPolling) {
                this.startPolling();
            }
        });
        
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
        
        // Get conversation ID from form
        let conversationId = this.conversationId;
        if (!conversationId) {
            const hiddenInput = form.querySelector('input[name="conversation_id"]');
            if (hiddenInput && hiddenInput.value) {
                conversationId = hiddenInput.value;
            }
        }
        
        if (!conversationId) {
            this.showAlert('Please select a conversation first', 'warning');
            return;
        }
        
        const sendBtn = form.querySelector('button[type="submit"]');
        const originalBtnContent = sendBtn.innerHTML;
        
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        try {
            const response = await fetch(`${this.apiBase}?action=send_message`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    conversation_id: conversationId,
                    message_content: messageContent
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                // Clear input immediately
                textarea.value = '';
                textarea.style.height = 'auto';
                
                // Update conversation ID if needed
                if (!this.conversationId && conversationId) {
                    this.conversationId = conversationId;
                    this.startPolling();
                    this.markMessagesRead();
                }
                
                // If we got the new message, display it immediately
                if (result.new_message) {
                    this.displayNewMessages([result.new_message]);
                    this.scrollToBottom();
                }
                
                this.showAlert('Message sent successfully!', 'success');
                
                // Quick poll to ensure everything syncs
                setTimeout(() => this.checkForNewMessages(true), 1000);
            } else {
                throw new Error(result.error || 'Failed to send message');
            }
        } catch (error) {
            console.error('Error sending message:', error);
            this.showAlert('Failed to send message. Please try again.', 'danger');
        } finally {
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
        if (this.isPolling) {
            this.pollTimeout = setTimeout(() => this.poll(), this.pollingInterval);
        }
    }
    
    async checkForNewMessages(force = false) {
        if (!this.conversationId) return;
        
        if (this.isFetching) {
            return;
        }
        this.isFetching = true;
        
        try {
            const params = new URLSearchParams({
                action: 'check_new_messages',
                conversation_id: this.conversationId,
                last_check: this.lastCheck
            });
            
            const response = await fetch(`${this.apiBase}?${params}`);
            const data = await response.json();
            
            if (data.new_messages && data.new_messages.length > 0) {
                console.log('Found', data.new_messages.length, 'new messages');
                this.displayNewMessages(data.new_messages);
                this.scrollToBottom();
            }
            
            this.updateUnreadCount(data.unread_count);
            
            if (data.timestamp && new Date(data.timestamp) > new Date(this.lastCheck)) {
                this.lastCheck = data.timestamp;
            }
        } catch (error) {
            console.error('Error checking for new messages:', error);
        } finally {
            this.isFetching = false;
        }
    }
    
    displayNewMessages(messages) {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return;
        
        let displayedCount = 0;
        messages.forEach(message => {
            // Simple deduplication - check if message with this ID already exists
            const existing = chatMessages.querySelector(`[data-msg-id="${message.Msg_ID}"]`);
            if (existing) {
                console.log('Message already exists, skipping:', message.Msg_ID);
                return;
            }
            
            const messageElement = this.createMessageElement(message);
            chatMessages.appendChild(messageElement);
            
            setTimeout(() => {
                messageElement.style.opacity = '1';
            }, 10);
            
            displayedCount++;
        });
        
        console.log('Displayed', displayedCount, 'new messages');
        
        // Remove empty state
        const emptyState = chatMessages.querySelector('.empty-state, .text-center');
        if (emptyState && emptyState.textContent.includes('No messages')) {
            emptyState.remove();
        }
    }
    
    createMessageElement(message) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message-bubble ${message.SenderID == this.userId ? 'sent' : 'received'}`;
        messageDiv.style.opacity = '0';
        messageDiv.dataset.msgId = message.Msg_ID;
        
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
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    }
    
    updateUnreadCount(count) {
        const badges = document.querySelectorAll('.badge');
        badges.forEach(badge => {
            if (badge.parentElement.querySelector('.fa-bell')) {
                badge.textContent = count;
                badge.style.display = count > 0 ? 'inline' : 'none';
            }
        });
        
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
        
        this.scrollToBottom();
        
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
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
        const threshold = 50;
        return chatMessages.scrollTop >= (chatMessages.scrollHeight - chatMessages.offsetHeight - threshold);
    }
    
    showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; border-radius: 15px;';
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
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
    
    switchConversation(conversationId, userId = null) {
        this.stopPolling();
        this.conversationId = conversationId;
        if (userId) this.userId = userId;
        
        // Reset lastCheck for new conversation
        const now = new Date();
        now.setSeconds(now.getSeconds() - 15);
        this.lastCheck = now.toISOString().slice(0, 19).replace('T', ' ');
        
        if (conversationId) {
            this.startPolling();
            this.markMessagesRead();
        }
    }
    
    destroy() {
        this.stopPolling();
    }
}

// Initialize messaging when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    let conversationId = new URLSearchParams(window.location.search).get('conversation');
    const userIdElement = document.querySelector('meta[name="user-id"]') || 
                         document.querySelector('[data-user-id]');
    const userId = userIdElement ? 
                   userIdElement.getAttribute('content') || userIdElement.getAttribute('data-user-id') :
                   null;
    
    const apiBase = window.location.pathname.includes('/owner/') ? '../api/messages.php' : '../api/messages.php';
    
    window.realtimeMessaging = new RealtimeMessaging({
        conversationId: conversationId,
        userId: userId,
        apiBase: apiBase,
        pollingInterval: 2000
    });
    
    // Handle conversation switching
    document.addEventListener('click', function(e) {
        const conversationLink = e.target.closest('a[href*="conversation="]');
        if (conversationLink) {
            e.preventDefault();
            const url = new URL(conversationLink.href, window.location.origin);
            const newConversationId = url.searchParams.get('conversation');
            
            if (newConversationId !== conversationId) {
                window.history.pushState({}, '', conversationLink.href);
                window.realtimeMessaging.switchConversation(newConversationId, userId);
                
                document.querySelectorAll('.conversation-item').forEach(item => {
                    item.classList.remove('active');
                });
                conversationLink.classList.add('active');
                
                setTimeout(() => {
                    window.location.reload();
                }, 100);
            }
        }
    });
});

window.addEventListener('beforeunload', function() {
    if (window.realtimeMessaging) {
        window.realtimeMessaging.destroy();
    }
});