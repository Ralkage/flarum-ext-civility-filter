# Civility Filter

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

AI-powered content moderation extension for [Flarum](https://flarum.org) that automatically analyzes posts for civility using **Anthropic Claude**, **OpenAI GPT**, or **OpenRouter**.

## Features

### AI Analysis
- Analyzes posts in real-time before they're published
- Supports **Anthropic Claude** (Haiku, Sonnet, Opus), **OpenAI GPT** (4o-mini, 4o, 4.1), and **OpenRouter** (200+ models)
- Customizable AI prompt for tailored moderation rules
- Fail-open design — posts go through if the API is unavailable

### 4-Tier Action System
| Action | Default Threshold | Behavior |
|---|---|---|
| **Allowed** | 0–59 | Post published normally |
| **Warned** | 60–79 | Post published, logged, user notified |
| **Moderated** | 80–94 | Post held in moderation queue for review |
| **Blocked** | 95–100 | Post rejected, user sees error message |

All thresholds are configurable in 5-point increments.

### Content Filtering
- **Word Blocklist** — Instant block on matching keywords without calling the AI (saves API costs)
- **Tag-Based Monitoring** — Only analyze posts in specific tags, or monitor all
- **Quoted Content Exclusion** — Strips quoted text before analysis so users aren't penalized for quoting others

### Moderation Tools
- **Civility Log** — Paginated, filterable log of all analysis results
- **Statistics Dashboard** — Action breakdown, top categories, top offenders, and daily trend charts
- **Test Analyzer** — Test messages against the AI before deploying settings
- **CSV Export** — Download the full civility log for offline review
- **Quick Actions** — Approve, delete, or suspend directly from the log table

### Automated Enforcement
- **Auto-Suspend** — Automatically suspend users after X violations within a configurable time window
- **API Rate Limiting** — Cap AI API calls per hour to control costs during traffic spikes

### Notifications
- **In-App Alerts** — Users are notified when their posts are warned or moderated
- **Discord/Webhook Alerts** — Send rich embeds to Discord or JSON payloads to any webhook URL when posts are flagged

### Forum Integration
- **Post Badges** — Visual notices on warned/moderated posts (visible to author and staff only)
- **User Profile History** — Per-user civility stats and recent violations (admin only)
- **Bypass Permission** — Exempt trusted users/groups from civility checks

## Installation

```bash
composer require ralkage/flarum-ext-civility-filter
php flarum migrate
php flarum cache:clear
```

Enable the extension in the admin panel under **Extensions > Civility Filter**.

## Configuration

Navigate to the extension settings page in the admin panel. The settings are organized into sections:

### General
- **Enable Civility Filter** — Master on/off switch

### AI Provider
- **AI Provider** — Choose between Anthropic (Claude), OpenAI (GPT), or OpenRouter
- **Anthropic API Key** — Your Anthropic API key
- **OpenAI API Key** — Your OpenAI API key
- **OpenRouter API Key** — Your OpenRouter API key (access 200+ models from one API)
- **AI Model** — Select the model for your chosen provider

### Thresholds
- **Warn Threshold** — Score to trigger a warning (default: 60)
- **Hold/Moderate Threshold** — Score to hold for moderation (default: 80)
- **Block Threshold** — Score to block the post (default: 95)

### Filtering
- **Monitored Tags** — Multi-select tag picker to limit which tags are monitored
- **Word Blocklist** — One word/phrase per line, instantly blocks without AI

### Custom Prompt
- **Custom AI Prompt** — Override the default analysis prompt with your own instructions

### Auto-Suspend
- **Threshold** — Number of violations before auto-suspend (0 = disabled)
- **Duration** — Suspension length in days
- **Window** — Count violations within this many days

### Webhooks
- **Webhook URL** — Discord webhook or generic endpoint
- **Minimum Action** — Only alert for this severity or higher

### Logging & Limits
- **Log All Checks** — Include passing posts in the log
- **Rate Limit** — Maximum API calls per hour (0 = unlimited)

## Permissions

| Permission | Description |
|---|---|
| Bypass Civility Filter | Users with this permission skip all civility analysis |

Configure under the **Permissions** section of the extension settings page.

## API Endpoints

All endpoints require admin authentication unless noted.

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/civility-logs` | List logs (paginated, filterable) |
| `DELETE` | `/api/civility-logs` | Clear all logs |
| `GET` | `/api/civility-logs/export` | Download CSV export |
| `GET` | `/api/civility-logs/stats` | Statistics and trends |
| `POST` | `/api/civility-logs/test` | Test analyzer |
| `POST` | `/api/civility-logs/moderate` | Quick actions (approve/delete/suspend) |
| `GET` | `/api/civility-logs/user?userId=X` | Per-user civility history |

## Scoring Guide

The AI evaluates posts on a 0–100 scale:

| Score Range | Meaning |
|---|---|
| 0–20 | Civil, constructive, or neutral |
| 21–40 | Mildly rude or snarky but not harmful |
| 41–60 | Hostile tone, dismissive, or antagonistic |
| 61–80 | Personal attacks, inflammatory language, bad-faith arguing |
| 81–95 | Hate speech, severe harassment, threats |
| 96–100 | Extreme abuse or dangerous content |

Political disagreement alone is **not** considered uncivil. The AI focuses on *how* something is said, not *what* position is taken.

## Categories

The AI categorizes issues found in posts:

`personal_attack` · `inflammatory` · `hate_speech` · `bad_faith` · `trolling` · `harassment` · `threat` · `profanity` · `discrimination`

## Requirements

- Flarum `^1.8`
- PHP 8.0+
- An API key from [Anthropic](https://console.anthropic.com/), [OpenAI](https://platform.openai.com/), or [OpenRouter](https://openrouter.ai/)

## Links

- [Ralkage](https://ralkage.com)

## License

MIT License. See [LICENSE](LICENSE) for details.
