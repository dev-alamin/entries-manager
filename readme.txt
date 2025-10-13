=== EntryDashboard – Entry Manager for Forms ===
Contributors: coderalamin, entriesmanager
Tags: form entries, submissions, wpforms submission, contact form 7 submission, google sheets sync
Requires at least: 5.4
Tested up to: 6.8
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Centralized dashboard to save, manage, search, and sync form submissions from WPForms, CF7, Elementor & more — turn WordPress into a mini-CRM.

== Description ==

**EntryDashboard – Forms Entries Manager** gives you a powerful way to manage, search, and organize submissions from multiple form plugins in one clean interface.  

No more digging through emails or scattered plugin screens. With EntryDashboard, you get a **centralized entries hub** for popular plugins like **WPForms**, **Contact Form 7**, **Elementor Forms**, and more (with additional integrations coming soon).  

Whether you want to **search**, **filter**, **export**, or **sync with Google Sheets**, EntryDashboard makes it simple to treat form submissions like the valuable leads and data they truly are.  

Think of it as your **mini-CRM**—but directly inside WordPress.

---

### ✨ Why EntryDashboard?

* **All Entries, One Dashboard** – A unified view for submissions across supported form plugins.  
* **Mini-CRM Features** – Add private notes, star favorites, and keep track of follow-ups.  
* **Google Sheets Integration** – Secure OAuth 2.0 connection, no API keys required.  
* **Smarter Workflow** – Bulk actions, status management, quick-view modals, and more.  
* **Export Data Easily** – Export filtered entries to CSV.  

---

### 🚀 Free Features

* Centralized entry listing with counts for all supported forms.  
* Grouping per form in admin dashbaord
* Clean modal view to display entry details.  
* Copy entry easily
* Read/Unread status management.  
* Favorite (star) important entries.  
* Bulk actions for deleting, marking status, and more.  
* Global search and date range filtering.  
* Print-friendly entry view.  
* Notes & comments on entries (mini-CRM style).  
* Google Sheets integration with OAuth 2.0 (manual and auto sync).
* Google Sheet Sync (upto 500 rows) still it will be saved in db and thus resync after upgrade
* Manually Sync individual entry (Click every entry to send in sheet)
* Manually Unsync individual entry (Remove from sheet)
* Live view of connected email for entry sync
* Export to CSV.  

---

### 🌟 Premium (Pro) Features  

* Unlimited Google sheet rows
* Failed entry re-sync to google sheet
* Bulk, Asyn entry sync to google sheet with Action Scheduler
* Export entries to **Excel (XLSX)** and **PDF**.  
* Advanced reporting and analytics.  
* Integrations with **Gravity Forms**, **Ninja Forms**, and more.  
* Team collaboration tools (assign entries, activity log).  
* Extended Google Sheets sync options (conditional sync, multiple sheets).  
* Priority support and updates.

