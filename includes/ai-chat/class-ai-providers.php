<?php
/**
 * AI Providers Catalog.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_AI_Providers {

	public static function get_providers(): array {
		return array(
			'openai'     => array(
				'name'     => 'OpenAI',
				'endpoint' => 'https://api.openai.com/v1/chat/completions',
				'models'   => array( 'gpt-4o', 'gpt-4o-mini', 'o3-mini' ),
			),
			'anthropic'  => array(
				'name'     => 'Anthropic',
				'endpoint' => 'https://api.anthropic.com/v1/messages',
				'models'   => array( 'claude-3-5-sonnet-20241022', 'claude-3-5-haiku-20241022' ),
			),
			'openrouter' => array(
				'name'     => 'OpenRouter',
				'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
				'models'   => array( 'anthropic/claude-3.5-sonnet', 'openai/gpt-4o', 'deepseek/deepseek-r1' ),
			),
			'groq'       => array(
				'name'     => 'Groq',
				'endpoint' => 'https://api.groq.com/openai/v1/chat/completions',
				'models'   => array( 'llama-3.3-70b-versatile', 'mixtral-8x7b-32768' ),
			),
			'ollama'     => array(
				'name'     => 'Ollama (Local)',
				'endpoint' => 'http://localhost:11434/v1/chat/completions',
				'models'   => array( 'llama3.1', 'mistral', 'qwen2.5-coder' ),
			),
		);
	}
}
