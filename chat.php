<?php
// ---------- Configuration ----------
const DB_FILE  = __DIR__ . "/chat.sqlite";   // created automatically
const MAX_NAME = 30;
const MAX_MSG  = 2000;

function respond(array $data, int $code = 200): void {
    http_response_code($code);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- Database (SQLite via PDO) ----------
try {
    $db = new PDO("sqlite:" . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sender TEXT NOT NULL,
        message TEXT NOT NULL,
        reg_date DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    respond(["success" => false, "error" => "Database setup failed."], 500);
}

// ---------- Send a message ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["sender"] ?? "");
    $text = trim($_POST["message"] ?? "");

    if ($name === "" || $text === "") {
        respond(["success" => false, "error" => "Name and message are required."], 400);
    }
    if (mb_strlen($name) > MAX_NAME || mb_strlen($text) > MAX_MSG) {
        respond(["success" => false, "error" => "Input is too long."], 400);
    }

    try {
        $stmt = $db->prepare("INSERT INTO chat_messages (sender, message) VALUES (?, ?)");
        $stmt->execute([$name, $text]);
        respond(["success" => true]);
    } catch (PDOException $e) {
        respond(["success" => false, "error" => "Message could not be saved."], 500);
    }
}

// ---------- Fetch messages newer than ?after=ID ----------
if (isset($_GET["fetch"])) {
    $after = max(0, (int)($_GET["after"] ?? 0));

    $stmt = $db->prepare(
        "SELECT id, sender, message, reg_date FROM (
            SELECT id, sender, message, reg_date FROM chat_messages
            WHERE id > ? ORDER BY id DESC LIMIT 200
         ) ORDER BY id ASC"
    );
    $stmt->execute([$after]);
    respond($stmt->fetchAll(PDO::FETCH_ASSOC));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Group Chat</title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; padding: 20px; font-family: Arial, sans-serif; background: #f0fdf4; }
    .container { max-width: 520px; margin: 30px auto; padding: 20px;
                 background: #fff; border-radius: 14px; box-shadow: 0 4px 15px #00000018; }
    h2 { text-align: center; color: #15803d; margin: 0 0 4px; }
    .subtitle { text-align: center; color: #777; margin: 0 0 18px; }
    input, button { padding: 12px; font-size: 15px; border-radius: 8px; }
    input { width: 100%; border: 1px solid #ddd; outline: none; }
    input:focus { border-color: #15803d; }
    #sender { margin-bottom: 12px; }
    #chatbox { height: 350px; overflow-y: auto; padding: 12px; margin-bottom: 12px;
               background: #f8fafc; border: 1px solid #eee; border-radius: 8px; }
    .msg { padding: 10px; margin-bottom: 10px; background: #dcfce7;
           border-radius: 8px; overflow-wrap: anywhere; }
    .msg.mine { background: #bbf7d0; margin-left: 30px; }
    .msg strong { color: #166534; }
    .msg p { margin: 6px 0; white-space: pre-wrap; }
    .msg small { color: #666; font-size: 11px; }
    .row { display: flex; gap: 8px; }
    .row input { flex: 1; min-width: 0; }
    button { border: none; color: #fff; background: #15803d; cursor: pointer; }
    button:hover { background: #166534; }
    button:disabled { opacity: .6; cursor: not-allowed; }
    #status { color: #666; font-size: 12px; text-align: center; margin-top: 12px; }
    @media (max-width: 400px) {
        body { padding: 10px; }
        .row { flex-wrap: wrap; }
        .row button { width: 100%; }
        #chatbox { height: 55vh; }
    }
</style>
</head>
<body>
<div class="container">
    <h2>💬 Group Chat</h2>
    <p class="subtitle">Talk with your friends in real time</p>

    <input type="text" id="sender" placeholder="Your name" maxlength="30" autocomplete="nickname" required>
    <div id="chatbox"><em>Loading messages...</em></div>

    <form id="chat-form" class="row">
        <input type="text" id="message" placeholder="Type a message..." maxlength="2000" autocomplete="off" required>
        <button type="submit" id="send-button">Send</button>
    </form>
    <div id="status">Connecting...</div>
</div>

<script>
const $ = (id) => document.getElementById(id);
const chatbox = $("chatbox"), nameInput = $("sender"), msgInput = $("message"),
      form = $("chat-form"), statusEl = $("status"), sendBtn = $("send-button");

let lastId = 0;
let firstLoad = true;

// Remember the user's name between visits
nameInput.value = localStorage.getItem("chatName") || "";
nameInput.addEventListener("input", () =>
    localStorage.setItem("chatName", nameInput.value.trim()));

function renderMessage(m) {
    const box = document.createElement("div");
    box.className = "msg" + (m.sender === nameInput.value.trim() ? " mine" : "");

    const who = document.createElement("strong");
    who.textContent = m.sender;
    const body = document.createElement("p");
    body.textContent = m.message;          // textContent prevents XSS
    const when = document.createElement("small");
    when.textContent = m.reg_date;

    box.append(who, body, when);
    return box;
}

async function loadMessages() {
    try {
        const res = await fetch(`chat.php?fetch=1&after=${lastId}`, { cache: "no-store" });
        if (!res.ok) throw new Error("fetch failed");
        const rows = await res.json();

        if (firstLoad) { chatbox.textContent = ""; firstLoad = false; }

        if (rows.length) {
            const nearBottom = chatbox.scrollHeight - chatbox.scrollTop - chatbox.clientHeight < 60;
            rows.forEach((m) => chatbox.appendChild(renderMessage(m)));
            lastId = Number(rows[rows.length - 1].id);
            if (nearBottom) chatbox.scrollTop = chatbox.scrollHeight;
        }
        statusEl.textContent = "Connected";
    } catch (e) {
        statusEl.textContent = "Connection lost. Retrying...";
    }
}

form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const name = nameInput.value.trim(), text = msgInput.value.trim();
    if (!name || !text) { alert("Please enter your name and a message."); return; }

    sendBtn.disabled = true;
    try {
        const res = await fetch("chat.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({ sender: name, message: text })
        });
        const result = await res.json();
        if (!res.ok || !result.success) throw new Error(result.error || "Send failed.");

        msgInput.value = "";
        await loadMessages();
        chatbox.scrollTop = chatbox.scrollHeight;
        msgInput.focus();
    } catch (err) {
        alert(err.message);
    } finally {
        sendBtn.disabled = false;
    }
});

loadMessages();
setInterval(loadMessages, 2000);
</script>
</body>
</html>