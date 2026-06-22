# Rafiq – Web Portal

A web portal for Rafiq, a community-driven platform connecting people with disabilities and elderly users in Egypt to verified caregivers, doctors, interpreters and accessible transport, featuring an interactive accessibility map powered by real user reviews.

> **Note:** This project is primarily designed for use in Egypt and may not be fully suitable for other regions due to localization, service availability, and infrastructure differences.

## Related Repositories

- [Rafiq Mobile Application](https://github.com/jomaanaa/rafiq-application)
- [Rafiq Backend (PHP API & Database)](https://github.com/jomaanaa/rafiq-backend)

## Getting Started

### Prerequisites

- A PHP server (e.g. XAMPP or WAMP)
- PostgreSQL installed and running

### Setup

Step 1: Clone the repository
git clone https://github.com/jomaanaa/rafiq-website.git

Step 2: Place the project folder in your PHP server's `htdocs` directory

Step 3: Import the database using the included `.backup` file via pgAdmin

Step 4: Open `pgdb/db.php` and update the database connection details to match your local setup

Step 5: Open `general/chatbot_api.php` and replace `YOUR_GROQ_API_KEY_HERE` with your own Groq API key, available for free at [console.groq.com](https://console.groq.com)

Step 6: Start your PHP server and open the project in your browser

## Contributors

Jomana Ahmed Mostafa
