<?php
session_start();
require_once '../includes/db.php';

// Philippine time for everything on this page (PHP and MySQL session).
date_default_timezone_set('Asia/Manila');
$conn->query("SET time_zone = '+08:00'");

$page_title='Driver Messages';
$active_nav='messages';

/** "First Last" for a driver row, or '' if both are empty. */
function driver_full_name(array $d): string {
    return trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? ''));
}

// ---------------------------------------------------------------------
// All verified (admin-approved) drivers. These are the ones the admin can
// start a brand-new chat with, even if they never messaged before.
// ---------------------------------------------------------------------
$drivers = [];
$driversRes = $conn->query("SELECT id, first_name, last_name, barangay, email, phone
                            FROM driver
                            WHERE is_verified=1
                            ORDER BY first_name, last_name");
if ($driversRes) $drivers = $driversRes->fetch_all(MYSQLI_ASSOC);

$driverById = [];
foreach ($drivers as $d) $driverById[(int)$d['id']] = $d;

// Get list of drivers who have messaged.
// The display name comes from the driver's account (driver table); the name stored
// on the message rows is only a fallback, because those rows can hold inconsistent names.
$threads=$conn->query("SELECT dm.driver_id,
    COALESCE(NULLIF(TRIM(CONCAT(MAX(d.first_name),' ',MAX(d.last_name))),''), MAX(dm.driver_name)) as driver_name,
    MAX(d.phone) as phone, MAX(d.email) as email,
    (SELECT message FROM driver_message WHERE driver_id=dm.driver_id ORDER BY created_at DESC LIMIT 1) as last_msg,
    (SELECT created_at FROM driver_message WHERE driver_id=dm.driver_id ORDER BY created_at DESC LIMIT 1) as last_time,
    (SELECT COUNT(*) FROM driver_message WHERE driver_id=dm.driver_id AND sender='driver' AND is_read=0) as unread
    FROM driver_message dm LEFT JOIN driver d ON d.id=dm.driver_id GROUP BY dm.driver_id ORDER BY last_time DESC")->fetch_all(MYSQLI_ASSOC);

$threadDriverIds = array_map(fn($t) => (int)$t['driver_id'], $threads);

$selected_driver=intval($_GET['driver']??0);
$selectedDriverRow = $driverById[$selected_driver] ?? null; // null if not a verified driver
$conversation=[];
$selected_name='';
if ($selected_driver) {
    $stmt=$conn->prepare("SELECT * FROM driver_message WHERE driver_id=? ORDER BY created_at ASC LIMIT 100");
    $stmt->bind_param("i",$selected_driver);
    $stmt->execute();
    $res=$stmt->get_result();
    if ($res) $conversation=$res->fetch_all(MYSQLI_ASSOC);

    // Name shown in the chat header: same source as the thread list.
    foreach ($threads as $th) {
        if ((int)$th['driver_id'] === $selected_driver) { $selected_name = $th['driver_name']; break; }
    }
    if ($selected_name === '' && $selectedDriverRow) $selected_name = driver_full_name($selectedDriverRow);
    if ($selected_name === '' && !empty($conversation)) $selected_name=$conversation[0]['driver_name'];

    // Mark the driver's messages as read (and remember WHEN they were read)
    $upd=$conn->prepare("UPDATE driver_message SET is_read=1, read_at=NOW() WHERE driver_id=? AND sender='driver' AND is_read=0");
    $upd->bind_param("i",$selected_driver);
    $upd->execute();
}

// The chat opens if there is history, OR if this is a verified driver the
// admin just picked from "New Chat" (no messages yet).
$canChat = $selected_driver && (!empty($conversation) || $selectedDriverRow);

// A driver picked from "New Chat" who has no thread yet: show them at the top
// of the thread list so the list matches the open chat.
$pendingThread = null;
if ($selectedDriverRow && !in_array($selected_driver, $threadDriverIds, true)) {
    $pendingThread = [
        'driver_id'   => $selected_driver,
        'driver_name' => $selected_name !== '' ? $selected_name : 'Driver',
        'last_msg'    => 'New conversation',
        'unread'      => 0,
    ];
}

include '../includes/admin_header.php';
?>
<style>
/* ---- Fit this page to the screen: no page scrolling, only the message list scrolls ---- */
body{height:100vh;height:100dvh;min-height:0;overflow:hidden}
.layout{min-height:0}
.main-content{min-height:0}
.page-body{display:flex;flex-direction:column;overflow:hidden;padding:16px 28px}

.msg-layout{display:grid;grid-template-columns:300px minmax(0,1fr);grid-template-rows:minmax(0,1fr);gap:0;background:white;border-radius:12px;border:1.5px solid #e0e8f4;overflow:hidden;flex:1;min-height:0;box-shadow:0 4px 20px rgba(0,0,0,0.08)}
.thread-list{border-right:1px solid #e0e8f4;overflow-y:auto;display:flex;flex-direction:column;min-height:0}
.tl-header{padding:14px 16px;background:#f8fafc;border-bottom:1px solid #e0e8f4;font-size:14px;font-weight:900;color:#0d1b2a;flex-shrink:0;display:flex;align-items:center;justify-content:space-between;gap:8px}
.new-chat-btn{background:#1a73e8;color:white;border:none;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:800;font-family:inherit;cursor:pointer;transition:background 0.15s;white-space:nowrap}
.new-chat-btn:hover{background:#0d47a1}
.thread-item{padding:14px 16px;border-bottom:1px solid #f0f4f8;cursor:pointer;transition:background 0.15s;display:flex;gap:10px;align-items:flex-start;text-decoration:none}
.thread-item:hover{background:#f8fafc}
.thread-item.active{background:#e8f0fe}
.ti-avatar{width:40px;height:40px;background:#e8f0fe;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.ti-body{flex:1;min-width:0}
.ti-name{font-size:13px;font-weight:800;color:#0d1b2a;margin-bottom:2px}
.ti-preview{font-size:11px;color:#6b7a99;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:600}
.ti-time{font-size:10px;color:#9baabb;font-weight:600;flex-shrink:0}
.unread-dot{width:8px;height:8px;background:#1a73e8;border-radius:50%;flex-shrink:0;margin-top:4px}

.chat-area{display:flex;flex-direction:column;overflow:hidden;min-height:0;min-width:0}
.chat-header{padding:14px 18px;border-bottom:1px solid #e0e8f4;display:flex;align-items:center;gap:12px;background:#f8fafc;flex-shrink:0}
.chat-back{display:none;width:36px;height:36px;border-radius:50%;align-items:center;justify-content:center;color:#1a1a2e;text-decoration:none;flex-shrink:0;margin-left:-6px}
.chat-back:hover{background:#e8f0fe}
.ch-avatar{width:38px;height:38px;background:#e8f0fe;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.ch-name{font-size:15px;font-weight:800;color:#0d1b2a}
.ch-status{font-size:11px;color:#00897b;font-weight:700}
.chat-messages{flex:1;min-height:0;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:8px;background:#f4f7fc}
.empty-note{margin:auto;text-align:center;color:#9baabb;font-size:13px;font-weight:700;line-height:1.6}
.bubble-wrap{display:flex;flex-direction:column;max-width:70%}
.bw-me{align-self:flex-end;align-items:flex-end}
.bw-driver{align-self:flex-start;align-items:flex-start}
.bubble{padding:10px 14px;border-radius:16px;font-size:13px;font-weight:600;line-height:1.5;word-break:break-word}
.bubble-me{background:#1a73e8;color:white;border-bottom-right-radius:4px}
.bubble-driver{background:white;color:#0d1b2a;border-bottom-left-radius:4px;border:1.5px solid #e0e8f4}
.btime{font-size:10px;color:#9baabb;margin-top:2px;font-weight:600}
.tick{margin-left:4px;font-weight:800;color:#9baabb}
.tick.seen{color:#1a73e8}
.chat-input-bar{padding:12px 16px;border-top:1px solid #e0e8f4;background:white;display:flex;gap:10px;align-items:flex-end;flex-shrink:0}
.chat-textarea{flex:1;min-width:0;border:1.5px solid #e0e8f4;border-radius:20px;padding:10px 14px;font-size:14px;font-family:inherit;font-weight:600;color:#0d1b2a;outline:none;resize:none;max-height:80px;line-height:1.4}
.chat-textarea:focus{border-color:#1a73e8}
.send-btn{width:40px;height:40px;border-radius:50%;background:#1a73e8;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background 0.2s;flex-shrink:0}
.send-btn:hover{background:#0d47a1}
.send-btn:disabled{background:#9baabb;cursor:default}
.no-chat{display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;color:#9baabb;text-align:center;gap:10px}
.no-chat .ni{font-size:56px;opacity:0.4}
.no-chat p{font-size:14px;font-weight:700}

/* ---- New Chat modal: driver picker ---- */
.dl-list{margin-top:12px;max-height:50vh;overflow-y:auto;border:1px solid #e0e8f4;border-radius:10px}
.dl-item{display:flex;align-items:center;gap:12px;padding:11px 14px;border-bottom:1px solid #f0f4f8;text-decoration:none;color:inherit;transition:background 0.15s}
.dl-item:last-child{border-bottom:none}
.dl-item:hover{background:#f4f8ff}
.dl-avatar{width:38px;height:38px;background:#e8f0fe;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:19px;flex-shrink:0}
.dl-body{flex:1;min-width:0}
.dl-name{font-size:14px;font-weight:800;color:#0d1b2a}
.dl-sub{font-size:12px;color:#6b7a99;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dl-tag{font-size:10px;font-weight:800;color:#1a73e8;background:#e8f0fe;border-radius:10px;padding:3px 8px;flex-shrink:0}
.dl-empty{padding:22px;text-align:center;color:#9baabb;font-size:13px;font-weight:700}

/* Narrow windows and phones: one pane at a time.
   No chat selected  -> show the conversation list.
   Chat selected     -> show the chat, with a back arrow to the list. */
@media(max-width:768px){
  .page-body{padding:8px}
  .msg-layout{grid-template-columns:1fr;border-radius:10px}
  .thread-list{border-right:none}
  .msg-layout.has-chat .thread-list{display:none}
  .msg-layout:not(.has-chat) .chat-area{display:none}
  .chat-back{display:flex}
  .chat-header{padding:10px 12px}
  .chat-messages{padding:12px}
  .bubble-wrap{max-width:85%}
  .chat-input-bar{padding:10px 12px}
  /* 16px stops iPhones from zooming in when the box is tapped */
  .chat-textarea{font-size:16px}
  .thread-item{padding:14px}
}
</style>

<div class="msg-layout <?= $canChat ? 'has-chat' : '' ?>">
  <!-- THREAD LIST -->
  <div class="thread-list">
    <div class="tl-header">
      <span>💬 Driver Conversations</span>
      <button type="button" class="new-chat-btn" onclick="openNewChat()">+ New Chat</button>
    </div>

    <?php if ($pendingThread): ?>
    <a href="messages.php?driver=<?= (int)$pendingThread['driver_id'] ?>" class="thread-item active">
      <div class="ti-avatar">🚛</div>
      <div class="ti-body">
        <div class="ti-name"><?= htmlspecialchars($pendingThread['driver_name']) ?></div>
        <div class="ti-preview"><?= htmlspecialchars($pendingThread['last_msg']) ?></div>
      </div>
    </a>
    <?php endif; ?>

    <?php if (empty($threads) && !$pendingThread): ?>
    <div style="padding:24px;text-align:center;color:#9baabb;font-size:13px;font-weight:700">No messages yet.<br>Click “+ New Chat” to message a driver.</div>
    <?php else: ?>
    <?php foreach ($threads as $t): $isActive=$t['driver_id']==$selected_driver; ?>
    <a href="messages.php?driver=<?= (int)$t['driver_id'] ?>" class="thread-item <?= $isActive?'active':'' ?>">
      <div class="ti-avatar">🚛</div>
      <div class="ti-body">
        <div class="ti-name"><?= htmlspecialchars($t['driver_name'] ?? 'Driver') ?></div>
        <div class="ti-preview"><?= htmlspecialchars(mb_substr($t['last_msg']??'',0,40)) ?></div>
      </div>
      <?php if ($t['unread']>0): ?><div class="unread-dot"></div><?php endif; ?>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- CHAT AREA -->
  <div class="chat-area">
    <?php if ($canChat): ?>
    <div class="chat-header">
      <a href="messages.php" class="chat-back" aria-label="Back to conversations">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      </a>
      <div class="ch-avatar">🚛</div>
      <div><div class="ch-name"><?= htmlspecialchars($selected_name !== '' ? $selected_name : 'Driver') ?></div><div class="ch-status">Driver</div></div>
    </div>
    <div class="chat-messages" id="cm" data-last-id="<?= !empty($conversation) ? (int)end($conversation)['id'] : 0 ?>">
      <?php if (empty($conversation)): ?>
      <div class="empty-note" id="emptyNote">No messages yet.<br>Type below to start the conversation.</div>
      <?php endif; ?>
      <?php foreach ($conversation as $m):
        $isAdmin=$m['sender']==='admin';
        $timeStr=(new DateTime($m['created_at']))->format('g:i A');
        $isSeen=((int)($m['is_read'] ?? 0)===1);
        $seenStr=(!empty($m['read_at'])) ? (new DateTime($m['read_at']))->format('g:i A') : '';
      ?>
      <div class="bubble-wrap <?= $isAdmin?'bw-me':'bw-driver' ?>">
        <div class="bubble <?= $isAdmin?'bubble-me':'bubble-driver' ?>"><?= htmlspecialchars($m['message']) ?></div>
        <div class="btime" data-id="<?= (int)$m['id'] ?>"><?= $isAdmin?'You':'Driver' ?> • <?= $timeStr ?><?php if ($isAdmin): ?><span class="tick <?= $isSeen?'seen':'' ?>"><?= $isSeen ? '✓✓ Seen'.($seenStr?' '.$seenStr:'') : '✓' ?></span><?php endif; ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="chat-input-bar">
      <form id="replyForm" style="display:contents">
        <input type="hidden" name="driver_id" id="driverIdInput" value="<?= $selected_driver ?>">
        <input type="hidden" name="driver_name" value="<?= htmlspecialchars($selected_name) ?>">
        <textarea class="chat-textarea" name="reply_msg" id="replyInput" placeholder="Type a message..." rows="1" maxlength="500"></textarea>
        <button type="submit" class="send-btn" id="sendBtn" aria-label="Send"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></button>
      </form>
    </div>
    <?php else: ?>
    <div class="no-chat"><div class="ni">💬</div><p>Select a driver conversation<br>or start a new chat</p></div>
    <?php endif; ?>
  </div>
</div>

<!-- NEW CHAT MODAL -->
<div class="modal-overlay" id="newChatModal">
  <div class="modal" style="max-width:460px;">
    <div class="modal-header">Start a New Chat</div>
    <div class="modal-body" style="padding:16px 20px;">
      <?php if (empty($drivers)): ?>
        <div class="dl-empty">There are no verified drivers yet.<br>Approve a driver in “Driver Approvals” first.</div>
      <?php else: ?>
        <input type="text" id="driverSearch" class="form-control" placeholder="Search by name, barangay, email or phone..." autocomplete="off">
        <div class="dl-list" id="driverList">
          <?php foreach ($drivers as $d):
            $nm = driver_full_name($d);
            if ($nm === '') $nm = 'Driver #' . (int)$d['id'];
            $sub = array_filter([$d['barangay'] ?? '', $d['phone'] ?? '', $d['email'] ?? '']);
            $searchText = mb_strtolower($nm . ' ' . implode(' ', $sub));
            $hasThread = in_array((int)$d['id'], $threadDriverIds, true);
          ?>
          <a class="dl-item" href="messages.php?driver=<?= (int)$d['id'] ?>" data-search="<?= htmlspecialchars($searchText) ?>">
            <div class="dl-avatar">🚛</div>
            <div class="dl-body">
              <div class="dl-name"><?= htmlspecialchars($nm) ?></div>
              <div class="dl-sub"><?= htmlspecialchars(implode(' · ', $sub)) ?></div>
            </div>
            <?php if ($hasThread): ?><span class="dl-tag">Open chat</span><?php endif; ?>
          </a>
          <?php endforeach; ?>
          <div class="dl-empty" id="driverNoMatch" style="display:none">No drivers match your search.</div>
        </div>
      <?php endif; ?>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn" onclick="closeNewChat()">Close</button>
    </div>
  </div>
</div>

<script>
const cm = document.getElementById('cm');
const driverId = <?= (int)$selected_driver ?>;

// Always open the chat at the newest message.
function scrollToBottom() { if (cm) cm.scrollTop = cm.scrollHeight; }
scrollToBottom();
window.addEventListener('load', scrollToBottom);

const ri = document.getElementById('replyInput');
const form = document.getElementById('replyForm');
const sendBtn = document.getElementById('sendBtn');

// Removes the "No messages yet" hint once the first message appears.
function removeEmptyNote() {
    const note = document.getElementById('emptyNote');
    if (note) note.remove();
}

function escapeAndBuildBubble(msg, sender, time, id) {
    const wrap = document.createElement('div');
    wrap.className = 'bubble-wrap ' + (sender === 'admin' ? 'bw-me' : 'bw-driver');

    const bubble = document.createElement('div');
    bubble.className = 'bubble ' + (sender === 'admin' ? 'bubble-me' : 'bubble-driver');
    bubble.textContent = msg; // textContent avoids HTML injection

    const btime = document.createElement('div');
    btime.className = 'btime';
    btime.dataset.id = id;
    btime.appendChild(document.createTextNode((sender === 'admin' ? 'You' : 'Driver') + ' • ' + time));

    if (sender === 'admin') {
        const tick = document.createElement('span');
        tick.className = 'tick';
        tick.textContent = '✓';
        btime.appendChild(tick);
    }

    wrap.appendChild(bubble);
    wrap.appendChild(btime);
    return wrap;
}

// Turns a sent ✓ into a blue ✓✓ Seen <time>
function markSeen(id, time) {
    const t = document.querySelector('.btime[data-id="' + id + '"] .tick');
    if (t && !t.classList.contains('seen')) {
        t.classList.add('seen');
        t.textContent = '✓✓ Seen' + (time ? ' ' + time : '');
    }
}

if (form) {
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const text = ri.value.trim();
        if (!text) return;

        sendBtn.disabled = true;
        const fd = new FormData(form);

        fetch('messages_send.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    removeEmptyNote();
                    cm.appendChild(escapeAndBuildBubble(data.message, 'admin', data.time, data.id));
                    cm.dataset.lastId = data.id;
                    scrollToBottom();
                    ri.value = '';
                    ri.style.height = 'auto';
                } else {
                    alert(data.error || 'Failed to send message.');
                }
            })
            .catch(() => alert('Network error sending message.'))
            .finally(() => { sendBtn.disabled = false; ri.focus(); });
    });

    ri.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 80) + 'px';
    });
    ri.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.requestSubmit();
        }
    });
}

// Poll every 3s: new messages + which of my messages the driver has seen
if (driverId && cm) {
    setInterval(function () {
        fetch('messages_poll.php?driver=' + driverId + '&after_id=' + cm.dataset.lastId)
            .then(r => r.json())
            .then(data => {
                const msgs = data.messages || [];
                if (msgs.length) {
                    removeEmptyNote();
                    msgs.forEach(m => {
                        cm.appendChild(escapeAndBuildBubble(m.message, m.sender, m.time, m.id));
                        cm.dataset.lastId = m.id;
                    });
                    scrollToBottom();
                }
                (data.seen || []).forEach(s => markSeen(s.id, s.time));
            })
            .catch(() => { /* silent fail, retry next interval */ });
    }, 3000);
}

// ---- New Chat modal ----
const newChatModal = document.getElementById('newChatModal');
const driverSearch = document.getElementById('driverSearch');

function openNewChat() {
    newChatModal.classList.add('open');
    if (driverSearch) { driverSearch.value = ''; filterDrivers(); driverSearch.focus(); }
}
function closeNewChat() { newChatModal.classList.remove('open'); }

function filterDrivers() {
    const q = (driverSearch.value || '').trim().toLowerCase();
    let shown = 0;
    document.querySelectorAll('#driverList .dl-item').forEach(el => {
        const match = !q || el.dataset.search.includes(q);
        el.style.display = match ? '' : 'none';
        if (match) shown++;
    });
    const none = document.getElementById('driverNoMatch');
    if (none) none.style.display = shown === 0 ? 'block' : 'none';
}
if (driverSearch) driverSearch.addEventListener('input', filterDrivers);

newChatModal.addEventListener('click', e => { if (e.target === newChatModal) closeNewChat(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeNewChat(); });
</script>
<?php include '../includes/admin_footer.php'; ?>
