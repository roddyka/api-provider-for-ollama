# Promptiva Connector for Ollama

> **Connect WordPress 7.0 AI to your own Ollama server — no API key, no cloud, no cost.**

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)
[![WordPress 7.0+](https://img.shields.io/badge/WordPress-7.0%2B-blue?logo=wordpress)](https://wordpress.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php)](https://php.net)
[![Ollama](https://img.shields.io/badge/Ollama-OpenAI%20Compatible-black)](https://ollama.com)

WordPress 7.0 ships with a native AI integration layer. The problem? It only comes with connectors for cloud providers that require an API key. **This plugin bridges that gap**, wiring the new WordPress AI Client directly to your local (or self-hosted) [Ollama](https://ollama.com) instance.

Run Llama 3, Mistral, Gemma, Phi, Qwen — any model Ollama supports — and use it to power WordPress AI features without sending a single token to the cloud.

---

## ✨ Features

| Feature | Details |
|---|---|
| **Zero API key** | Ollama uses its own local auth; no credentials needed |
| **Dynamic model list** | Auto-discovers every model installed in Ollama via `/v1/models` |
| **OpenAI-compatible** | Uses Ollama's `/v1/chat/completions` endpoint |
| **wp-admin UI** | Custom connector card under **Settings → Connectors** |
| **Runtime diagnostics** | Card shows connection status and available models live |
| **Flexible base URL** | Option → constant → env var → sensible default |
| **WP AI preferred models** | Injects your default model at the top of the preference list |
| **GPL-2.0-or-later** | Free as in freedom |

---

## Requirements

| Requirement | Minimum |
|---|---|
| WordPress | **7.0** (AI Client API introduced) |
| PHP | **7.4** |
| [Ollama](https://ollama.com/download) | Any recent release with OpenAI-compatible endpoint |

> **WordPress 7.0 is required.** This plugin depends on `WordPress\AiClient`, the `wp_connectors_init` hook, and `@wordpress/connectors` — all introduced in WP 7.0.

---

## Installation

### From this repository (recommended for testing)

```bash
# Clone directly into your wp-content/plugins directory
cd /path/to/wordpress/wp-content/plugins
git clone https://github.com/roddyka/api-provider-for-ollama.git promptiva-connector-for-ollama

```

Then activate it from **Plugins → Installed Plugins** in wp-admin.

### Manual upload

1. Download the `.zip` from [Releases](https://github.com/roddyka/api-provider-for-ollama/releases)
2. In wp-admin go to **Plugins → Add New → Upload Plugin**
3. Choose the zip, install and activate

---

## Quick Start

1. **Install Ollama** → [ollama.com/download](https://ollama.com/download)
2. **Pull a model** (example):
   ```bash
   ollama pull llama3
   ```
3. **Verify Ollama is running** at `http://127.0.0.1:11434`
4. **Activate this plugin** in WordPress
5. Go to **Settings → Connectors** and open the **Ollama Local** card
6. The card will auto-detect your running models — pick a default and save

That's it. WordPress AI features (Block Editor AI, AI assistants, etc.) will now use your local Ollama instance.

---

## Configuration

The base URL is resolved in this priority order:

| Source | Example |
|---|---|
| wp-admin Connector card | Saved to `wp_options` |
| PHP constant | `define('OLLAMA_API_BASE_URL', 'http://my-server:11434');` |
| Environment variable | `OLLAMA_API_BASE_URL=http://my-server:11434` |
| Built-in default | `http://127.0.0.1:11434/v1` |

The plugin automatically appends `/v1` if you omit it.

### Remote Ollama

If Ollama runs on a different machine or Docker container, just set the base URL to that host. Make sure the Ollama port (default `11434`) is accessible from your WordPress server.

---

## Architecture

```
promptiva-connector-for-ollama.php  ← entry point, hooks, REST endpoint, settings
src/
  autoload.php           ← lightweight PSR-4 autoloader (no Composer needed)
  Provider/
    OllamaProvider.php   ← extends AbstractApiProvider; registers with WP AI Client
  Models/
    OllamaTextGenerationModel.php  ← OpenAI-compatible chat completions
  Metadata/
    OllamaModelMetadataDirectory.php  ← dynamic model discovery via /v1/models
  Support/
    OllamaConfig.php     ← centralised URL / model config with priority chain
assets/
  connector-card.js      ← React UI for the wp-admin Connectors screen
```

### How the WP 7.0 AI integration works

```
WordPress AI Client (core)
       │
       ├─ asks registry for available providers
       │
       └─ OllamaProvider (this plugin)
              │
              ├─ OllamaModelMetadataDirectory  →  GET /v1/models
              └─ OllamaTextGenerationModel     →  POST /v1/chat/completions
```

---

## Contributing

Contributions are welcome and encouraged! Here's how to get started:

### 1. Fork & clone

```bash
git clone https://github.com/roddyka/api-provider-for-ollama.git
cd api-provider-for-ollama
```

### 2. Set up your environment

- PHP 7.4+ with `php` in your PATH
- A local WordPress 7.0 install (e.g. [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/), LocalWP, or plain LAMP/MAMP)
- Ollama running locally

### 3. Install into WordPress

Symlink or copy the plugin folder into `wp-content/plugins/promptiva-connector-for-ollama/` and activate.

### 4. Make your changes

The codebase is intentionally small and dependency-free (no Composer). Key extension points:

| Want to… | Look at… |
|---|---|
| Add image/multimodal support | `OllamaModelMetadataDirectory` — extend `$textCapabilities` |
| Support streaming responses | `OllamaTextGenerationModel` — override request handling |
| Add vision/embedding models | New model class extending the base |
| Improve the admin UI | `assets/connector-card.js` (plain React via `wp.element`) |
| Add more config options | `OllamaConfig.php` + `promptiva-connector-for-ollama.php` REST callback |

### 5. Open a Pull Request

- Keep PRs focused on one thing
- Describe *what* and *why* in the PR description
- If it's a new feature, open an issue first to discuss

### Reporting bugs

[Open an issue](https://github.com/roddyka/api-provider-for-ollama/issues) with:
- WordPress version
- PHP version
- Ollama version & model(s) in use
- Steps to reproduce

---

## Roadmap

- [ ] Streaming response support
- [ ] Image / multimodal model support (LLaVA, Moondream)
- [ ] Embedding model support
- [ ] WordPress.org plugin directory submission
- [ ] Unit test suite (PHPUnit)
- [ ] GitHub Actions CI

---

## FAQ

**Q: Does this work with Ollama on Docker / a remote server?**  
A: Yes. Set the base URL in the connector card or via the `OLLAMA_API_BASE_URL` constant/env var.

**Q: Does it conflict with other AI providers (OpenAI, Anthropic)?**  
A: No. It registers as a separate provider (`ollama-local`) alongside any other connectors.

**Q: What models are supported?**  
A: Any text generation model you have pulled in Ollama. The plugin discovers them automatically.

**Q: I see "unreachable" in the card — what do I do?**  
A: Make sure `ollama serve` is running and accessible from the WordPress server. Try `curl http://127.0.0.1:11434/v1/models`.

---

## License

[GPL-2.0-or-later](https://spdx.org/licenses/GPL-2.0-or-later.html) — free and open source.

Example:

```php
define('OLLAMA_API_BASE_URL', 'http://127.0.0.1:11434/v1');
```

## Usage

```php
use WordPress\AiClient\AiClient;

$result = AiClient::prompt('Say hello in Portuguese.')
    ->usingProvider('ollama-local')
    ->generateTextResult();

echo $result->toText();
```

## Notes

- This first version focuses on text generation.
- The provider relies on Ollama's OpenAI-compatible APIs (`/v1/models` and `/v1/chat/completions`).
- The Connectors screen lists this provider as keyless (`authentication: none`), which is the normal setup for local Ollama.
- The current WordPress Connectors UI only supports built-in auth fields (`api_key` or `none`), so custom fields like base URL/model are not first-class form inputs there yet.
