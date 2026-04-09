document.getElementById('year') && (document.getElementById('year').textContent = new Date().getFullYear());
		const chatbotOpenBtn = document.getElementById('chatbot-open-btn');
		const chatbotOverlay = document.getElementById('chatbot-overlay');
		const chatbotCloseBtn = document.getElementById('chatbot-close-btn');
		const chatbotNewBtn = document.getElementById('chatbot-new-btn');
		const chatbotMessages = document.getElementById('chatbot-messages');
		const chatbotForm = document.getElementById('chatbot-form');
		const chatbotInput = document.getElementById('chatbot-input');

		let conversationMessages = [];
		let isSending = false;
		const statusChip = document.getElementById('chatbot-status-chip');
		const CHAT_STORAGE_KEY = 'upcycleconnect-chat-history';
		const CHAT_OPEN_STORAGE_KEY = 'upcycleconnect-chat-open';

		function saveConversation() {
			if (typeof localStorage === 'undefined') return;
			try {
				localStorage.setItem(CHAT_STORAGE_KEY, JSON.stringify(conversationMessages));
				localStorage.setItem(CHAT_OPEN_STORAGE_KEY, chatbotOverlay?.classList.contains('is-open') ? '1' : '0');
			} catch (err) {
				console.warn('Chat persistence failed:', err);
			}
		}

		function restoreConversation() {
			if (typeof localStorage === 'undefined') return;
			const stored = localStorage.getItem(CHAT_STORAGE_KEY);
			if (stored) {
				try {
					const parsed = JSON.parse(stored);
					if (Array.isArray(parsed)) {
						conversationMessages = parsed;
					}
				} catch (err) {
					console.warn('Chat restore failed:', err);
				}
			}

			if (conversationMessages.length > 0) {
				chatbotMessages.innerHTML = '';
				conversationMessages.forEach(msg => appendMessage(msg.role, msg.content));
			}

			const openState = localStorage.getItem(CHAT_OPEN_STORAGE_KEY);
			if (openState === '1') {
				setChatbotVisible(true);
			}
		}

		function setStatusChip(state, label) {
			if (!statusChip) return;
			statusChip.className = 'chatbot-status-chip chatbot-status-' + state;
			statusChip.textContent = label;
		}

		function clearConversation() {
			conversationMessages = [];
			if (chatbotMessages) chatbotMessages.innerHTML = '';
			localStorage.removeItem('upcycleconnect-chat-history');
			localStorage.removeItem('upcycleconnect-chat-open');
			appendMessage('assistant', 'Salut ! Je suis Kévin. Comment puis-je vous aider aujourd’hui ?');
			conversationMessages.push({ role: 'assistant', content: 'Salut ! Je suis Kévin. Comment puis-je vous aider aujourd’hui ?' });
			saveConversation();
			setStatusChip('ready', 'Ready');
			scrollChatToBottom();
		}

		function scrollChatToBottom() {
			requestAnimationFrame(() => {
				if (chatbotMessages) {
					chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
				}
			});
		}

		function escapeHtml(text) {
			return String(text)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#39;');
		}

		function renderMarkdown(text) {
			let html = escapeHtml(text);
			html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
			html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
			html = html.replace(/`([^`]+?)`/g, '<code>$1</code>');
			html = html.replace(/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
			html = html.replace(/^\s*[-*+]\s+(.+)$/gm, '<li>$1</li>');
			html = html.replace(/(?:<li>.+?<\/li>\s*)+/g, function(list) {
				return '<ul>' + list.trim() + '</ul>';
			});
			html = html.replace(/^\s*\d+\.\s+(.+)$/gm, '<li>$1</li>');
			html = html.replace(/(?:<li>.+?<\/li>\s*)+/g, function(list) {
				return list.includes('<ul>') ? list : '<ol>' + list.trim() + '</ol>';
			});
			return html.replace(/\n/g, '<br>');
		}

		function appendMessage(role, text) {
			if (!chatbotMessages) return;
			const bubble = document.createElement('div');
			bubble.className = 'chatbot-message ' + role;
			if (role === 'assistant') {
				bubble.innerHTML = renderMarkdown(text);
			} else {
				bubble.textContent = text;
			}
			chatbotMessages.appendChild(bubble);
			scrollChatToBottom();
		}

		function setChatbotVisible(visible) {
			if (!chatbotOverlay || !chatbotOpenBtn) return;
			chatbotOverlay.classList.toggle('is-open', visible);
			chatbotOpenBtn.classList.toggle('is-open', visible);
			chatbotOpenBtn.setAttribute('aria-label', visible ? 'Close chatbot' : 'Open chatbot');
			const icon = chatbotOpenBtn.querySelector('i');
			if (icon) {
				icon.className = visible ? 'fa-solid fa-xmark' : 'fa-solid fa-robot';
			}
			chatbotOverlay.setAttribute('aria-hidden', visible ? 'false' : 'true');
			if (visible) {
				setStatusChip('ready', 'Ready');
			}
			if (visible && conversationMessages.length === 0) {
				appendMessage('assistant', 'Salut ! Je suis Kévin. Comment puis-je vous aider aujourd’hui ?');
				conversationMessages.push({ role: 'assistant', content: 'Salut ! Je suis Kévin. Comment puis-je vous aider aujourd’hui ?' });
				saveConversation();
			}
			saveConversation();
			scrollChatToBottom();
		}

		async function sendMessage(text) {
			if (isSending || !text.trim()) return;
			isSending = true;
			chatbotInput.disabled = true;
			appendMessage('user', text);
			conversationMessages.push({ role: 'user', content: text });
			saveConversation();

			const status = document.createElement('div');
			status.className = 'chatbot-status typing';
			status.innerHTML = 'Kévin réfléchit <span class="typing-dots"><span></span><span></span><span></span></span>';
			chatbotMessages.appendChild(status);
			scrollChatToBottom();
			setStatusChip('busy', 'Busy');

			try {
				const apiUrl = '../common/gemini-chat-api';
			const response = await fetch(apiUrl, {
				method: 'POST',
				credentials: 'include',
				headers: {
					'Content-Type': 'application/json',
					'X-Requested-With': 'XMLHttpRequest'
				},
				body: JSON.stringify({ messages: conversationMessages })
			});

			if (!response.ok) {
				const errorData = await response.json().catch(() => ({}));
				console.error('Chatbot response error', response.status, response.statusText, errorData);
					throw new Error(errorData.error || 'Unable to send message');
				}

				const data = await response.json();
				const answer = (data.text || 'Je n’ai pas réussi à répondre, veuillez réessayer.').trim();
				conversationMessages.push({ role: 'assistant', content: answer });
				appendMessage('assistant', answer);
				saveConversation();
				setStatusChip('ready', 'Ready');
			} catch (err) {
				conversationMessages.push({ role: 'assistant', content: 'Désolé, je n’ai pas pu traiter votre demande pour le moment.' });
				appendMessage('assistant', 'Désolé, je n’ai pas pu traiter votre demande pour le moment.');
				saveConversation();
				setStatusChip('error', 'Unavailable');
				console.error('Chatbot error', err);
			} finally {
				isSending = false;
				chatbotInput.disabled = false;
				chatbotInput.focus();
				status.remove();
			}
		}

		restoreConversation();

		chatbotOpenBtn && chatbotOpenBtn.addEventListener('click', () => {
			const isOpen = chatbotOverlay.classList.contains('is-open');
			setChatbotVisible(!isOpen);
		});

		chatbotCloseBtn && chatbotCloseBtn.addEventListener('click', () => setChatbotVisible(false));
		chatbotNewBtn && chatbotNewBtn.addEventListener('click', () => {
			setChatbotVisible(true);
			clearConversation();
		});

		chatbotForm && chatbotForm.addEventListener('submit', (event) => {
			event.preventDefault();
			const text = chatbotInput.value.trim();
			if (!text) return;
			chatbotInput.value = '';
			sendMessage(text);
		});

		document.addEventListener('keydown', (event) => {
			if (event.key === 'Escape' && chatbotOverlay.classList.contains('is-open')) {
				setChatbotVisible(false);
			}
		});