This is a massive milestone! Uploading your project to GitHub is a great way to show off what you've built, and having a polished README.md is what separates a good project from a great one.

Since your dashboard is specifically built for Mu Online (using MS SQL Server) and has advanced automated payment gateways, we need to make sure the installation instructions are incredibly clear for other server owners who want to use it.

Here is a complete, professional README.md file you can copy and paste directly into GitHub.

Mu Online Advanced User Dashboard & Webshop
A modern, fully-featured User Dashboard, Webshop, and Admin Control Panel designed for Mu Online private servers. This dashboard features a secure PHP backend, a dynamic JavaScript frontend, and fully automated donation systems supporting PayPal and PayMongo (GCash, Maya, QR Ph, and Cards).

✨ Features
💳 Automated Donations:

PayMongo Integration: Fully automated GCash, Maya, and Credit Card payments via secure Webhooks.

PayPal Integration: Automated PayPal checkout and credit delivery.

Manual QR Ph: Allows players to upload screenshot proofs for manual Admin approval.

🛒 Advanced Webshop:

Dynamic pricing based on item configurations.

Supports Item Level, Luck, Skill, Excellent Options, 380 Options, Harmony, Sockets, and Ancient options.

Direct-to-warehouse or direct-to-character delivery system (depending on your server's database structure).

⚙️ Character Management:

Reset Character & Reset Stats

Clear PK Status

Reset Master Level

Unstuck Character

💱 Economy & Tools:

Convert WebCredits to in-game currencies (WCoinC, WCoinP, GoblinPoints).

Live sidebar statistics (Top Rankings, Class Distribution, Server Jewel Economy).

Secure Change Password functionality.

🛡️ Admin Control Panel:

Toggle modules on/off instantly without touching code.

Manage multi-server setups (e.g., Mid Rate vs. Hard Rate).

Configure API keys and currency conversion rates via a secure UI.

📋 Prerequisites
Before installing, ensure your web server meets the following requirements:

OS: Windows Server (IIS) or Windows/Linux with XAMPP/WAMP.

PHP: PHP 7.4 or PHP 8.x

Extensions: * sqlsrv and pdo_sqlsrv (Required to connect to MS SQL Server).

curl (Required for PayPal and PayMongo APIs).

Database: Microsoft SQL Server (Standard for Mu Online).

🚀 Installation Guide
Step 1: Download & Extract
Download or clone this repository.

Extract the files into your web server's public directory (e.g., C:\xampp\htdocs\ or C:\inetpub\wwwroot\).

Step 2: Database Preparation
The donation system requires a table to store the players' WebCredits. Run this query in your SQL Server Management Studio (SSMS) inside your MuOnline database:

SQL
CREATE TABLE WebCredits (
    memb___id varchar(10) NOT NULL,
    credits int NOT NULL DEFAULT 0,
    PRIMARY KEY (memb___id)
);
(Note: If your server uses a different table or column for web credits, you will need to update the SQL queries inside Configuration/paymongo-webhook.php and Configuration/paypal-capture.php).

Step 3: Configure the Database Connection
Open the config.php (or equivalent database connection file in your project).

Set your SQL Server credentials (Host, Database Name, Username, and Password).

If your project uses encrypted passwords in settings.json, ensure your ENCRYPTION_KEY and ENCRYPTION_CIPHER in the config file match your setup.

Step 4: Admin CP Setup
Navigate to the Admin Control Panel in your browser (e.g., http://your-server-ip/AdminCP/).

Log in using your Admin credentials.

Configure your server settings, API keys (PayPal/PayMongo), and Webshop pricing.

Save the configuration. This will generate/update the Configuration/settings.json file.

🔗 PayMongo Webhook Setup (Crucial for Auto-Donations)
For PayMongo to automatically deliver credits when a player pays via GCash or Card, you must register your webhook URL in your PayMongo Dashboard.

Ensure your website is hosted on a live, public IP/Domain (Webhooks do not work on localhost without a tunnel like Ngrok).

Log in to your PayMongo Dashboard.

Navigate to Developers -> Webhooks.

Click Create Webhook.

Set the Endpoint URL to point to your webhook file:
https://your-website.com/Configuration/paymongo-webhook.php

Under Events, check the box for link.payment.paid.

Click Create.

(Important: PayMongo separates Live Mode and Test Mode. If you are testing, make sure your dashboard is in "Test Mode" when you create the webhook!)

⚠️ Security Recommendations
Protect settings.json: Your settings.json file stores your encrypted database passwords and API keys. Ensure your web server (IIS/Apache) blocks direct URL access to .json files in the Configuration/ folder.

Change Default Admin Passwords: Immediately change any default administrative passwords provided in the base script.

SSL/HTTPS: You must use an SSL Certificate (HTTPS). Modern APIs like PayMongo and PayPal require secure connections.

📝 License & Credits
Developed by [Your GitHub Username / Name]

Built for the Mu Online private server community.
