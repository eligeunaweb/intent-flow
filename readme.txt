=== Intent Flow — Workflow Automation ===
Contributors: adaptatuweb
Author: Álvaro Martínez - AdaptaTuWeb.com
Tags: automation, workflow, triggers, actions, webhooks
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.9.4.2.33
Text Domain: intent-flow
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automate WordPress without limits. No credits, no subscription, built-in scheduler, WCAG-accessible UI and GDPR-friendly self-hosted architecture.

== Description ==

**Intent Flow** is a powerful automation plugin for WordPress that lets you connect triggers (what happens) with actions (what to do automatically) — without limits, credits or subscriptions.

= Why Intent Flow? =

Most automation plugins either charge per execution, require a subscription, or send your data through external servers. Intent Flow is different:

* ✅ **No credits** — unlimited executions, forever free
* ✅ **No subscription** — one-time payment for Pro, free version stays free
* ✅ **Self-hosted** — your data never leaves your server (GDPR-friendly)
* ✅ **Built-in scheduler** — cron jobs included in the free version
* ✅ **Dry-run mode** — test automations safely before going live
* ✅ **WCAG accessible UI** — the only automation plugin built for accessibility
* ✅ **Retry policy** — automatic retry with exponential backoff on failures
* ✅ **Permanent audit log** — full execution history, never deleted

= Free Version Triggers =

**WordPress — Users**
* User registered
* User login / Login failed / User logout
* User profile updated
* User password reset
* User deleted
* User role changed

**WordPress — Content**
* Post created / Post updated / Post deleted / Post trashed
* Post status changed
* Comment posted
* Plugin updated

**System**
* Cron tick (scheduler)
* Webhook received

= Free Version Actions =

**WordPress**
* Send Email (wp_mail)
* Create User
* Add Role to User
* Update User Meta
* Create Post
* Set Option

**Integrations**
* HTTP Request (any URL)
* Slack webhook
* Telegram
* Zapier Catch Hook

= Ready-to-use Recipes =

Get started in seconds with 12 pre-built automation recipes:

* 👤 Welcome email on user registration
* 🔔 Notify admin on new user registration
* 🚨 Alert on failed login attempt
* 🔑 Notify on user role change
* 📝 Notify admin on post status change
* 🔗 Send post data to external webhook
* 💬 Notify admin on new comment
* 🔗 Forward incoming webhook to external URL
* 📨 Incoming webhook to Slack
* 🔄 Notify on plugin update
* 📢 Post created to Slack
* ⏰ Daily report by email (cron)

= Pro Version =

The Pro version adds:

* WooCommerce triggers and actions (order created, status changed, customer registered...)
* Google Sheets integration
* Multi-site support
* Loops / bulk actions over users or posts
* Advanced delays and scheduling
* Premium pre-built recipes
* Priority support

= How it works =

1. Go to **Intent Flow → Events** and create a rule: choose a trigger event and an action
2. Or go to **Intent Flow → Flows** and pick a ready-to-use recipe
3. The automation runs automatically whenever the trigger fires
4. Check **Intent Flow → Logs** to see execution history

= Accessibility =

Intent Flow is built with accessibility in mind. The admin UI follows WCAG 2.1 guidelines, making it usable for people with visual, motor or cognitive disabilities. It is the only WordPress automation plugin with a fully accessible interface.

= Privacy / GDPR =

All data stays on your server. No external API calls are made unless you explicitly configure an integration (Slack, HTTP, etc.). No telemetry, no tracking, no data collection by the plugin author.


== External Services ==

This plugin optionally connects to the following external services:

= Adapta Tu Web Licensing API =

If you choose to activate a Pro license, the plugin connects to the Adapta Tu Web licensing API to validate your license key.

* **What data is sent:** Your license key, site URL, domain, WordPress version, PHP version and plugin version.
* **When:** Only when you activate, deactivate or validate a license from the License page in the plugin settings.
* **Service provider:** Adapta Tu Web (adaptatuweb.com)
* **Terms of Service:** https://adaptatuweb.com/condiciones-generales-de-contratacion/
* **Privacy Policy:** https://adaptatuweb.com/politica-de-privacidad/

No data is sent to this service unless you explicitly enter and activate a license key.

= Telegram =

