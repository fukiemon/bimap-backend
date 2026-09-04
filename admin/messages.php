<?php
session_start();
require_once '../includes/db.php';
$page_title='Driver Messages';
$active_nav='messages';

// Handle admin reply
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reply_msg'])) {
    $driver_id=intval($_POST['driver_id']);
    $driver_name=trim($_POST['driver_name']??'Driver');
    $msg=trim($_POST['reply_msg']);
    if ($msg && $driver_id) {
        $ins=$conn->prepare("INSERT INTO driver_message (driver_id,driver_name,message,sender) VALUES (?,?,?,'admin')");
        $ins->bind_param("iss",$driver_id,$driver_name,$msg);
        $ins->execute();
    }
    header("Location: messages.php?driver=$driver_id"); exit;
}

// Get list of drivers who have messaged
$threads=$conn->query("SELECT dm.driver_id, MAX(dm.driver_name) as driver_name, MAX(d.phone) as phone, MAX(d.email) as email,
    (SELECT message FROM driver_message WHERE driver_id=dm.driver_id ORDER BY created_at DESC LIMIT 1) as last_msg,
    (SELECT created_at FROM driver_message WHERE driver_id=dm.driver_id ORDER BY created_at DESC LIMIT 1) as last_time,
    (SELECT COUNT(*) FROM driver_message WHERE driver_id=dm.driver_id AND sender='driver' AND is_read=0) as unread
    FROM driver_message dm LEFT JOIN driver d ON d.id=dm.driver_id GROUP BY dm.driver_id ORDER BY last_time DESC")->fetch_all(MYSQLI_ASSOC);

$selected_driver=intval($_GET['driver']??0);
$conversation=[];
$selected_name='';
if ($selected_driver) {
    $res=$conn->query("SELECT * FROM driver_message WHERE driver_id=$selected_driver ORDER BY created_at ASC LIMIT 100");
    if ($res) $conversation=$res->fetch_all(MYSQLI_ASSOC);
    if (!empty($conversation)) $selected_name=$conversation[0]['driver_name'];
    // Mark as read
    $conn->query("UPDATE driver_message SET is_read=1 WHERE driver_id=$selected_driver AND sender='driver'");
}

include '../includes/admin_header.php';
?>
<style>
.msg-layout{display:grid;grid-template-columns:300px 1fr;gap:0;background:white;border-radius:12px;border:1.5px solid #e0e8f4;overflow:hidden;height:580px;box-shadow:0 4px 20px rgba(0,0,0,0.08)}
.thread-list{border-right:1px solid #e0e8f4;overflow-y:auto;display:flex;flex-direction:column}
.tl-header{padding:14px 16px;background:#f8fafc;border-bottom:1px solid #e0e8f4;font-size:14px;font-weight:900;color:#0d1b2a;flex-shrink:0}
.thread-item{padding:14px 16px;border-bottom:1px solid #f0f4f8;cursor:pointer;transition:background 0.15s;display:flex;gap:10px;align-items:flex-start;text-decoration:none}
.thread-item:hover{background:#f8fafc}
.thread-item.active{background:#e8f0fe}
.ti-avatar{width:40px;height:40px;background:#e8f0fe;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.ti-body{flex:1;min-width:0}
.ti-name{font-size:13px;font-weight:800;color:#0d1b2a;margin-bottom:2px}
.ti-preview{font-size:11px;color:#6b7a99;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:600}
.ti-time{font-size:10px;color:#9baabb;font-weight:600;flex-shrink:0}
.unread-dot{width:8px;height:8px;background:#1a73e8;border-radius:50%;flex-shrink:0;margin-top:4px}

.chat-area{display:flex;flex-direction:column;overflow:hidden}
.chat-header{padding:14px 18px;border-bottom:1px solid #e0e8f4;display:flex;align-items:center;gap:12px;background:#f8fafc;flex-shrink:0}
.ch-avatar{width:38px;height:38px;background:#e8f0fe;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.ch-name{font-size:15px;font-weight:800;color:#0d1b2a}
.ch-status{font-size:11px;color:#00897b;font-weight:700}
.chat-messages{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:8px;background:#f4f7fc}
.bubble-wrap{display:flex;flex-direction:column;max-width:70%}
.bw-me{align-self:flex-end;align-items:flex-end}
.bw-driver{align-self:flex-start;align-items:flex-start}
.bubble{padding:10px 14px;border-radius:16px;font-size:13px;font-weight:600;line-height:1.5}
.bubble-me{background:#1a73e8;color:white;border-bottom-right-radius:4px}
.bubble-driver{background:white;color:#0d1b2a;border-bottom-left-radius:4px;border:1.5px solid #e0e8f4}
.btime{font-size:10px;color:#9baabb;margin-top:2px;font-weight:600}
.chat-input-bar{padding:12px 16px;border-top:1px solid #e0e8f4;background:white;display:flex;gap:10px;align-items:flex-end;flex-shrink:0}
.chat-textarea{flex:1;border:1.5px solid #e0e8f4;border-radius:20px;padding:10px 14px;font-size:14px;font-family:inherit;font-weight:600;color:#0d1b2a;outline:none;resize:none;max-height:80px;line-height:1.4}
.chat-textarea:focus{border-color:#1a73e8}
.send-btn{width:40px;height:40px;border-radius:50%;background:#1a73e8;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background 0.2s;flex-shrink:0}
.send-btn:hover{background:#0d47a1}
.no-chat{display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;color:#9baabb;text-align:center;gap:10px}
.no-chat .ni{font-size:56px;opacity:0.4}
.no-chat p{font-size:14px;font-weight:700}
@media(max-width:768px){.msg-layout{grid-template-columns:1fr;height:auto}.thread-list{border-right:none;border-bottom:1px solid #e0e8f4;max-height:250px}}
</style>

<div class="msg-layout">
  <!-- THREAD LIST -->
  <div class="thread-list">
    <div class="tl-header">💬 Driver Conversations</div>
    <?php if (empty($threads)): ?>
    <div style="padding:24px;text-align:center;color:#9baabb;font-size:13px;font-weight:700">No messages yet</div>
    <?php else: ?>
    <?php foreach ($threads as $t): $isActive=$t['driver_id']==$selected_driver; ?>
    <a href="messages.php?driver=<?= $t['driver_id'] ?>" class="thread-item <?= $isActive?'active':'' ?>">
      <div class="ti-avatar">🚛</div>
      <div class="ti-body">
        <div class="ti-name"><?= htmlspecialchars($t['driver_name']) ?></div>
        <div class="ti-preview"><?= htmlspecialchars(substr($t['last_msg']??'',0,40)) ?></div>
      </div>
      <?php if ($t['unread']>0): ?><div class="unread-dot"></div><?php endif; ?>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- CHAT AREA -->
  <div class="chat-area">
    <?php if ($selected_driver && !empty($conversation)): ?>
    <div class="chat-header">
      <div class="ch-avatar">🚛</div>
      <div><div class="ch-name"><?= htmlspecialchars($selected_name) ?></div><div class="ch-status">Driver</div></div>
    </div>
    <div class="chat-messages" id="cm">
      <?php foreach ($conversation as $m):
        $isAdmin=$m['sender']==='admin';
        $dt=new DateTime($m['created_at']); $t=$dt->format('g:i A');
      ?>
      <div class="bubble-wrap <?= $isAdmin?'bw-me':'bw-driver' ?>">
        <div class="bubble <?= $isAdmin?'bubble-me':'bubble-driver' ?>"><?= htmlspecialchars($m['message']) ?></div>
        <div class="btime"><?= $isAdmin?'You':'Driver' ?> • <?= $t ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="chat-input-bar">
      <form method="POST" style="display:contents" id="replyForm">
        <input type="hidden" name="driver_id" value="<?= $selected_driver ?>">
        <input type="hidden" name="driver_name" value="<?= htmlspecialchars($selected_name) ?>">
        <textarea class="chat-textarea" name="reply_msg" id="replyInput" placeholder="Type a reply..." rows="1" maxlength="500"></textarea>
        <button type="submit" class="send-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></button>
      </form>
    </div>
    <?php else: ?>
    <div class="no-chat"><div class="ni">💬</div><p>Select a driver conversation<br>to view messages</p></div>
    <?php endif; ?>
  </div>
</div>

<script>
const cm=document.getElementById('cm');
if(cm)cm.scrollTop=cm.scrollHeight;
const ri=document.getElementById('replyInput');
if(ri){ri.addEventListener('input',function(){this.style.height='auto';this.style.height=Math.min(this.scrollHeight,80)+'px'});ri.addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();document.getElementById('replyForm').submit()}})}
</script>
<?php include '../includes/admin_footer.php'; ?>
