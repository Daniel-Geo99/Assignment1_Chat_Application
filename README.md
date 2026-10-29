# Chat Application

A simple multi-user web chat built with PHP, SQLite and vanilla JavaScript.
Users open the page in a browser, enter a name, and exchange messages that
appear on every connected screen within about two seconds.

## Features
- Send and receive messages without reloading the page (AJAX / Fetch API)
- Messages stored permanently in an SQLite database (created automatically)
- Efficient polling: only messages newer than the last one seen are fetched
- Security: prepared statements (SQL injection), textContent (XSS), server-side validation
- Responsive layout; your name is remembered between visits

## Technologies
PHP 8 (PDO + SQLite), HTML5, CSS3, JavaScript (ES6)

## How to run
1. Install PHP and enable the `pdo_sqlite` and `sqlite3` extensions in php.ini.
2. In the project folder, run: `php -S 0.0.0.0:8000`
3. Open `http://localhost:8000/chat.php`
4. For a second computer on the same network, open `http://<host-IP>:8000/chat.php`

## Files
- `chat.php`: backend (send/fetch endpoints) and chat page
- `.gitignore`: excludes the local database file `chat.sqlite`
