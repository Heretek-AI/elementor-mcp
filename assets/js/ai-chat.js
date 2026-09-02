(function($) {
	'use strict';
	$(document).ready(function() {
		if ($('#emcp-ai-chat-container').length) {
			return;
		}

		var html = '<div id="emcp-ai-chat-container">' +
			'<div class="emcp-ai-chat-fab" title="EMCP AI Assistant">' +
			'<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 0 1 10 10c0 5.523-4.477 10-10 10a10 10 0 0 1-10-10A10 10 0 0 1 12 2z"></path><path d="M8 12h8"></path><path d="M12 8v8"></path></svg>' +
			'</div>' +
			'<div class="emcp-ai-chat-panel">' +
			'<div class="emcp-ai-chat-header">' +
			'<span>EMCP AI Assistant</span>' +
			'<button type="button" class="emcp-ai-chat-close" style="background:none;border:none;color:#fff;cursor:pointer;font-size:18px;">&times;</button>' +
			'</div>' +
			'<div class="emcp-ai-chat-messages">' +
			'<div class="emcp-msg emcp-msg-assistant">Hello! How can I help you build or customize this page?</div>' +
			'</div>' +
			'<div class="emcp-ai-chat-input-row">' +
			'<input type="text" class="emcp-ai-chat-input" placeholder="Ask AI assistant..." />' +
			'<button type="button" class="button button-primary emcp-ai-chat-send">Send</button>' +
			'</div>' +
			'</div>' +
			'</div>';

		$('body').append(html);

		$('.emcp-ai-chat-fab').on('click', function() {
			$('.emcp-ai-chat-panel').toggleClass('is-open');
		});

		$('.emcp-ai-chat-close').on('click', function() {
			$('.emcp-ai-chat-panel').removeClass('is-open');
		});

		function sendMessage() {
			var input = $('.emcp-ai-chat-input');
			var text = input.val().trim();
			if (!text) {
				return;
			}
			input.val('');

			var messagesContainer = $('.emcp-ai-chat-messages');
			messagesContainer.append('<div class="emcp-msg emcp-msg-user">' + $('<div>').text(text).html() + '</div>');
			messagesContainer.scrollTop(messagesContainer[0].scrollHeight);

			messagesContainer.append('<div class="emcp-msg emcp-msg-assistant emcp-loading">Thinking...</div>');

			$.ajax({
				url: window.emcpAiChat ? window.emcpAiChat.endpoint : '/wp-json/emcp/v1/chat',
				method: 'POST',
				beforeSend: function(xhr) {
					if (window.emcpAiChat && window.emcpAiChat.nonce) {
						xhr.setRequestHeader('X-WP-Nonce', window.emcpAiChat.nonce);
					}
				},
				contentType: 'application/json',
				data: JSON.stringify({
					messages: [
						{ role: 'user', content: text }
					]
				}),
				success: function(res) {
					$('.emcp-loading').remove();
					var reply = (res && res.choices && res.choices[0] && res.choices[0].message) ?
						res.choices[0].message.content :
						(res && res.content && res.content[0] ? res.content[0].text : 'Response received.');
					messagesContainer.append('<div class="emcp-msg emcp-msg-assistant">' + $('<div>').text(reply).html() + '</div>');
					messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
				},
				error: function(xhr) {
					$('.emcp-loading').remove();
					messagesContainer.append('<div class="emcp-msg emcp-msg-assistant" style="color:red;">Error connecting to AI service. Please check API key in settings.</div>');
				}
			});
		}

		$('.emcp-ai-chat-send').on('click', sendMessage);
		$('.emcp-ai-chat-input').on('keydown', function(e) {
			if (e.key === 'Enter') {
				sendMessage();
			}
		});
	});
})(jQuery);
