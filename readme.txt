=== Promptiva Connector for Ollama ===
Contributors: roddyka
Tags: ai, ollama, llm, connector, local-ai
Requires at least: 7.0
Tested up to: 7.0
Stable tag: 0.1.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Promptiva Connector for Ollama for the PHP AI Client SDK.

== Description ==

This plugin provides Ollama integration for the PHP AI Client SDK using OpenAI-compatible endpoints.

**Features:**

* Text generation via `/v1/chat/completions`
* Dynamic model listing via `/v1/models`
* Automatic provider registration
* Keyless/local setup support

**Requirements:**

* PHP 7.4 or higher
* Ollama server available
* WordPress AI Client plugin/runtime support

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/promptiva-connector-for-ollama/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure `OLLAMA_API_BASE_URL` (optional)

== Frequently Asked Questions ==

= Which endpoint does it use by default? =

`http://127.0.0.1:11434/v1`

= Does this plugin require an API key? =

No. This provider is configured as keyless by default.

== Changelog ==

= 0.1.0 =

* Initial release
* OpenAI-compatible text generation support for Ollama
