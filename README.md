# ⚔️ MU Online Advanced Webshop & CMS

A lightweight, highly advanced Content Management System and Webshop built specifically for MU Online private servers. Written in PHP and optimized for Microsoft SQL Server, this CMS features full dual-server support, an automated `.txt` file configuration parser, and a dynamic hex-injected Webshop.

## ✨ Key Features

### 🛒 Advanced Hex Webshop
* **Direct-to-Warehouse Delivery:** Uses a custom "Tetris Algorithm" to automatically find empty slots in the player's in-game warehouse and safely inject the item Hex string.
* **Full Item Customization:** Players can add Level, Luck, Skill, Excellent Options, Sockets, 380 Options, Harmony (Yellow Option), and Ancient Tier bonuses.
* **Dynamic Ancient Sets:** The Webshop dynamically reads the Ancient Set name (e.g., "Kantata" or "Warrior") and displays the correct stamina tiers (+5 or +10).
* **Offline Protection:** Automatically detects the "Ghost Connection" bug and prevents players from buying items while their `ConnectStat` is stuck online.

### ⚙️ Automated Admin Uploader
Stop manually checking boxes for hundreds of items! The Admin Control Panel includes an automated parser. Just upload your server's text files:
* `Item.txt` (Base sizes and names)
* `SocketItemType.txt` (Auto-detects max sockets)
* `380ItemType.txt` (Auto-enables PvP options)
* `SetItemType.txt` & `SetItemOption.txt` (Auto-maps Ancient Set names to specific items)

### 👥 User Dashboard & Character Management
* **Dual-Server Support:** Manage characters and credits across Server 1 (Mid-Rate) and Server 2 (Hard-Rate) seamlessly.
* **Self-Service Tools:** Players can Reset Characters, Reset Stats, Clear PK status, Reset Master Levels, and Unstuck their characters.
* **Currency Converter:** Convert WebCredits into WCoinC, WCoinP, or GoblinPoints dynamically based on Admin-defined exchange rates.

---

## 📋 Prerequisites

To run this CMS on your production server, you need the following environment:
* **Web Server:** Windows IIS, Apache (XAMPP/Laragon), or Nginx.
* **PHP:** PHP 7.4 or PHP 8.x
* **Required Extensions:** `sqlsrv`, `openssl`, `mbstring`, `fileinfo`
* **Database:** Microsoft SQL Server (2008 R2, 2012, 2017, 2019, etc.)
* **Microsoft ODBC Driver:** ODBC Driver 17 or 18 for SQL Server installed on the host machine.

---

## 🚀 Installation Guide

### 1. Database Setup
1. Open SQL Server Management Studio (SSMS).
2. Select your MU Online database.
3. Run the provided SQL installation script to create the custom `WebCredits` and `WebshopItems` tables. *(If using a dual-server setup, run this on both databases).*

### 2. Web Server Setup
1. Clone or download this repository into your web server's public directory (e.g., `htdocs` or `www`).
2. Ensure the web server has **Write/Modify permissions** for the following folders:
   * `/Configuration/` (to save `settings.json`)
   * `/uploads/` (for custom logos and favicons)

### 3. Initial Configuration
1. Navigate to the Admin Control Panel via your browser (`http://your-ip/AdminCP/`).
2. Log in using the default credentials.
3. Go to **Settings -> Database** and input your SQL Server credentials for Server 1 and Server 2.
4. Save the settings.

### 4. Populate the Webshop
1. In the Admin Panel, go to **User Settings -> Upload Webshop Files**.
2. Select your server's `Item.txt`, `SocketItemType.txt`, `380ItemType.txt`, `SetItemType.txt`, and `SetItemOption.txt`.
3. Click **Parse & Upload**. The CMS will take ~2 seconds to map all your items, limits, and options to the database automatically!

---

## 🛠️ Built With
* **Backend:** PHP (Vanilla)
* **Frontend:** HTML5, CSS3, Vanilla JavaScript
* **Database:** Microsoft SQL Server (T-SQL / sqlsrv)

## 📸 Screenshots
*(Optional: Add images of your Webshop, Admin Panel, and User Dashboard here!)*
* `![User Dashboard](link_to_image)`
* `![Admin Panel](link_to_image)`

## ⚠️ Important Notes
* Ensure players are completely logged out of the game before using the Webshop to prevent database deadlocks or item duping.
* The `user-action.php` script contains a built-in fix for the MU Online "Ghost Connection" bug when a player uses the Unstuck feature.