Manage all your form entries effortlessly with **[EntryDashboard Pro - Entries Manager](https://entriesmanager.com/)**!

---

### Supported Form Plugins

* Works with **WPForms** (Lite & Pro).  
* Works with **Contact Form 7**.  
* Works with **Elementor Forms**.  
* More integrations (like Gravity Forms, Ninja Forms) coming soon.  

---

== External services ==

This plugin utilizes two external services to provide its core features: its own **EntryDashboard Backend Service** and the **Google Sheets API**.

### 1. EntryDashboard Backend Service (backend.entriesmanager.com)

This service acts as a secure intermediary for handling authentication and site features.

**What it is used for:**
* **Secure Authentication Proxy:** It helps manage the secure OAuth 2.0 connection and refresh tokens for Google Sheets integration. This prevents highly sensitive tokens from being fully exposed on the WordPress site.
* **License/Feature Verification:** It is used to verify the status of the EntryDashboard Pro license and enable premium features.

**What data is sent and when:**
* **During initial connection/refresh:** Your website's URL (e.g., `https://your-site.com`) is sent to the backend service to identify the site requesting tokens or feature verification.
* **During Google Sheets setup:** An authorization code is temporarily sent to exchange for the secure access tokens.
* **No form entry data is sent to this backend service.**

**Service Provider:**
* EntryDashboard (The plugin author)
* **Terms of Service:** https://entriesmanager.com/terms-of-service/
* **Privacy Policy:** https://entriesmanager.com/privacy-policy/

### 2. Google Sheets API (Google LLC)

This service is used to sync your form submission data directly to your designated Google Sheet.

**What it does:**
* Sends form entry data (like name, email, and submission fields) to your designated Google Sheet when syncing.
* Receives data updates if using bidirectional sync (if applicable).

**When data is sent:**
* Each time an entry is synced manually or automatically via scheduled sync.

**Service Provider:**
* Google Sheets API (Google LLC)
* Terms of Service: https://policies.google.com/terms
* Privacy Policy: https://policies.google.com/privacy

**User Control:**
* Users can choose which forms and which fields are synced.
* User can sync, unsync manually from dashboard.
* Sync can be disabled at any time in the plugin settings.

---

== Third-Party Libraries and Source Code ==

This plugin includes several third-party libraries that are distributed in minified or compiled format.  
In compliance with the WordPress.org Plugin Guidelines, links to their original public source code are provided below:

* **Alpine.js (`alpine.min.js`)**  
  * **Purpose:** Provides a lightweight, declarative JavaScript framework for building reactive UI components.  
  * **Source Code:** https://github.com/alpinejs/alpine  

* **Lottie Player (`lottie-player.js`)**  
  * **Purpose:** Renders Lottie animations (JSON-based vector animations) for UI feedback and interactions.  
  * **Source Code:** https://github.com/LottieFiles/lottie-player  

* **Tailwind CSS (`tailwind.min.js` or `tailwind.min.css`)**  
  * **Purpose:** Utility-first CSS framework used to style the admin UI.  
  * **Source Code:** https://github.com/tailwindlabs/tailwindcss  

* **Collapse Utility (`collapse.js`)**  
  * **Purpose:** Handles simple toggle/accordion interactions in the admin UI.  
  * **Source Code:** https://alpinejs.dev/plugins/collapse  

The rest of the plugin's code, including `admin.css` and `admin.js`, is maintained in human-readable format and is not obfuscated or minified.

== Installation ==

1. From your WordPress dashboard:  
   * Go to `Plugins > Add New`.  
   * Search for "EntryDashboard – Forms Entries Manager".  
   * Click `Install Now` → `Activate`.  

2. Manual upload:  
   * Download the `.zip`.  
   * Go to `Plugins > Add New` → `Upload Plugin`.  
   * Upload the `.zip` and activate.  

3. FTP method:  
   * Upload the `entrydashboard` folder to `/wp-content/plugins/`.  
   * Activate the plugin via `Plugins` in WordPress.  

---

== Frequently Asked Questions ==

**Which plugins are supported?**  
The free version supports WPForms, Contact Form 7, and Elementor Forms. Pro will add more integrations like Gravity Forms and Ninja Forms.  

**Is the Google Sheets connection secure?**  
Yes. EntryDashboard uses official Google OAuth 2.0. You connect directly with Google, and we never store your password. Access can be revoked anytime.  

**Can I export entries?**  
Yes. CSV export is included free. Excel and PDF export will be part of Pro.  

**Will this affect my website speed?**  
No. EntryDashboard only runs in your admin dashboard and won’t impact the front-end.  

---

== Screenshots ==

1. Dashboard overview showing all forms and entries.  
2. Clean entry listing with filters and search.  
3. Detailed modal view of a single entry.  
4. Bulk actions in use.  
5. Google Sheets OAuth authentication screen.  
6. Entry with notes added (mini-CRM functionality).  

---

== Changelog ==

= 1.0.0 =  
* Initial release.  
* Support for WPForms, Contact Form 7, Elementor Forms.  
* Entry listing, modal view, notes, favorites, status management.  
* Google Sheets sync (manual & auto).  
* CSV export.  

---

== Changelog ==

### 1.0.2
- Resolved all remaining **SQL Injection** vulnerabilities by enforcing safe $wpdb->prepare() usage across all files (8 incidences total).
- Corrected logic for safely handling **dynamic WHERE and SET clauses** in bulk actions and data fetching.
- Added required documentation for the use of the **external proxy service** (backend.entriesmanager.com) in readme.txt.

### 1.0.1
- Fixed missing and incorrect **nonces** in admin AJAX/REST requests.
- Improved **data sanitization and escaping** throughout the plugin.
- Updated **translation text domain** from `entrydashboard` to `entries-manager`.
- Prefixed all functions, classes, and global variables with `entr_mgr_` to avoid conflicts.
- Added documentation for **third-party libraries** (Alpine.js, Tailwind, Lottie Player, Collapse Utility).
- Fixed **SQL queries** to use `$wpdb->prepare()` for safety.
- Updated **external service documentation** (Google Sheets integration usage and API references).
- Ensured all distributed JS/CSS files are in **readable format** or linked with their public sources.
- Added this **changelog and readme updates** to comply with WordPress.org Plugin Guidelines.


= 1.0.0 =  
Welcome to EntryDashboard! A new way to manage your form submissions from WPForms, Contact Form 7, Elementor, and more—all in one place.  
