# Mu Online Advanced User Dashboard & Webshop

A modern, fully-featured User Dashboard, Webshop, and Admin Control Panel designed for Mu Online private servers. This dashboard features a secure PHP backend, a dynamic JavaScript frontend, and fully automated donation systems supporting PayPal and PayMongo (GCash, Maya, QR Ph, and Cards).

## ✨ Features

* **💳 Automated Donations:**
    * **PayMongo Integration:** Fully automated GCash, Maya, and Credit Card payments via secure Webhooks.
    * **PayPal Integration:** Automated PayPal checkout and credit delivery.
    * **Manual QR Ph:** Allows players to upload screenshot proofs for manual Admin approval.
* **🛒 Advanced Webshop:**
    * Dynamic pricing based on item configurations.
    * Supports Item Level, Luck, Skill, Excellent Options, 380 Options, Harmony, Sockets, and Ancient options.
    * Direct-to-warehouse or direct-to-character delivery system (depending on your server's database structure).
* **⚙️ Character Management:**
    * Reset Character & Reset Stats
    * Clear PK Status
    * Reset Master Level
    * Unstuck Character
* **💱 Economy & Tools:**
    * Convert WebCredits to in-game currencies (WCoinC, WCoinP, GoblinPoints).
    * Live sidebar statistics (Top Rankings, Class Distribution, Server Jewel Economy).
    * Secure Change Password functionality.
* **🛡️ Admin Control Panel:**
    * Toggle modules on/off instantly without touching code.
    * Manage multi-server setups (e.g., Mid Rate vs. Hard Rate).
    * Configure API keys and currency conversion rates via a secure UI.

---

## 📋 Prerequisites

Before installing, ensure your web server meets the following requirements:
* **OS:** Windows Server (IIS) or Windows/Linux with XAMPP/WAMP.
* **PHP:** PHP 7.4 or PHP 8.x
* **Extensions:** * `sqlsrv` and `pdo_sqlsrv` (Required to connect to MS SQL Server).
    * `curl` (Required for PayPal and PayMongo APIs).
* **Database:** Microsoft SQL Server (Standard for Mu Online).

---

## 🚀 Installation Guide

### Step 1: Download & Extract
1. Download or clone this repository.
2. Extract the files into your web server's public directory (e.g., `C:\xampp\htdocs\` or `C:\inetpub\wwwroot\`).

### Step 2: Database Preparation
The donation system requires a table to store the players' WebCredits. Run this query in your SQL Server Management Studio (SSMS) inside your MuOnline database:

```sql
CREATE TABLE WebCredits (
    memb___id varchar(10) NOT NULL,
    credits int NOT NULL DEFAULT 0,
    PRIMARY KEY (memb___id)
);
