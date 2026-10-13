<?php
/*  AI ASSISTANT  ─  dedicated full-page chat
    -------------------------------------------------------------------------
    Sessions-based assistant. Each active session remembers the whole
    conversation. Upload any file (doc, pdf, txt, csv, images) for the AI to
    read and act on. Voice input (Web Speech API) + spoken replies. Opening a
    client/module is done by asking the AI. Ending a session is confirmed in
    voice before it is saved.
    ------------------------------------------------------------------------- */
session_start();
include "includes/db.php";
include "includes/auth.php";
require_once "includes/roles.php";
include "includes/ai_helper.php";

if (!ai_can_use()) {
    header("Location: index.php");
    exit;
}
$aiEnabled = ai_get('ai_enabled', '1') === '1';
$uid = (int) ($_SESSION['user_id'] ?? 0);
/* Ensure an active session exists up-front so the page can render history. */
$active = ai_session_active($uid);
if (!$active) {
    $sid = ai_session_create($uid, 'New Session');
    ai_session_title($uid, $sid, 'Session ' . date('M j, g:i A'));
    $active = ai_session_get($uid, $sid);
}
$activeSid = (int) $active['session_id'];
$hist = ai_chat_history($uid, $activeSid, 200);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Syed Professional Services - SPS Assistant</title>
    <?php include "includes/links.php"; ?>
    <style>
        .ai-page-wrap { display: flex; gap: 18px; height: calc(100vh - 170px); min-height: 480px; margin-top: 6px; }
        .ai-sess-panel { width: 260px; flex-shrink: 0; background: #fff; border: 1px solid #e8ecf1; border-radius: 14px; display: flex; flex-direction: column; overflow: hidden; }
        .ai-sess-panel h6 { margin: 0; padding: 14px; border-bottom: 1px solid #eef2f7; font-size: 14px; }
        .ai-sess-new { margin: 12px; }
        .ai-sess-list { flex: 1; overflow-y: auto; }
        .ai-sess-item { display: block; width: 100%; text-align: left; background: none; border: none; border-bottom: 1px solid #f1f5f9; padding: 10px 14px; cursor: pointer; }
        .ai-sess-item:hover { background: #f8fafc; }
        .ai-sess-item.active { background: #eef4ff; border-left: 3px solid #1456b8; }
        .ai-sess-item b { font-size: 12.5px; color: #1e293b; display: block; }
        .ai-sess-item small { color: #94a3b8; font-size: 11px; }
        .ai-chat-main { flex: 1; background: #fff; border: 1px solid #e8ecf1; border-radius: 14px; display: flex; flex-direction: column; overflow: hidden; }
        .ai-chat-head { padding: 12px 16px; border-bottom: 1px solid #eef2f7; display: flex; align-items: center; gap: 10px; }
        .ai-chat-head b { font-size: 15px; }
        .ai-chat-head .badge { font-size: 10px; }
        #aiMsgs2 { flex: 1; padding: 16px; overflow-y: auto; background: #f7f9fc; }
        .ai-msg { margin-bottom: 12px; display: flex; }
        .ai-msg.bot { justify-content: flex-start; }
        .ai-msg.user { justify-content: flex-end; }
        .ai-bubble { max-width: 82%; padding: 10px 13px; border-radius: 12px; font-size: 13.5px; white-space: pre-wrap; word-break: break-word; }
        .ai-msg.bot .ai-bubble { background: #fff; border: 1px solid #e8ecf1; color: #1e293b; }
        .ai-msg.user .ai-bubble { background: #1456b8; color: #fff; }
        .ai-table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 11px; background: #fff; }
        .ai-table th, .ai-table td { border: 1px solid #dde3ec; padding: 5px 6px; text-align: left; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ai-table th { background: #eef2f7; }
        .ai-tools, .ai-confirmwrap { display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap; }
        .ai-tools button, .ai-confirmwrap button { border: none; border-radius: 8px; padding: 6px 10px; font-size: 12px; cursor: pointer; color: #fff; }
        .ai-csv { background: #16a34a; } .ai-pdf { background: #dc2626; } .ai-confirm { background: #1456b8; } .ai-cancel { background: #64748b !important; }
        .ai-preview { background: #fff; border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px; margin-top: 8px; }
        .ai-preview h6 { margin: 0 0 8px; color: #1456b8; }
        .ai-preview table { width: 100%; font-size: 12px; border-collapse: collapse; }
        .ai-preview td { padding: 4px 6px; border-bottom: 1px solid #eef2f7; }
        .ai-chat-foot { padding: 12px; border-top: 1px solid #e8ecf1; background: #fff; }
        #aiRow2 { display: flex; align-items: flex-end; gap: 8px; }
        #aiIn2 { flex: 1; border: 1px solid #cbd5e1; border-radius: 10px; padding: 10px 12px; font-size: 14px; resize: none; max-height: 120px; }
        .ai-icobtn { background: none; border: none; font-size: 24px; cursor: pointer; color: #1456b8; line-height: 1; padding: 2px; }
        .ai-icobtn.rec { color: #dc2626; animation: pulse 1s infinite; }
        @keyframes pulse { 0%,100% {opacity:1} 50% {opacity:.3} }
        #aiFileInfo { font-size: 11px; color: #64748b; margin-top: 4px; }
        .ai-upload-label { display: inline-flex; align-items: center; gap: 6px; background: #f1f5f9; border: 1px dashed #94a3b8; border-radius: 10px; padding: 8px 12px; font-size: 13px; color: #475569; cursor: pointer; }
        .ai-upload-label:hover { border-color: #1456b8; color: #1456b8; }
        .ai-filethumb { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:6px 10px; font-size:12px; color:#334155; margin-top:6px; display:inline-flex; align-items:center; gap:8px; }
        .ai-speak { background:none; border:none; color:#1456b8; cursor:pointer; }
        .ai-empty { color:#94a3b8; font-size:13px; text-align:center; padding:30px 10px; }
    </style>
</head>

<body class="theme-3">
    <div class="loader-bg"><div class="loader-track"><div class="loader-fill"></div></div></div>
    <?php include "includes/sidebar.php"; ?>
    <?php include "includes/header.php"; ?>

    <div class="dash-container">
        <div class="dash-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="page-header-title"><h4 class="m-b-10"><i class="ti ti-robot"></i> SPS Assistant</h4></div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item">SPS Assistant</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!$aiEnabled) { ?>
                <div class="alert alert-warning">The SPS Assistant is <b>disabled</b>. Enable it in <a href="ai_settings.php">SPS Assistant Settings</a>.</div>
            <?php } else { ?>

            <div class="ai-page-wrap">
                <!-- Sessions panel -->
                <div class="ai-sess-panel">
                    <h6><i class="ti ti-messages"></i> Sessions</h6>
                    <div class="ai-sess-new">
                        <button class="btn btn-primary btn-sm w-100" type="button" onclick="aiNewSession()"><i class="ti ti-plus"></i> New Session</button>
                    </div>
                    <div class="ai-sess-list" id="aiSessList"></div>
                </div>

                <!-- Chat main -->
                <div class="ai-chat-main">
                    <div class="ai-chat-head">
                        <i class="ti ti-robot"></i>
                        <b id="aiChatTitle"><?= htmlspecialchars($active['title']) ?></b>
                        <span class="badge bg-success">Active</span>
                        <button class="btn btn-outline-secondary btn-sm ms-auto" type="button" onclick="aiEndSessionVoice()"><i class="ti ti-player-stop"></i> End Session</button>
                    </div>
                    <div id="aiMsgs2"></div>
                    <div class="ai-chat-foot">
                        <div id="aiFileInfo"></div>
                        <div id="aiRow2">
                            <label class="ai-upload-label" title="Upload any file (doc, pdf, txt, csv, images)">
                                <i class="ti ti-upload"></i> File
                                <input type="file" id="aiFile2" style="display:none" onchange="aiUploadFile(this)">
                            </label>
                            <button class="ai-icobtn" id="aiMic2" title="Voice" onclick="aiVoice2()"><i class="ti ti-microphone"></i></button>
                            <textarea id="aiIn2" rows="1" placeholder="Ask the AI... e.g. 'add a client named John in New York', or say 'end session'" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();aiSend2();}"></textarea>
                            <button class="ai-icobtn" title="Send" onclick="aiSend2()"><i class="ti ti-send"></i></button>
                        </div>
                        <div class="mt-2 text-muted small">Tip: upload a document and the AI will read it and insert the details for you. Say <b>"end session"</b> when finished.</div>
                    </div>
                </div>
            </div>

            <?php } ?>
        </div>
    </div>

    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>
    <script>
        var AI_SID = <?= $activeSid ?>;
        var INITIAL_HISTORY = <?= json_encode($hist) ?>;
        window.aiState2 = { pending: null, lastSql: '' };

        /* ---------- Render helpers ---------- */
        function aiEsc(s){ return String(s==null?'':s).replace(/</g,'&lt;'); }
        function aiAppend(role, html, speak){
            var wrap=document.createElement('div'); wrap.className='ai-msg '+role;
            var b=document.createElement('div'); b.className='ai-bubble'; b.innerHTML=html;
            wrap.appendChild(b);
            var m=document.getElementById('aiMsgs2'); m.appendChild(wrap);
            m.scrollTop=m.scrollHeight;
            if(speak && role==='bot' && !window.__muteVoice){ aiSpeak(stripHtml(b.innerText||'')); }
            return wrap;
        }
        function stripHtml(h){ return h.replace(/<[^>]*>/g,' ').replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/\s+/g,' ').trim(); }
        function aiSpeak(text){
            if(!('speechSynthesis' in window)) return;
            window.speechSynthesis.cancel();
            var u=new SpeechSynthesisUtterance(text); u.rate=1.02; u.pitch=1;
            u.lang='en-US'; window.speechSynthesis.speak(u);
        }
        function aiAddBot(html, speak){ aiAppend('bot', html, speak); }

        /* ---------- Render a stored assistant message (may contain intents) */
        function aiRenderHistoryText(txt){
            if(/NEWCLIENT:\s*\{.*\}/is.test(txt)){
                var m=txt.match(/NEWCLIENT:\s*(\{.*\})/is);
                var f=JSON.parse(m[1]); aiAppend('bot', aiEsc(m[0]));
                aiShowClientPreview2(f);
                return;
            }
            if(/OPEN:\s*(.+)/is.test(txt)){
                var om=txt.match(/OPEN:\s*(.+)/is); aiOpenTarget(aiEsc(om[1].trim())); return;
            }
            aiAppend('bot', aiEsc(txt));
        }

        function renderInitial(){
            var m=document.getElementById('aiMsgs2'); m.innerHTML='';
            var any=false;
            if(INITIAL_HISTORY && INITIAL_HISTORY.length){
                INITIAL_HISTORY.forEach(function(hm){
                    if(hm.role==='system') return;
                    if(hm.role==='user'){ aiAppend('user', aiEsc(hm.content)); }
                    else { aiRenderHistoryText(hm.content); }
                    any=true;
                });
            }
            if(!any){
                aiAppend('bot', 'Hello! I'+"'"+'m your AI assistant. I can help you with anything in the CRM, and I remember our whole conversation until you end the session. You can:<br>• Ask about your data (clients, invoices, projects)<br>• Upload a document (doc, pdf, txt) to read &amp; insert details<br>• Open a client or module by just saying "open client 5"<br>• Use the mic to talk to me. Say <b>"end session"</b> when done.');
            }
            loadSessions();
        }

        /* ---------- Sessions ---------- */
        function loadSessions(){
            var fd=new FormData(); fd.append('action','session_list');
            fetch('ai_ajax.php',{method:'POST',body:fd}).then(r=>r.json()).then(function(res){
                var list=document.getElementById('aiSessList'); list.innerHTML='';
                if(!res.ok || !res.sessions.length){ list.innerHTML='<div class="ai-empty">No sessions yet.</div>'; return; }
                res.sessions.forEach(function(s){
                    var b=document.createElement('button');
                    b.className='ai-sess-item'+(s.status==='active'?' active':'');
                    b.innerHTML='<b>'+aiEsc(s.title)+'</b><small>'+aiEsc(s.status)+' · '+aiEsc(s.messages)+' msgs</small>';
                    b.onclick=function(){ aiOpenSession(s.session_id); };
                    list.appendChild(b);
                });
            });
        }
        function aiNewSession(){
            if(!confirm('Starting a new session ends & saves the current one. Continue?')) return;
            var fd=new FormData(); fd.append('action','session_new');
            fetch('ai_ajax.php',{method:'POST',body:fd}).then(r=>r.json()).then(function(res){
                if(res.ok){ location.href='ai.php'; }
            });
        }
        function aiOpenSession(sid){
            var fd=new FormData(); fd.append('action','session_open'); fd.append('session_id',sid);
            fetch('ai_ajax.php',{method:'POST',body:fd}).then(r=>r.json()).then(function(res){
                if(!res.ok){ alert(res.error||'Error opening session'); return; }
                location.href='ai.php';
            });
        }
        function aiEndSession(){
            var fd=new FormData(); fd.append('action','session_end'); fd.append('session_id',AI_SID);
            return fetch('ai_ajax.php',{method:'POST',body:fd}).then(r=>r.json());
        }

        /* ---------- Send / chat ---------- */
        function aiSend2(){
            var inp=document.getElementById('aiIn2'); var msg=inp.value.trim(); if(!msg) return;
            aiAppend('user', aiEsc(msg)); inp.value='';
            if(/^\s*(end|end session|stop|finish|that'?s all|bye session|close session)\s*[.!]?\s*$/i.test(msg)){
                aiDoEndSession(aiEsc(msg)); return;
            }
            aiAddBot('Thinking...');
            var fd=new FormData(); fd.append('action','chat'); fd.append('message',msg);
            aiDoChat(fd);
        }
        function aiDoChat(fd){
            fetch('ai_ajax.php',{method:'POST',body:fd})
                .then(r=>r.json())
                .then(function(res){
                    aiRmThinking();
                    if(!res.ok){ aiAddBot('Error: '+aiEsc(res.error||'Unknown')); return; }
                    aiHandle2(res);
                })
                .catch(function(){ aiRmThinking(); aiAddBot('Network error. Please try again.'); });
        }
        function aiRmThinking(){
            var msgs=document.getElementById('aiMsgs2'); var l=msgs.lastElementChild;
            if(l && l.textContent.indexOf('Thinking...')!==-1) l.remove();
        }
        function aiHandle2(res){
            if(res.ok===false){ aiAddBot('Error: '+aiEsc(res.error||'')); return; }
            if(res.session_id) AI_SID=res.session_id;
            if(res.type==='newclient'){ aiAppend('bot', aiEsc(res.text)); aiShowClientPreview2(res.fields); }
            else if(res.type==='note'){ aiAppend('bot', aiEsc(res.text)); aiShowNotePreview2(res.member_id,res.text_note); }
            else if(res.type==='table'){ aiAppend('bot', aiEsc(res.text)+'<br>'+aiTable2(res.rows,res.sql)); }
            else if(res.type==='open'){ aiOpenTarget(aiEsc(res.target||'')); }
            else if(res.type==='file_attach'){ aiAppend('bot', aiEsc(res.text)); aiOfferAttach(res.member_id,res.notes); }
            else if(res.session_ended){ aiEndSessionUi(); aiAppend('bot', aiEsc(res.text), true); }
            else { aiAppend('bot', aiEsc(res.text), true); }
        }
        function aiTable2(rows, sql){
            if(!rows || !rows.length) return '<div style="font-size:12px;color:#64748b">No matching records.</div>';
            var h=Object.keys(rows[0]); var html='<div style="overflow:auto;max-height:280px"><table class="ai-table"><thead><tr>';
            h.forEach(function(k){ html+='<th>'+aiEsc(k)+'</th>'; });
            html+='</tr></thead><tbody>';
            rows.forEach(function(r){ html+='<tr>'; h.forEach(function(k){ html+='<td>'+aiEsc(r[k])+'</td>'; }); html+='</tr>'; });
            html+='</tbody></table></div>';
            window.aiState2.lastSql=sql;
            html+='<div class="ai-tools"><button class="ai-csv" onclick="aiExp(\'export_csv\')">Download CSV</button><button class="ai-pdf" onclick="aiExp(\'export_pdf\')">Download PDF</button></div>';
            return html;
        }
        function aiExp(act){
            var fd=new FormData(); fd.append('action',act); fd.append('sql',window.aiState2.lastSql||''); fd.append('title','AI Export');
            fetch('ai_ajax.php',{method:'POST',body:fd}).then(function(r){
                var ct=r.headers.get('Content-Type')||'';
                if(ct.indexOf('application/json')!==-1) return r.json().then(function(j){ aiAddBot('Error: '+aiEsc(j.error||'')); return null; });
                return r.blob();
            }).then(function(b){ if(b){ var u=URL.createObjectURL(b); var a=document.createElement('a'); a.href=u; a.download='ai_export.'+(act==='export_csv'?'csv':'pdf'); document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(u);} });
        }

        /* ---------- Open target (module / client) in new tab ---------- */
        function aiOpenTarget(t){
            t=(t||'').trim().toLowerCase();
            var id=t.match(/(\d+)/);
            if(id){ window.open('client_details.php?id='+id[1],'_blank'); }
            else {
                var map={clients:'view_clients.php',invoices:'invoice_billings.php',invoice:'invoice_billings.php',projects:'projects.php',project:'projects.php',services:'memberships.php',service:'memberships.php',payments:'billing_payments.php',calendar:'reminder_calender.php',settings:'ai_settings.php',dashboard:'index.php'};
                var url=map[t]||null;
                if(url) window.open(url,'_blank');
                else { window.open('view_clients.php','_blank'); }
            }
            aiAddBot('Opening '+aiEsc(t)+' in a new tab.');
        }

        /* ---------- Client / note preview & confirm ---------- */
        function aiShowClientPreview2(fields){
            var f=fields||{}; window.aiState2.pending={type:'client',fields:f};
            var html='<div class="ai-preview"><h6>Verify before inserting</h6><table>';
            [['First name','name'],['Last name','lname'],['Business','bus_name'],['Phone','phone'],['Cell','cell_ph'],['Email','prm_email'],['Address','address'],['City','city'],['State','state'],['Zip','zip']].forEach(function(p){
                html+='<tr><td><b>'+p[0]+'</b></td><td>'+aiEsc(f[p[1]])+'</td></tr>';
            });
            html+='</table><div class="ai-confirmwrap"><button class="ai-confirm" onclick="aiConfirmClient2()">Confirm &amp; Insert</button><button class="ai-cancel" onclick="aiCancel2()">Cancel</button></div></div>';
            aiAppend('bot', html);
            aiSpeak('I found a new client. Please review and confirm to insert, or cancel.');
        }
        function aiShowNotePreview2(mid,note){
            window.aiState2.pending={type:'note',member_id:mid,text_note:note};
            var html='<div class="ai-preview"><h6>Add note to client #'+mid+'</h6><div style="font-size:12px;margin-bottom:8px">'+aiEsc(note)+'</div><div class="ai-confirmwrap"><button class="ai-confirm" onclick="aiConfirmNote2()">Confirm &amp; Add</button><button class="ai-cancel" onclick="aiCancel2()">Cancel</button></div></div>';
            aiAppend('bot', html);
            aiSpeak('Add this note to client number '+mid+'? Say confirm or type confirm.');
        }
        function aiConfirmClient2(){
            var p=window.aiState2.pending; if(!p) return;
            var fd=new FormData(); fd.append('action','preview_client'); fd.append('fields',JSON.stringify(p.fields));
            fetch('ai_ajax.php',{method:'POST',body:fd}).then(r=>r.json()).then(function(res){
                if(!res.ok){ aiAddBot('Error: '+aiEsc(res.error||'')); return; }
                var f2=new FormData(); f2.append('action','confirm_client'); f2.append('confirmed','1'); f2.append('fields',JSON.stringify(res.preview));
                return fetch('ai_ajax.php',{method:'POST',body:f2});
            }).then(r=>r?r.json():null).then(function(res){
                if(res && res.ok){ window.aiState2.pending=null; aiAddBot('Done! '+aiEsc(res.message)+'<br><a target="_blank" href="client_details.php?id='+res.member_id+'" style="color:#1456b8">Open client #'+res.member_id+'</a>', true); loadSessions(); }
                else if(res){ aiAddBot('Error: '+aiEsc(res.error||'')); }
            });
        }
        function aiConfirmNote2(){
            var p=window.aiState2.pending; if(!p) return;
            var fd=new FormData(); fd.append('action','preview_note'); fd.append('member_id',p.member_id); fd.append('text_note',p.text_note);
            fetch('ai_ajax.php',{method:'POST',body:fd}).then(r=>r.json()).then(function(res){
                if(!res.ok){ aiAddBot('Error: '+aiEsc(res.error||'')); return; }
                var f2=new FormData(); f2.append('action','confirm_note'); f2.append('confirmed','1'); f2.append('member_id',res.preview.member_id); f2.append('text_note',res.preview.note);
                return fetch('ai_ajax.php',{method:'POST',body:f2});
            }).then(r=>r?r.json():null).then(function(res){
                if(res && res.ok){ window.aiState2.pending=null; aiAddBot(aiEsc(res.message),true); loadSessions(); }
                else if(res){ aiAddBot('Error: '+aiEsc(res.error||'')); }
            });
        }
        function aiCancel2(){ window.aiState2.pending=null; aiAddBot('Cancelled.'); }
        function aiOfferAttach(mid,notes){
            var inp='<div class="ai-preview"><h6>Attach a file to client #'+mid+'</h6><div class="ai-filethumb"><i class="ti ti-file"></i> <input type="text" id="aiAttachName'+mid+'" placeholder="Upload a file first, then type its name"> <button class="ai-confirm" onclick="aiAttachSave('+mid+')">Attach</button></div></div>';
            aiAppend('bot', inp);
        }
        function aiAttachSave(mid){
            var name=document.getElementById('aiAttachName'+mid).value.trim();
            var fd=new FormData(); fd.append('action','file_attach'); fd.append('member_id',mid); fd.append('filename',name);
            fetch('ai_ajax.php',{method:'POST',body:fd}).then(r=>r.json()).then(function(res){
                aiAddBot(res.ok?aiEsc(res.message):'Error: '+aiEsc(res.error||''), res.ok);
            });
        }

        /* ---------- File upload ---------- */
        function aiUploadFile(input){
            var file=input.files[0]; if(!file) return;
            document.getElementById('aiFileInfo').textContent='Processing "'+file.name+'"...';
            aiAddBot('Reading file "'+aiEsc(file.name)+'"...');
            var fd=new FormData(); fd.append('action','upload_file'); fd.append('file',file); fd.append('session_id',AI_SID);
            aiDoChat(fd);
            input.value='';
        }

        /* ---------- Voice input + end-session confirmation ---------- */
        function aiVoice2(){
            var SR=window.SpeechRecognition||window.webkitSpeechRecognition;
            if(!SR){ aiAddBot('Voice is not supported in this browser.'); return; }
            var rec=new SR(); rec.lang='en-US'; rec.interimResults=true;
            var mic=document.getElementById('aiMic2'); mic.classList.add('rec');
            var collected='';
            rec.onresult=function(e){ var s=''; for(var i=0;i<e.results.length;i++) s+=e.results[i][0].transcript; collected=s; document.getElementById('aiIn2').value=s; };
            rec.onerror=function(){ mic.classList.remove('rec'); aiAddBot('Voice error — please type instead.'); };
            rec.onend=function(){ mic.classList.remove('rec'); var v=(collected||document.getElementById('aiIn2').value).trim(); if(v){ aiSend2(); } };
            rec.start();
        }
        function aiEndSessionVoice(){
            aiSpeak('Do you want to end and save this session? Say yes to confirm.');
            var SR=window.SpeechRecognition||window.webkitSpeechRecognition;
            if(!SR){ if(confirm('End and save this session?')) aiDoEndSession('end session'); return; }
            var rec=new SR(); rec.lang='en-US'; rec.interimResults=false;
            rec.onresult=function(e){ var txt=''; for(var i=0;i<e.results.length;i++) txt+=e.results[i][0].transcript; txt=txt.toLowerCase(); if(/yes|confirm|ok|yeah|sure/.test(txt)){ aiDoEndSession('end session'); } else { aiSpeak('Okay, the session continues.'); aiAddBot('Session continues.'); } };
            rec.onerror=function(){ if(confirm('End and save this session?')) aiDoEndSession('end session'); };
            rec.start();
        }
        function aiDoEndSession(displayText){
            aiAppend('user', displayText.replace(/</g,'&lt;'));
            aiAddBot('Ending session...');
            aiEndSession().then(function(res){
                aiEndSessionUi();
                aiAddBot(res.ok?aiEsc(res.message||'Session ended and saved.'):'Error ending session', true);
            });
        }
        function aiEndSessionUi(){
            var b=document.getElementById('aiChatTitle'); if(b) b.textContent='Session Ended';
            var badge=document.querySelector('.ai-chat-head .badge'); if(badge){ badge.className='badge bg-secondary'; badge.textContent='Ended'; }
            loadSessions();
        }

        renderInitial();
    </script>
</body>

</html>