If you configure a Telegram connection and use the Telegram module in your automations, the plugin will send messages to the Telegram API.

* **What data is sent:** The message content you configure in your automation flows, sent to your Telegram bot.
* **When:** Only when an automation you have configured triggers a Telegram action.
* **Service provider:** Telegram Messenger Inc.
* **Terms of Service:** https://telegram.org/tos
* **Privacy Policy:** https://telegram.org/privacy

No data is sent to Telegram unless you explicitly configure a Telegram connection and create an automation that uses it.

= OpenAI =

If you configure an OpenAI connection and use any AI action (generate, summarize, classify, translate or moderate), the plugin sends data to the OpenAI API.

* **What data is sent:** The text content you configure in your automation flows (e.g. post body, comment text, user-provided string).
* **When:** Only when an automation you have configured triggers an OpenAI action.
* **Service provider:** OpenAI, L.L.C.
* **Terms of Service:** https://openai.com/policies/terms-of-use
* **Privacy Policy:** https://openai.com/policies/privacy-policy

No data is sent to OpenAI unless you explicitly configure an OpenAI connection and create an automation that uses it.

= WhatsApp Business (Meta) =

If you configure a WhatsApp Business connection and use WhatsApp actions, the plugin sends messages via the Meta WhatsApp Cloud API.

* **What data is sent:** The recipient phone number and message content you configure in your automation flows.
* **When:** Only when an automation you have configured triggers a WhatsApp action.
* **Service provider:** Meta Platforms, Inc.
* **Terms of Service:** https://www.whatsapp.com/legal/terms-of-service
* **Privacy Policy:** https://www.whatsapp.com/legal/privacy-policy

No data is sent to WhatsApp unless you explicitly configure a WhatsApp connection and create an automation that uses it.

= Slack =

If you configure a Slack webhook connection, the plugin will send messages to Slack.

* **What data is sent:** The message content you configure in your automation flows.
* **When:** Only when an automation you have configured triggers a Slack action.
* **Service provider:** Slack Technologies LLC
* **Terms of Service:** https://slack.com/terms-of-service
* **Privacy Policy:** https://slack.com/privacy-policy

= HTTP Requests =

The plugin can be configured to send HTTP requests to any URL you specify. This is a general-purpose integration feature.

* **What data is sent:** Only the data you explicitly configure in your automation flows.
* **When:** Only when an automation you have configured triggers an HTTP action.
* **Service provider:** Whichever service you configure (entirely under your control).

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/intent-flow/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **Intent Flow** in the admin menu
4. Create your first automation in **Events** or use a recipe in **Flows**

== Frequently Asked Questions ==

= Is it really free with no limits? =

Yes. The free version has no execution limits, no credits and no expiration. You can run as many automations as you want.

= Is my data safe? =

All data is stored in your WordPress database. Nothing is sent to external servers unless you configure an integration yourself.

= Does it work with WooCommerce? =

WooCommerce support is available in the Pro version.

= Can I test automations before they run for real? =

Yes. Enable **dry-run mode** in Settings. All actions will be simulated without actually executing anything.

= Is there a retry policy for failed actions? =

Yes. Each event rule supports configurable retries with exponential backoff.

= Where can I see what happened? =

Go to **Intent Flow → Logs** for a full audit trail of all executions.

== Screenshots ==

1. Dashboard — quick access to all sections
2. Events — create automations with trigger + action dropdowns
3. Flows — visual automation builder with ready-to-use recipes
4. Logs — full execution history with filters
5. Settings — dry-run and safe mode toggles

== Changelog ==

= 0.9.4.2.33 =
* Added 12 WordPress triggers (user registration, login, role changes, comments, post status...)
* Added 6 WordPress actions (send email, create user, add role, update meta, create post, set option)
* Added Events UI with trigger/action dropdowns and tooltips
* Added 12 ready-to-use automation recipes
* Added Settings UI with dry-run and safe-mode toggles
* Added permanent audit log
* Added retry policy with exponential backoff
* Added dry-run mode for safe testing

= 0.9.0 =
* Initial public release

== Upgrade Notice ==

= 0.9.4.2.33 =
Major update: new Events UI, 12 WordPress triggers, 6 WordPress actions, ready-to-use recipes.
