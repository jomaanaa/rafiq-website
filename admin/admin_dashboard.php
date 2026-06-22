<?php
// ============================================================
// FILE: rafiq/admin/admin_dashboard.php
// ============================================================
session_start();

// Admin must log in first from the main website login page, same as the app flow.
if (empty($_SESSION['admin_id'])) {
    header('Location: ../general/login.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Rafiq - Admin Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
<style>
/* ── RESET & BASE ─────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Nunito','Segoe UI',Arial,sans-serif;background:#f5f6fb;color:#1e1b4b;min-height:100vh}
button{cursor:pointer;font-family:inherit}
input,select,textarea{font-family:inherit}
table{border-collapse:collapse;width:100%}

/* ── LAYOUT ───────────────────────────────────────────── */
.app{display:flex;min-height:100vh}

/* ── SIDEBAR ──────────────────────────────────────────── */
.sidebar{width:228px;background:linear-gradient(180deg,#1e1b4b,#312e81);display:flex;flex-direction:column;flex-shrink:0;transition:width .22s ease;overflow:hidden}
.sidebar.collapsed{width:62px}
.sidebar-logo{padding:24px 18px 22px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center}
.admin-brand{width:100%;display:flex;flex-direction:column;align-items:center;text-align:center;gap:12px}
.sidebar-logo-img-wrap{width:158px;height:74px;border-radius:22px;background:#fff;display:flex;align-items:center;justify-content:center;padding:13px 18px;box-shadow:0 16px 36px rgba(0,0,0,.20),inset 0 0 0 1px rgba(255,255,255,.55)}
.sidebar-logo-img{width:100%;height:100%;display:block;object-fit:contain}
.sidebar-logo-sub{display:flex;flex-direction:column;gap:2px;align-items:center}
.sidebar-logo-sub strong{font-size:16px;font-weight:900;color:#fff;letter-spacing:-.2px;line-height:1}
.sidebar-logo-sub span{font-size:10px;font-weight:900;color:rgba(255,255,255,.52);letter-spacing:.16em;text-transform:uppercase}
.sidebar-nav{padding:16px 8px;flex:1}
.nav-btn{width:100%;display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:11px;border:none;background:transparent;color:rgba(255,255,255,.55);font-size:13px;font-weight:500;transition:all .14s;text-align:left;margin-bottom:3px;white-space:nowrap}
.nav-btn:hover{background:rgba(255,255,255,.08);color:rgba(255,255,255,.85)}
.nav-btn.active{background:rgba(255,255,255,.14);color:#fff;font-weight:800}
.nav-btn .nav-icon{font-size:15px;flex-shrink:0;width:18px;text-align:center}
.sidebar-footer{padding:12px 8px;border-top:1px solid rgba(255,255,255,.07)}
.collapse-btn{width:100%;padding:8px;border-radius:9px;border:none;background:rgba(255,255,255,.07);color:rgba(255,255,255,.5);font-size:13px;font-weight:600}
.collapse-btn:hover{background:rgba(255,255,255,.13)}
.sidebar.collapsed .nav-label,.sidebar.collapsed .sidebar-logo-sub{display:none}
.sidebar.collapsed .sidebar-logo{padding:18px 8px;justify-content:center}
.sidebar.collapsed .admin-brand{gap:0}
.sidebar.collapsed .sidebar-logo-img-wrap{width:44px;height:44px;padding:7px;border-radius:14px;overflow:hidden}
.nav-badge{background:#ef4444;color:#fff;font-size:10px;font-weight:900;padding:1px 7px;border-radius:99px;margin-left:auto;line-height:1.6}
.logout-btn{width:100%;display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:11px;border:none;background:rgba(239,68,68,.13);color:rgba(255,100,100,.9);font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;text-align:left;margin-top:4px;white-space:nowrap;transition:all .14s}
.logout-btn:hover{background:rgba(239,68,68,.22);color:#fca5a5}

/* ── MAIN ─────────────────────────────────────────────── */
.main{flex:1;display:flex;flex-direction:column;overflow:hidden}

/* ── TOP BAR ──────────────────────────────────────────── */
.topbar{background:#fff;border-bottom:1px solid #f1f5f9;padding:14px 28px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.topbar-title{font-size:15px;font-weight:800;color:#1e1b4b}
.topbar-right{display:flex;align-items:center;gap:10px}
.bell-btn{width:34px;height:34px;border-radius:10px;background:#f5f6fb;border:1.5px solid #f1f5f9;font-size:15px;display:flex;align-items:center;justify-content:center;color:#4f46e5}

/* ── PAGE CONTENT ─────────────────────────────────────── */
.page-content{flex:1;overflow-y:auto;padding:28px 28px 60px}
.page{display:none}
.page.active{display:block}
.page-title{font-size:22px;font-weight:900;color:#1e1b4b;margin-bottom:4px}
.page-sub{color:#64748b;font-size:14px;margin-bottom:24px}

/* ── AVATAR ───────────────────────────────────────────── */
.avatar{border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-weight:800;flex-shrink:0}

/* ── BADGES ───────────────────────────────────────────── */
.badge{padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:5px;white-space:nowrap}
.badge-dot{width:5px;height:5px;border-radius:50%}
.badge-pending  {background:#fffbeb;color:#92400e} .badge-pending .badge-dot  {background:#f59e0b}
.badge-accepted {background:#ecfdf5;color:#065f46} .badge-accepted .badge-dot {background:#10b981}
.badge-rejected {background:#fef2f2;color:#991b1b} .badge-rejected .badge-dot {background:#ef4444}
.badge-active   {background:#ecfdf5;color:#065f46} .badge-active .badge-dot   {background:#10b981}
.badge-hidden   {background:#f1f5f9;color:#374151} .badge-hidden .badge-dot   {background:#9ca3af}
.badge-completed{background:#eef2ff;color:#4338ca} .badge-completed .badge-dot{background:#4f46e5}
.badge-cancelled{background:#fef2f2;color:#991b1b} .badge-cancelled .badge-dot{background:#ef4444}
.badge-expired  {background:#f1f5f9;color:#475569} .badge-expired .badge-dot  {background:#64748b}
.badge-paid     {background:#ecfdf5;color:#065f46} .badge-paid .badge-dot     {background:#10b981}
.badge-unpaid   {background:#fffbeb;color:#92400e} .badge-unpaid .badge-dot   {background:#f59e0b}

/* ── STAT CARDS ───────────────────────────────────────── */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(185px,1fr));gap:14px;margin-bottom:24px}
.stat-card{background:#fff;border-radius:16px;padding:18px 20px;box-shadow:0 1px 8px rgba(30,27,75,.07);border:1px solid #f1f5f9;display:flex;align-items:center;gap:14px}
.stat-icon{width:46px;height:46px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.stat-value{font-size:26px;font-weight:900;line-height:1}
.stat-label{font-size:12px;color:#64748b;margin-top:3px;font-weight:600}

/* ── CARDS / PANELS ───────────────────────────────────── */
.card{background:#fff;border-radius:16px;box-shadow:0 1px 8px rgba(30,27,75,.07);border:1px solid #f1f5f9;overflow:hidden}
.card-pad{padding:20px 22px}
.card-title{font-weight:800;font-size:14px;color:#1e1b4b;margin-bottom:2px}
.card-sub{font-size:12px;color:#94a3b8;margin-bottom:14px}
.chart-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:22px}

.analytics-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:22px}
.metric-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid #f1f5f9}
.metric-row:last-child{border-bottom:none}
.metric-left{display:flex;align-items:center;gap:10px;min-width:0}
.metric-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0}
.metric-name{font-size:13px;font-weight:800;color:#1e1b4b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.metric-sub{font-size:11px;color:#94a3b8;margin-top:1px}
.metric-value{font-size:13px;font-weight:900;color:#1e1b4b;white-space:nowrap}
.hbar{height:7px;background:#eef2ff;border-radius:99px;overflow:hidden;margin-top:6px}
.hbar-fill{height:100%;background:linear-gradient(90deg,#4f46e5,#7c3aed);border-radius:99px}
.revenue-chart{display:flex;align-items:flex-end;gap:6px;height:80px;margin-top:4px}
.revenue-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px}
.revenue-fill{width:100%;background:linear-gradient(180deg,#10b981,#059669);border-radius:4px 4px 0 0;min-height:4px}
.revenue-label{font-size:9px;color:#94a3b8}
.revenue-value{font-size:9px;color:#065f46;font-weight:800}
@media(max-width:900px){.analytics-grid{grid-template-columns:1fr}}

/* ── BAR CHART ────────────────────────────────────────── */
.bar-chart{display:flex;align-items:flex-end;gap:5px;height:65px}
.bar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px}
.bar-fill{width:100%;background:#4f46e5;border-radius:3px 3px 0 0;opacity:.82;transition:height .3s}
.bar-label{font-size:9px;color:#94a3b8}

/* ── DONUT ────────────────────────────────────────────── */
.donut-wrap{display:flex;align-items:center;gap:18px}
.donut-legend{display:flex;flex-direction:column;gap:8px}
.donut-row{display:flex;align-items:center;gap:8px;font-size:12px;color:#64748b}
.donut-dot{width:10px;height:10px;border-radius:2px;flex-shrink:0}
.donut-count{font-size:13px;font-weight:800;color:#1e1b4b;margin-left:auto;padding-left:10px}

/* ── RECENT BOOKINGS ──────────────────────────────────── */
.recent-row{display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid #f1f5f9}
.recent-row:last-child{border-bottom:none}
.recent-info{flex:1}
.recent-name{font-size:13px;font-weight:700;color:#1e1b4b}
.recent-meta{font-size:11px;color:#94a3b8;margin-top:2px;text-transform:capitalize}
.urgent-tag{background:#fef2f2;color:#ef4444;font-size:9px;font-weight:800;padding:1px 5px;border-radius:99px;margin-left:4px}
.booking-id{font-size:11px;color:#94a3b8}

/* ── FILTER BAR ───────────────────────────────────────── */
.filter-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px}
.filter-input{flex:1 1 200px;padding:9px 14px;border-radius:10px;border:1.5px solid #f1f5f9;font-size:13px;color:#1e1b4b;outline:none}
.filter-input:focus{border-color:#a5b4fc}
.filter-select{padding:9px 12px;border-radius:10px;border:1.5px solid #f1f5f9;font-size:13px;color:#1e1b4b;background:#fff}
.filter-select:focus{border-color:#a5b4fc;outline:none}

/* ── TABLE ────────────────────────────────────────────── */
.table-wrap{overflow-x:auto}
table thead tr{background:#f8fafc;border-bottom:1px solid #f1f5f9}
table thead th{padding:12px 16px;text-align:left;font-size:10px;font-weight:800;color:#94a3b8;letter-spacing:.06em;text-transform:uppercase;white-space:nowrap}
table tbody tr{border-bottom:1px solid #f8fafc;transition:background .12s}
table tbody tr:hover{background:#fafaff}
table tbody tr:last-child{border-bottom:none}
table tbody td{padding:13px 16px;font-size:13px}
.td-name{display:flex;align-items:center;gap:10px}
.td-name-text{font-weight:700;color:#1e1b4b}
.td-name-email{font-size:11px;color:#94a3b8}
.cat-badge{background:#eef2ff;color:#4338ca;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700}
.service-badge{background:#eef2ff;color:#4338ca;padding:2px 9px;border-radius:99px;font-size:11px;font-weight:700;text-transform:capitalize}
.td-muted{color:#64748b}
.td-bold{font-weight:800;color:#1e1b4b}
.td-actions{display:flex;gap:5px;flex-wrap:nowrap}

/* ── BUTTONS ──────────────────────────────────────────── */
.btn{padding:9px 20px;border-radius:11px;border:none;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit}
.btn-primary{background:linear-gradient(135deg,#4f46e5,#312e81);color:#fff}
.btn-primary:hover{opacity:.92}
.btn-ghost{padding:6px 12px;border-radius:8px;border:1.5px solid #f1f5f9;background:#fff;color:#64748b;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;white-space:nowrap}
.btn-ghost:hover{border-color:#a5b4fc;color:#4f46e5}
.btn-ghost-purple{padding:6px 12px;border-radius:8px;border:1.5px solid #f1f5f9;background:#fff;color:#4f46e5;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit}
.btn-green{padding:6px 10px;border-radius:8px;border:none;background:#ecfdf5;color:#065f46;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit}
.btn-green:hover{background:#d1fae5}
.btn-red{padding:6px 10px;border-radius:8px;border:none;background:#fef2f2;color:#991b1b;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit}
.btn-red:hover{background:#fee2e2}
.btn-note{padding:6px 10px;border-radius:8px;border:1.5px solid #f1f5f9;background:#fff;color:#64748b;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit}

/* ── PLACE CARDS ──────────────────────────────────────── */
.places-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px}
.place-card{background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 1px 8px rgba(30,27,75,.07);border:1px solid #f1f5f9;display:flex;flex-direction:column}
.place-card-top{background:linear-gradient(135deg,#1e1b4b,#4f46e5);padding:20px 18px 16px;position:relative}
.place-card-icon{font-size:28px;margin-bottom:6px}
.place-card-name{font-size:15px;font-weight:800;color:#fff}
.place-card-addr{font-size:12px;color:#a5b4fc;margin-top:4px}
.place-card-status{position:absolute;top:12px;right:12px}
.place-card-body{padding:14px 18px;flex:1}
.place-features{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:10px}
.feature-chip{background:#eef2ff;color:#4338ca;font-size:11px;padding:2px 9px;border-radius:99px;font-weight:600}
.place-desc{font-size:12px;color:#64748b;line-height:1.5;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.place-card-footer{padding:10px 18px 14px;border-top:1px solid #f1f5f9;display:flex;gap:6px;align-items:center}
.place-status-select{flex:1;padding:5px 8px;border-radius:8px;border:1.5px solid #f1f5f9;font-size:11px;color:#64748b;background:#fff;font-family:inherit}

/* ── MODALS ───────────────────────────────────────────── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(10,8,40,.62);z-index:9000;align-items:center;justify-content:center;padding:16px}
.modal-overlay.open{display:flex}
.modal-box{background:#fff;border-radius:20px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 32px 80px rgba(30,27,75,.28)}
.modal-header{padding:20px 24px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f1f5f9;position:sticky;top:0;background:#fff;z-index:1}
.modal-title{font-size:17px;font-weight:800;color:#1e1b4b}
.modal-close{background:#f5f6fb;border:none;border-radius:99px;width:30px;height:30px;font-size:18px;color:#64748b;display:flex;align-items:center;justify-content:center;cursor:pointer}
.modal-body{padding:20px 24px 28px}
.confirm-overlay{display:none;position:fixed;inset:0;background:rgba(10,8,40,.62);z-index:9999;align-items:center;justify-content:center}
.confirm-overlay.open{display:flex}
.confirm-box{background:#fff;border-radius:16px;width:370px;padding:28px;box-shadow:0 24px 60px rgba(30,27,75,.22)}
.confirm-title{font-size:16px;font-weight:800;color:#1e1b4b;margin-bottom:10px}
.confirm-msg{font-size:14px;color:#64748b;margin-bottom:22px;line-height:1.6}
.confirm-btns{display:flex;gap:10px;justify-content:flex-end}

/* ── DETAIL MODAL ─────────────────────────────────────── */
.detail-header{background:linear-gradient(135deg,#1e1b4b,#4f46e5);border-radius:14px;padding:18px 20px;margin-bottom:18px;display:flex;align-items:center;gap:16px}
.detail-header-info{flex:1}
.detail-header-name{font-size:19px;font-weight:900;color:#fff;margin:0}
.detail-cat-chip{background:rgba(255,255,255,.18);color:#fff;padding:2px 10px;border-radius:99px;font-size:12px;font-weight:700;display:inline-block;margin-top:4px}
.fields-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px}
.field-box{padding:11px 14px;background:#f5f6fb;border-radius:10px}
.field-box.wide{grid-column:1/-1}
.field-label{font-size:10px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px}
.field-value{font-size:13px;color:#1e1b4b;font-weight:600;word-break:break-word}
.bookings-history{margin-bottom:14px}
.history-row{display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#f5f6fb;border-radius:8px;margin-bottom:5px}
.history-id{font-size:12px;font-weight:700;color:#1e1b4b}
.history-meta{font-size:11px;color:#64748b;margin-left:8px}
.history-right{display:flex;gap:6px;align-items:center}
.note-box{padding:13px 15px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;margin-bottom:14px}
.note-label{font-size:10px;font-weight:800;color:#92400e;text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px}
.note-text{font-size:13px;color:#78350f}
.detail-actions{display:flex;gap:10px;padding-top:16px;border-top:1px solid #f1f5f9}
.btn-accept-full{flex:1;padding:11px;border-radius:11px;border:none;background:linear-gradient(135deg,#10b981,#059669);color:#fff;font-weight:800;font-size:14px;cursor:pointer;font-family:inherit}
.btn-reject-full{flex:1;padding:11px;border-radius:11px;border:none;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;font-weight:800;font-size:14px;cursor:pointer;font-family:inherit}


.file-link{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 12px;
    border-radius:10px;
    background:#eef2ff;
    color:#4338ca;
    font-size:12px;
    font-weight:900;
    text-decoration:none;
    border:1px solid #dfe5ff;
    transition:.15s ease;
}
.file-link:hover{
    background:#e0e7ff;
    transform:translateY(-1px);
}
.file-muted{
    color:#94a3b8;
    font-size:13px;
    font-weight:700;
}
.file-text-value{
    white-space:pre-wrap;
    font-weight:600;
    color:#475569;
    line-height:1.6;
}

/* ── FORM ─────────────────────────────────────────────── */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.form-group{display:flex;flex-direction:column;gap:5px}
.form-group.full{grid-column:1/-1}
.form-label{font-size:10px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em}
.form-input{padding:9px 12px;border-radius:10px;border:1.5px solid #f1f5f9;font-size:13px;color:#1e1b4b;outline:none;font-family:inherit}
.form-input:focus{border-color:#a5b4fc}
.form-textarea{padding:9px 12px;border-radius:10px;border:1.5px solid #f1f5f9;font-size:13px;color:#1e1b4b;outline:none;resize:vertical;font-family:inherit}
.form-textarea:focus{border-color:#a5b4fc}
.feature-toggle{padding:7px 14px;border-radius:99px;font-size:12px;font-weight:700;border:none;background:#f5f6fb;color:#64748b;cursor:pointer;font-family:inherit;transition:all .15s}
.feature-toggle.on{background:#4f46e5;color:#fff}
.form-footer{display:flex;gap:10px;justify-content:flex-end;border-top:1px solid #f1f5f9;padding-top:16px;margin-top:4px}

/* ── PENDING REQUESTS PANEL ───────────────────────────── */
.pending-panel{background:#fff;border-radius:18px;box-shadow:0 1px 8px rgba(30,27,75,.07);border:2px solid #fde68a;overflow:hidden;margin-bottom:22px}
.pending-panel-header{background:linear-gradient(135deg,#fffbeb,#fef3c7);padding:16px 22px;display:flex;align-items:center;justify-content:space-between;gap:12px;border-bottom:1px solid #fde68a}
.pending-panel-title{font-size:15px;font-weight:900;color:#92400e;display:flex;align-items:center;gap:10px}
.pending-count-badge{background:#f59e0b;color:#fff;font-size:12px;font-weight:900;padding:3px 10px;border-radius:99px;line-height:1.5}
.pending-panel-sub{font-size:12px;color:#b45309}
.pending-view-all{padding:7px 14px;border-radius:9px;border:1.5px solid #f59e0b;background:transparent;color:#92400e;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit;transition:all .15s}
.pending-view-all:hover{background:#fef3c7}
.pending-row{display:flex;align-items:center;gap:14px;padding:14px 22px;border-bottom:1px solid #f8fafc;transition:background .12s}
.pending-row:last-child{border-bottom:none}
.pending-row:hover{background:#fffbeb}
.pending-row-info{flex:1;min-width:0}
.pending-row-name{font-size:14px;font-weight:800;color:#1e1b4b}
.pending-row-meta{font-size:12px;color:#94a3b8;margin-top:2px}
.pending-row-actions{display:flex;gap:6px;flex-shrink:0}
.pending-empty{padding:32px;text-align:center;color:#a3a3a3;font-size:14px}

/* ── SPINNER ──────────────────────────────────────────── */
.spinner-wrap{display:flex;justify-content:center;align-items:center;padding:56px}
.spinner{width:34px;height:34px;border:3px solid #eef2ff;border-top-color:#4f46e5;border-radius:50%;animation:spin .65s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.empty-state{padding:48px 24px;text-align:center;color:#94a3b8;font-size:14px}

/* ── TOAST ────────────────────────────────────────────── */
.toast{position:fixed;bottom:24px;right:24px;background:#1e1b4b;color:#fff;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:700;z-index:99999;opacity:0;transform:translateY(10px);transition:all .3s;pointer-events:none}
.toast.show{opacity:1;transform:translateY(0)}

@media(max-width:768px){
  .chart-grid{grid-template-columns:1fr}
  .stat-grid{grid-template-columns:repeat(auto-fill,minmax(150px,1fr))}
  .fields-grid,.form-grid{grid-template-columns:1fr}
  .page-content{padding:16px}
}


/* ── APP-STYLE ADMIN OVERVIEW / CHARTS ─────────────────── */
.stat-grid.app-stat-grid{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:14px;margin-bottom:14px}
.app-stat-card{border-radius:15px;padding:18px 14px;min-height:116px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;box-shadow:0 10px 24px rgba(30,27,75,.12);position:relative;overflow:hidden}
.app-stat-card:after{content:'';position:absolute;inset:auto -24px -32px auto;width:84px;height:84px;border-radius:50%;background:rgba(255,255,255,.10)}
.app-stat-icon{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;margin-bottom:9px;font-size:15px}
.app-stat-value{font-size:25px;font-weight:900;line-height:1;margin-bottom:4px}
.app-stat-label{font-size:11px;font-weight:800}
.app-stat-sub{font-size:10px;margin-top:2px}
.app-revenue-card{border-radius:15px;padding:18px;background:linear-gradient(135deg,#2b2c41,#404066);box-shadow:0 12px 28px rgba(43,44,65,.24);margin-bottom:14px;color:#fff}
.rev-head{display:flex;align-items:center;gap:9px;font-size:13px;font-weight:800;margin-bottom:15px;color:rgba(255,255,255,.88)}
.rev-icon{width:31px;height:31px;border-radius:9px;display:flex;align-items:center;justify-content:center;background:rgba(237,204,111,.18);color:#edcc6f}
.rev-gross{text-align:center;width:100%;padding:4px 0 2px}
.rev-label{font-size:10px;color:rgba(255,255,255,.55);font-weight:700;text-align:center}
.rev-total{font-size:30px;font-weight:900;line-height:1.05;margin-top:3px;text-align:center}
.rev-divider{height:1px;background:rgba(255,255,255,.18);margin:16px 0}
.rev-split{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.rev-mini-title{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:900;margin-bottom:5px}
.rev-platform{color:#edcc6f}.rev-provider{color:#88cafc}
.rev-mini-value{font-size:18px;font-weight:900;color:#fff}.rev-mini-sub{font-size:10px;color:rgba(255,255,255,.42)}
.charts-trigger{width:100%;border:none;border-radius:14px;padding:14px 18px;background:linear-gradient(135deg,#404066,#88cafc);box-shadow:0 8px 22px rgba(64,64,102,.22);display:flex;align-items:center;justify-content:center;gap:10px;color:#fff;font-size:14px;font-weight:900;margin-bottom:14px}
.charts-trigger:hover{filter:brightness(1.02);transform:translateY(-1px)}
.app-accordion-card{background:#fff;border-radius:16px;border:1px solid #d2ebff;box-shadow:0 3px 14px rgba(30,27,75,.06);overflow:hidden;margin-bottom:18px}
.app-accordion-head{width:100%;padding:15px 16px;background:#fff;border:none;display:flex;align-items:center;gap:11px;text-align:left}
.app-accordion-icon{width:32px;height:32px;border-radius:9px;background:rgba(237,204,111,.16);color:#b08a00;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.app-accordion-title-wrap{flex:1;display:flex;flex-direction:column}.app-accordion-title{font-size:14px;font-weight:900;color:#2b2c41}.app-accordion-sub{font-size:11px;font-weight:700;color:#404066;opacity:.72}.app-accordion-chevron{color:#404066;transition:transform .22s}.app-accordion-chevron.up{transform:rotate(180deg)}
.app-accordion-body{display:none;border-top:1px solid #d2ebff}.app-accordion-body.open{display:block}
.sheet-overlay{display:none;position:fixed;inset:0;background:rgba(30,27,75,.40);z-index:9800;align-items:flex-end;justify-content:center}.sheet-overlay.open{display:flex}
.analytics-sheet{width:min(980px,100%);height:min(92vh,900px);background:#f6f8fd;border-radius:24px 24px 0 0;box-shadow:0 -24px 70px rgba(30,27,75,.24);display:flex;flex-direction:column;overflow:hidden}
.sheet-handle{width:42px;height:4px;border-radius:99px;background:#d2ebff;margin:12px auto 7px}.sheet-head{padding:6px 20px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #d2ebff}.sheet-title-wrap{display:flex;align-items:center;gap:9px;font-size:17px;font-weight:900;color:#2b2c41}.sheet-title-wrap i{color:#404066}.sheet-close{width:33px;height:33px;border:none;border-radius:10px;background:#d2ebff;color:#404066;display:flex;align-items:center;justify-content:center}.sheet-body{padding:16px;overflow-y:auto}
.app-chart-card{background:#fff;border:1px solid #d2ebff;border-radius:16px;padding:16px;margin-bottom:14px;box-shadow:0 3px 14px rgba(30,27,75,.06)}
.app-chart-head{display:flex;align-items:center;gap:10px;margin-bottom:16px}.app-chart-head strong{display:block;font-size:14px;font-weight:900;color:#2b2c41}.app-chart-head small{display:block;font-size:11px;font-weight:700;color:#404066;opacity:.72}.app-chart-icon{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center}.app-chart-icon.blue{background:rgba(136,202,252,.18);color:#404066}.app-chart-icon.gold{background:rgba(237,204,111,.18);color:#b08a00}.app-chart-icon.purple{background:rgba(64,64,102,.12);color:#404066}
.app-bar-chart{height:170px;display:flex;align-items:flex-end;gap:18px;padding:10px 8px 22px;position:relative;background:repeating-linear-gradient(to top, transparent 0, transparent 44px, #eaf4ff 45px)}
.app-bar-col{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;position:relative}.app-bar-fill{width:min(42px,70%);background:linear-gradient(180deg,#404066,#88cafc);border-radius:8px 8px 0 0;min-height:4px}.app-bar-fill.dim{background:linear-gradient(180deg,#d2ebff,#88cafc)}.app-bar-label{position:absolute;bottom:-20px;font-size:10px;font-weight:800;color:#404066}.app-bar-tip{font-size:10px;font-weight:900;color:#2b2c41;margin-bottom:5px}
.app-line-chart-wrap{height:180px;background:linear-gradient(to top,transparent 0,transparent 44px,#eaf4ff 45px);border-radius:10px;overflow:hidden}.app-line-chart-wrap svg{width:100%;height:100%}.line-grid{stroke:#eaf4ff;stroke-width:1}.line-path{fill:none;stroke:#edcc6f;stroke-width:4;stroke-linecap:round;stroke-linejoin:round}.line-area{fill:url(#revenueArea)}.line-dot{fill:#edcc6f;stroke:#fff;stroke-width:4}.axis-label{fill:#404066;font-size:11px;font-weight:800}.y-label{fill:#404066;font-size:10px;opacity:.75}
.app-chart-two{display:grid;grid-template-columns:1fr 1fr;gap:14px}.app-donut-block{display:flex;align-items:center;justify-content:center;gap:14px;min-height:166px}.app-donut-legend{display:flex;flex-direction:column;gap:7px;min-width:0}.app-donut-row{display:grid;grid-template-columns:10px 1fr auto;align-items:center;gap:7px;font-size:11px;color:#404066;min-width:130px}.app-donut-dot{width:9px;height:9px;border-radius:50%}.app-donut-name{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-transform:capitalize}.app-donut-count{font-weight:900;color:#2b2c41}.donut-center-main{font-size:16px;font-weight:900;fill:#2b2c41}.donut-center-sub{font-size:8px;fill:#404066;opacity:.72;font-weight:800}.empty-state.app-empty{padding:32px 12px;text-align:center;color:#404066;font-size:12px;font-weight:700}

.analytics-page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px}
.top-provider-item{display:flex;align-items:center;gap:12px;padding:14px 18px;border-top:1px solid #f1f5f9;background:#fff}
.top-provider-item:first-child{border-top:0}
.top-provider-rank{width:28px;height:28px;border-radius:10px;background:#eef6ff;color:#404066;font-size:12px;font-weight:900;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.top-provider-main{flex:1;min-width:0}
.top-provider-line{display:flex;justify-content:space-between;gap:10px;align-items:center}
.top-provider-name{font-size:14px;font-weight:900;color:#1e1b4b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.top-provider-earned{font-size:13px;font-weight:900;color:#404066;white-space:nowrap}
.top-provider-meta{font-size:11px;font-weight:700;color:#94a3b8;margin-top:2px}
.top-provider-track{height:6px;background:#eef2ff;border-radius:99px;overflow:hidden;margin-top:8px}
.top-provider-fill{height:100%;background:linear-gradient(90deg,#404066,#88cafc);border-radius:99px}
@media(max-width:980px){.stat-grid.app-stat-grid{grid-template-columns:repeat(2,1fr)}.app-chart-two{grid-template-columns:1fr}.app-donut-block{justify-content:flex-start}.analytics-page-head{flex-direction:column}}

.rating-summary{display:flex;align-items:center;gap:10px;border-radius:12px;background:rgba(237,204,111,.13);border:1px solid rgba(237,204,111,.32);padding:12px;margin-bottom:14px}.rating-summary-icon{width:42px;height:42px;border-radius:12px;background:rgba(237,204,111,.22);color:#b08a00;display:flex;align-items:center;justify-content:center;font-size:20px}.rating-summary strong{display:block;font-size:24px;font-weight:900;color:#2b2c41;line-height:1}.rating-summary small{font-size:11px;font-weight:700;color:#404066}

</style>
</head>
<body>

<div class="app">

  <!-- ══════════════════════════════════════════════════
       SIDEBAR
  ══════════════════════════════════════════════════ -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="admin-brand">
        <div class="sidebar-logo-img-wrap">
          <img src="../pictures/rafiq_logo.png" alt="Rafiq" class="sidebar-logo-img">
        </div>
        <div class="sidebar-logo-sub">
          <strong>Rafiq</strong>
          <span>Admin Panel</span>
        </div>
      </div>
    </div>
    <nav class="sidebar-nav">
      <button class="nav-btn active" id="navOverview" onclick="showPage('overview',this)">
        <span class="nav-icon"><i class="fa-solid fa-table-columns"></i></span><span class="nav-label">Dashboard</span>
      </button>

      <button class="nav-btn" id="navAnalytics" onclick="showPage('analytics',this)">
        <span class="nav-icon"><i class="fa-solid fa-chart-column"></i></span><span class="nav-label">Charts & Analytics</span>
      </button>
      <button class="nav-btn" onclick="showPage('providers',this)" id="navProviders">
        <span class="nav-icon"><i class="fa-solid fa-user-tie"></i></span><span class="nav-label">Providers</span>
        <span class="nav-badge" id="pendingBadge" style="display:none"></span>
      </button>
      <button class="nav-btn" onclick="showPage('patients',this)">
        <span class="nav-icon"><i class="fa-solid fa-wheelchair"></i></span><span class="nav-label">Patients</span>
      </button>
      <button class="nav-btn" onclick="showPage('places',this)">
        <span class="nav-icon"><i class="fa-solid fa-location-dot"></i></span><span class="nav-label">Places</span>
      </button>
      <button class="nav-btn" onclick="showPage('bookings',this)">
        <span class="nav-icon"><i class="fa-solid fa-receipt"></i></span><span class="nav-label">Bookings</span>
      </button>
    </nav>
    <div class="sidebar-footer">
      <button class="collapse-btn" onclick="toggleSidebar()" id="collapseBtn">Collapse</button>
      <a href="../general/logout.php" class="logout-btn" style="text-decoration:none">
        <span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span><span class="nav-label">Logout</span>
      </a>
    </div>
  </aside>

  <!-- ══════════════════════════════════════════════════
       MAIN
  ══════════════════════════════════════════════════ -->
  <div class="main">

    <!-- Top Bar -->
    <header class="topbar">
      <div>
        <div class="topbar-title" id="pageTitle">Dashboard Overview</div>
      </div>
      <div class="topbar-right">
        <div style="position:relative;cursor:pointer" onclick="showPage('providers',document.getElementById('navProviders'))" title="Pending provider requests">
          <div class="bell-btn"><i class="fa-solid fa-bell"></i></div>
          <span id="bellBadge" style="display:none;position:absolute;top:-4px;right:-4px;background:#ef4444;color:#fff;font-size:9px;font-weight:900;padding:1px 5px;border-radius:99px;line-height:1.6"></span>
        </div>
      </div>
    </header>

    <!-- Pages -->
    <div class="page-content">

      <!-- ════════════════════════════════════════════
           PAGE: OVERVIEW
      ════════════════════════════════════════════ -->
      <div class="page active" id="page-overview">
        <div class="page-title">Dashboard Overview</div>
        <div class="page-sub">Here's what's happening on Rafiq right now.</div>

        <!-- Stat Cards -->
        <div class="stat-grid" id="statGrid">
          <div class="spinner-wrap"><div class="spinner"></div></div>
        </div>


        <!-- Revenue Breakdown -->
        <div class="app-revenue-card" id="revenueBreakdownCard"></div>

        <!-- App-style chart trigger -->
        <button class="charts-trigger" type="button" onclick="showPage('analytics', document.getElementById('navAnalytics'))">
          <i class="fa-solid fa-chart-column"></i>
          <span>View Charts & Analytics</span>
          <i class="fa-solid fa-arrow-right"></i>
        </button>

        <!-- Top Providers like the app overview -->
        <div class="app-accordion-card">
          <button class="app-accordion-head" type="button" onclick="toggleTopProviders()">
            <span class="app-accordion-icon"><i class="fa-solid fa-award"></i></span>
            <span class="app-accordion-title-wrap">
              <span class="app-accordion-title">Top Providers</span>
              <span class="app-accordion-sub">Hours worked and revenue earned</span>
            </span>
            <i class="fa-solid fa-chevron-down app-accordion-chevron" id="topProvidersChevron"></i>
          </button>
          <div class="app-accordion-body open" id="topProvidersAccordionBody">
            <div id="topProvidersList"></div>
          </div>
        </div>
      </div>

      <!-- ════════════════════════════════════════════
           PAGE: ANALYTICS
      ════════════════════════════════════════════ -->
      <div class="page" id="page-analytics">
        <div class="analytics-page-head">
          <div>
            <div class="page-title">Charts & Analytics</div>
            <div class="page-sub">Dashboard charts and performance insights.</div>
          </div>
          <button class="btn-ghost-purple" type="button" onclick="showPage('overview', document.getElementById('navOverview'))">Back to Overview</button>
        </div>

        <div class="app-chart-card">
          <div class="app-chart-head">
            <span class="app-chart-icon blue"><i class="fa-solid fa-chart-column"></i></span>
            <span>
              <strong>Monthly Bookings</strong>
              <small>Booking activity per month</small>
            </span>
          </div>
          <div class="app-bar-chart" id="barChart"></div>
        </div>

        <div class="app-chart-card">
          <div class="app-chart-head">
            <span class="app-chart-icon gold"><i class="fa-solid fa-arrow-trend-up"></i></span>
            <span>
              <strong>Revenue Trend</strong>
              <small>Monthly earnings from completed bookings</small>
            </span>
          </div>
          <div class="app-line-chart-wrap">
            <svg id="revenueLineChart" viewBox="0 0 620 180" preserveAspectRatio="none"></svg>
          </div>
        </div>

        <div class="app-chart-two">
          <div class="app-chart-card">
            <div class="app-chart-head">
              <span class="app-chart-icon purple"><i class="fa-solid fa-circle-notch"></i></span>
              <span>
                <strong>By Category</strong>
                <small>Provider types</small>
              </span>
            </div>
            <div class="app-donut-block">
              <svg id="categoryDonutSvg" viewBox="0 0 120 120" width="130" height="130"></svg>
              <div class="app-donut-legend" id="categoryDonutLegend"></div>
            </div>
          </div>

          <div class="app-chart-card">
            <div class="app-chart-head">
              <span class="app-chart-icon gold"><i class="fa-solid fa-layer-group"></i></span>
              <span>
                <strong>Services</strong>
                <small>Most requested</small>
              </span>
            </div>
            <div class="app-donut-block">
              <svg id="serviceDonutSvg" viewBox="0 0 120 120" width="130" height="130"></svg>
              <div class="app-donut-legend" id="serviceDonutLegend"></div>
            </div>
          </div>
        </div>

        <div class="app-chart-card">
          <div class="app-chart-head">
            <span class="app-chart-icon gold"><i class="fa-solid fa-star"></i></span>
            <span>
              <strong>Provider Ratings</strong>
              <small>Top rated providers by patients</small>
            </span>
          </div>
          <div id="providerRatings"></div>
        </div>
      </div>

      <!-- ════════════════════════════════════════════
           PAGE: PROVIDERS
      ════════════════════════════════════════════ -->
      <div class="page" id="page-providers">
        <div class="page-title">Provider Management</div>
        <div class="page-sub">Review, accept, or reject service provider applications.</div>

        <div class="filter-bar">
          <input class="filter-input" id="provSearch" placeholder="Search name or email..." oninput="loadProviders()"/>
          <select class="filter-select" id="provStatus" onchange="loadProviders()">
            <option value="all">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="accepted">Accepted</option>
            <option value="rejected">Rejected</option>
          </select>
          <select class="filter-select" id="provCat" onchange="loadProviders()">
            <option value="all">All Categories</option>
            <option value="driver">Driver</option>
            <option value="doctor">Doctor</option>
            <option value="caregiver">Caregiver</option>
            <option value="interpreter">Interpreter</option>
          </select>
        </div>

        <div class="card">
          <div class="table-wrap">
            <table>
              <thead><tr>
                <th>Provider</th><th>Category</th><th>Location</th>
                <th>Bookings</th><th>Status</th><th>Actions</th>
              </tr></thead>
              <tbody id="provTable"><tr><td colspan="6"><div class="spinner-wrap"><div class="spinner"></div></div></td></tr></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ════════════════════════════════════════════
           PAGE: PLACES
      ════════════════════════════════════════════ -->
      <div class="page" id="page-places">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
          <div>
            <div class="page-title">Reviews of Accessible Places</div>
            <div class="page-sub" style="margin-bottom:0">Reviews stored in the places table using rating and comment.</div>
          </div>
          <button class="btn btn-primary" onclick="openPlaceReviews()">View Place Reviews</button>
        </div>

        <div class="filter-bar">
          <input class="filter-input" id="placeSearch" placeholder="Search rated places..." oninput="loadPlaces()"/>
          <select class="filter-select" id="placeType" onchange="loadPlaces()">
            <option value="all">All Types</option>
            <option>Hospital</option><option>Clinic</option><option>Mall</option>
            <option>Park</option><option>Museum</option><option>Restaurant</option>
            <option>Hotel</option><option>Mosque</option><option>Church</option>
            <option>Pharmacy</option><option>School</option><option>University</option>
            <option>Government Office</option><option>Other</option>
          </select>
          <select class="filter-select" id="placeStatus" onchange="loadPlaces()">
            <option value="all">All Statuses</option>
            <option value="active">Active</option>
            <option value="pending">Pending</option>
            <option value="hidden">Hidden</option>
          </select>
        </div>

        <div class="places-grid" id="placesGrid">
          <div class="spinner-wrap"><div class="spinner"></div></div>
        </div>
      </div>

      <!-- ════════════════════════════════════════════
           PAGE: BOOKINGS
      ════════════════════════════════════════════ -->
      <div class="page" id="page-bookings">
        <div class="page-title">Bookings</div>
        <div class="page-sub">All patient-provider booking transactions.</div>

        <div class="filter-bar">
          <input class="filter-input" id="bookSearch" placeholder="Search patient or provider..." oninput="loadBookings()"/>
          <select class="filter-select" id="bookStatus" onchange="loadBookings()">
            <option value="all">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="expired">Expired</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
          <select class="filter-select" id="bookService" onchange="loadBookings()">
            <option value="all">All Services</option>
            <option value="caregiver">Caregiver</option>
            <option value="driver">Driver</option>
            <option value="doctor">Doctor</option>
            <option value="interpreter">Interpreter</option>
          </select>
        </div>

        <div class="card">
          <div class="table-wrap">
            <table>
              <thead><tr>
                <th>#</th><th>Patient</th><th>Provider</th><th>Service</th>
                <th>Date</th><th>Amount</th><th>Payment</th><th>Rating</th><th>Status</th>
              </tr></thead>
              <tbody id="bookTable"><tr><td colspan="9"><div class="spinner-wrap"><div class="spinner"></div></div></td></tr></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ════════════════════════════════════════════
           PAGE: PATIENTS
      ════════════════════════════════════════════ -->
      <div class="page" id="page-patients">
        <div class="page-title">Patients</div>
        <div class="page-sub">All registered patients on the Rafiq platform.</div>

        <div class="filter-bar">
          <input class="filter-input" id="patSearch" placeholder="Search name or email..." oninput="loadPatients()"/>
        </div>

        <div class="card">
          <div class="table-wrap">
            <table>
              <thead><tr>
                <th>Patient</th><th>Phone</th><th>Gender</th><th>Disability</th><th>Address</th><th>Bookings</th>
              </tr></thead>
              <tbody id="patTable"><tr><td colspan="6"><div class="spinner-wrap"><div class="spinner"></div></div></td></tr></tbody>
            </table>
          </div>
        </div>
      </div>

    </div><!-- end page-content -->
  </div><!-- end main -->
</div><!-- end app -->


<!-- ══════════════════════════════════════════════════════════
     MODALS
══════════════════════════════════════════════════════════ -->

<!-- Provider Detail Modal -->
<div class="modal-overlay" id="modalDetail">
  <div class="modal-box" style="max-width:740px">
    <div class="modal-header">
      <span class="modal-title">Provider Details</span>
      <button class="modal-close" onclick="closeModal('modalDetail')">×</button>
    </div>
    <div class="modal-body" id="modalDetailBody"></div>
  </div>
</div>

<!-- Note Modal -->
<div class="modal-overlay" id="modalNote">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header">
      <span class="modal-title" id="noteModalTitle">Add Note</span>
      <button class="modal-close" onclick="closeModal('modalNote')">×</button>
    </div>
    <div class="modal-body">
      <textarea class="form-textarea" id="noteText" rows="5" style="width:100%" placeholder="Write a note about this provider..."></textarea>
      <div class="form-footer" style="margin-top:14px;padding-top:14px">
        <button class="btn-ghost" onclick="closeModal('modalNote')">Cancel</button>
        <button class="btn btn-primary" onclick="saveNote()">Save Note</button>
      </div>
    </div>
  </div>
</div>

<!-- Place Form Modal -->
<div class="modal-overlay" id="modalPlace">
  <div class="modal-box" style="max-width:660px">
    <div class="modal-header">
      <span class="modal-title" id="placeModalTitle">Add New Place</span>
      <button class="modal-close" onclick="closeModal('modalPlace')">×</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="placeEditId"/>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Place Name *</label>
          <input class="form-input" id="fName" placeholder="e.g. Cairo Festival City"/>
        </div>
        <div class="form-group">
          <label class="form-label">Type *</label>
          <select class="form-input" id="fType">
            <option value="">Select type...</option>
            <option>Hospital</option><option>Clinic</option><option>Mall</option>
            <option>Park</option><option>Museum</option><option>Restaurant</option>
            <option>Hotel</option><option>Mosque</option><option>Church</option>
            <option>Pharmacy</option><option>School</option><option>University</option>
            <option>Government Office</option><option>Other</option>
          </select>
        </div>
        <div class="form-group full">
          <label class="form-label">Address *</label>
          <input class="form-input" id="fAddress" placeholder="Full address"/>
        </div>
        <div class="form-group">
          <label class="form-label">Latitude</label>
          <input class="form-input" id="fLat" placeholder="e.g. 30.0444"/>
        </div>
        <div class="form-group">
          <label class="form-label">Longitude</label>
          <input class="form-input" id="fLng" placeholder="e.g. 31.2357"/>
        </div>
        <div class="form-group full">
          <label class="form-label">Accessibility Features</label>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:2px">
            <button type="button" class="feature-toggle" id="ft-elevator" onclick="toggleFeature('elevator')">Elevator</button>
            <button type="button" class="feature-toggle" id="ft-ramp"     onclick="toggleFeature('ramp')">Wheelchair Ramp</button>
            <button type="button" class="feature-toggle" id="ft-toilet"   onclick="toggleFeature('toilet')">Accessible Restroom</button>
            <button type="button" class="feature-toggle" id="ft-parking"  onclick="toggleFeature('parking')">Disabled Parking</button>
          </div>
        </div>
        <div class="form-group full">
          <label class="form-label">Description</label>
          <textarea class="form-textarea" id="fComment" rows="3" placeholder="Describe the accessibility setup..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Photo URL</label>
          <input class="form-input" id="fPhoto" placeholder="https://..."/>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select class="form-input" id="fStatus">
            <option value="active">Active</option>
            <option value="pending">Pending</option>
            <option value="hidden">Hidden</option>
          </select>
        </div>
      </div>
      <div class="form-footer">
        <button class="btn-ghost" onclick="closeModal('modalPlace')">Cancel</button>
        <button class="btn btn-primary" onclick="savePlace()">Save Place</button>
      </div>
    </div>
  </div>
</div>

<!-- Place View Modal -->
<div class="modal-overlay" id="modalPlaceView">
  <div class="modal-box" style="max-width:520px">
    <div class="modal-header">
      <span class="modal-title">Place Details</span>
      <button class="modal-close" onclick="closeModal('modalPlaceView')">×</button>
    </div>
    <div class="modal-body" id="modalPlaceViewBody"></div>
  </div>
</div>

<!-- Place Reviews Modal -->
<div class="modal-overlay" id="modalPlaceReviews">
  <div class="modal-box" style="max-width:760px">
    <div class="modal-header">
      <span class="modal-title">Accessible Place Reviews</span>
      <button class="modal-close" onclick="closeModal('modalPlaceReviews')">×</button>
    </div>
    <div class="modal-body">
      <div class="filter-bar" style="margin-bottom:14px">
        <input class="filter-input" id="placeReviewSearch" placeholder="Search patient, place, or comment..." oninput="renderPlaceReviews()"/>
      </div>
      <div id="placeReviewsBody"><div class="spinner-wrap"><div class="spinner"></div></div></div>
    </div>
  </div>
</div>


<!-- Confirm Modal -->
<div class="confirm-overlay" id="confirmModal">
  <div class="confirm-box">
    <div class="confirm-title" id="confirmTitle">Are you sure?</div>
    <div class="confirm-msg"   id="confirmMsg"></div>
    <div class="confirm-btns">
      <button class="btn-ghost" onclick="closeConfirm()">Cancel</button>
      <button class="btn" id="confirmYesBtn" onclick="confirmYes()">Confirm</button>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>


<!-- ══════════════════════════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════════════════════════ -->
<script>
// ── CONFIG ─────────────────────────────────────────────────
const API = 'admin_api.php'; // same folder - change if needed

// ── STATE ──────────────────────────────────────────────────
let currentNoteProviderId = null;
let currentNoteProviderStatus = 'pending';
let confirmCallback = null;
let placeFeatures = { elevator:false, ramp:false, toilet:false, parking:false };
let sidebarOpen = true;

// ── INIT ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  loadOverview();
});

// ── SIDEBAR ────────────────────────────────────────────────
function toggleSidebar() {
  sidebarOpen = !sidebarOpen;
  document.getElementById('sidebar').classList.toggle('collapsed', !sidebarOpen);
  document.getElementById('collapseBtn').textContent = sidebarOpen ? 'Collapse' : 'Open';
}

// ── PAGE ROUTING ───────────────────────────────────────────
const pageTitles = { overview:'Dashboard Overview', analytics:'Charts & Analytics', providers:'Provider Management', patients:'Patients', places:'Reviews of Accessible Places', bookings:'Bookings' };

function showPage(id, btn) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('page-' + id).classList.add('active');
  if (btn) btn.classList.add('active');
  document.getElementById('pageTitle').textContent = pageTitles[id];

  // Always reload the active page from the database.
  if (id === 'overview')  loadOverview();
  if (id === 'analytics') loadOverview();
  if (id === 'providers') loadProviders();
  if (id === 'patients')  loadPatients();
  if (id === 'places')    loadPlaces();
  if (id === 'bookings')  loadBookings();
}


function getCurrentPageId(){
  const active = document.querySelector('.page.active');
  return active ? active.id.replace('page-', '') : 'overview';
}

function refreshCurrentPage(){
  const id = getCurrentPageId();
  if (id === 'overview' || id === 'analytics') loadOverview();
  if (id === 'providers') loadProviders();
  if (id === 'patients') loadPatients();
  if (id === 'places') loadPlaces();
  if (id === 'bookings') loadBookings();
}

// Keeps dashboard data synced with DB changes without refreshing the browser.
setInterval(refreshCurrentPage, 15000);

// ── API HELPER ─────────────────────────────────────────────
async function apiFetch(action, opts = {}) {
  const cacheBust = `_=${Date.now()}`;
  const url =
    `${API}?action=${action}` +
    (opts.id ? `&id=${opts.id}` : '') +
    (opts.qs ? `&${opts.qs}` : '') +
    `&${cacheBust}`;

  const res = await fetch(url, {
    method:  opts.method || 'GET',
    headers: { 'Content-Type': 'application/json' },
    body:    opts.body   ? JSON.stringify(opts.body) : undefined,
    cache:   'no-store',
  });

  if (!res.ok) throw new Error('HTTP ' + res.status);
  return res.json();
}

// ── HELPERS ────────────────────────────────────────────────
function badge(status) {
  const s = (status || 'pending').toLowerCase();
  return `<span class="badge badge-${s}"><span class="badge-dot"></span>${cap(status)}</span>`;
}
function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : '-'; }
function avatar(name, size = 34) {
  const letters = (name || '?').split(' ').map(w => w[0]).slice(0,2).join('').toUpperCase();
  const colors = ['#4f46e5','#7c3aed','#0891b2','#10b981','#d97706'];
  const bg = colors[(name || '?').charCodeAt(0) % colors.length];
  return `<div class="avatar" style="width:${size}px;height:${size}px;font-size:${size*.36}px;background:${bg}">${letters}</div>`;
}
function stars(r) { return r ? (parseInt(r) + '/5') : '-'; }
function catIcon(c) { const cls = {Driver:'fa-car',Doctor:'fa-user-doctor',Caregiver:'fa-hand-holding-heart',Interpreter:'fa-hands-asl-interpreting'}[c] || 'fa-user-tie'; return `<i class="fa-solid ${cls}"></i>`; }
function placeIcon(t) { const cls = {Hospital:'fa-hospital',Clinic:'fa-house-medical',Mall:'fa-store',Park:'fa-tree',Museum:'fa-landmark',Restaurant:'fa-utensils',Hotel:'fa-hotel',Mosque:'fa-mosque',Church:'fa-church',Pharmacy:'fa-prescription-bottle-medical',School:'fa-school',University:'fa-graduation-cap','Government Office':'fa-building-columns',Other:'fa-location-dot'}[t] || 'fa-location-dot'; return `<i class="fa-solid ${cls}"></i>`; }
function featureChips(p) {
  const f = [];
  if (p.elevator) f.push('Elevator');
  if (p.ramp)     f.push('Wheelchair Ramp');
  if (p.toilet)   f.push('Accessible Restroom');
  if (p.parking)  f.push('Disabled Parking');
  return f;
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2800);
}

// ── MODAL HELPERS ──────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function openConfirm(title, msg, danger, cb) {
  document.getElementById('confirmTitle').textContent = title;
  document.getElementById('confirmMsg').textContent   = msg;
  const btn = document.getElementById('confirmYesBtn');
  btn.textContent = danger ? 'Yes, Confirm' : 'Yes';
  btn.style.background = danger ? '#ef4444' : '#10b981';
  confirmCallback = cb;
  document.getElementById('confirmModal').classList.add('open');
}
function closeConfirm() { document.getElementById('confirmModal').classList.remove('open'); confirmCallback = null; }
function confirmYes()   { if (confirmCallback) confirmCallback(); closeConfirm(); }

// ════════════════════════════════════════════════════════════
//  OVERVIEW
// ════════════════════════════════════════════════════════════
async function loadOverview() {
  try {
    const s = await apiFetch('stats');
    renderStats(s);
    renderRevenueBreakdown(s);
    renderBarChart(s.monthly || []);
    renderRevenueLineChart(s.monthlyRevenue || []);
    renderCategoryDonut(s.byCategory || []);
    renderServicesDonut(s.services || []);
    renderProviderRatings(s.topProviders || [], s.avgRating || 0);
    renderTopProviders(s.topProviders || []);
    // pending badge on sidebar + bell
    const pending = s.pendingProviders || 0;
    const badge = document.getElementById('pendingBadge');
    const bell  = document.getElementById('bellBadge');
    if (pending > 0) {
      badge.textContent = pending; badge.style.display = 'inline-block';
      bell.textContent  = pending; bell.style.display  = 'inline-block';
    } else {
      badge.style.display = 'none';
      bell.style.display  = 'none';
    }
  } catch(e) { console.error(e); }
}

function renderStats(s) {
  const cards = [
    { label:'Total Providers', value:s.totalProviders || 0, sub:(s.acceptedProviders || 0) + ' active', icon:'fa-user-tie', grad:'linear-gradient(135deg,#2b2c41,#404066)', valueColor:'#fff', subColor:'rgba(255,255,255,.55)', iconBg:'rgba(255,255,255,.12)', iconColor:'#fff' },
    { label:'Total Patients', value:s.totalPatients || 0, sub:'Registered', icon:'fa-user', grad:'linear-gradient(135deg,#404066,#88cafc)', valueColor:'#fff', subColor:'rgba(255,255,255,.58)', iconBg:'rgba(255,255,255,.14)', iconColor:'#fff' },
    { label:'Total Bookings', value:s.totalBookings || 0, sub:(s.doneBookings || 0) + ' done · ' + (s.expiredBookings || 0) + ' expired', icon:'fa-receipt', grad:'linear-gradient(135deg,#88cafc,#404066)', valueColor:'#fff', subColor:'rgba(255,255,255,.58)', iconBg:'rgba(255,255,255,.14)', iconColor:'#fff' },
    { label:'Pending', value:s.pendingProviders || 0, sub:'Need review', icon:'fa-clock', grad:'linear-gradient(135deg,#edcc6f,#d4a832)', valueColor:'#2b2c41', subColor:'rgba(43,44,65,.65)', iconBg:'rgba(43,44,65,.12)', iconColor:'#2b2c41' },
  ];
  const grid = document.getElementById('statGrid');
  grid.className = 'stat-grid app-stat-grid';
  grid.innerHTML = cards.map(c => `
    <div class="app-stat-card" style="background:${c.grad};color:${c.valueColor}">
      <div class="app-stat-icon" style="background:${c.iconBg};color:${c.iconColor}"><i class="fa-solid ${c.icon}"></i></div>
      <div class="app-stat-value" style="color:${c.valueColor}">${c.value}</div>
      <div class="app-stat-label" style="color:${c.valueColor}">${c.label}</div>
      <div class="app-stat-sub" style="color:${c.subColor}">${c.sub}</div>
    </div>`).join('');
}

function renderRevenueBreakdown(s) {
  const box = document.getElementById('revenueBreakdownCard');
  if (!box) return;
  const total = Number(s.totalRevenue || 0);
  const platform = Number(s.platformRevenue || (total * 0.15));
  const payouts = Number(s.providerPayouts || (total * 0.85));
  box.innerHTML = `
    <div class="rev-head"><span class="rev-icon"><i class="fa-solid fa-wallet"></i></span><span>Revenue Breakdown</span></div>
    <div class="rev-gross">
      <div class="rev-label">Gross Revenue</div>
      <div class="rev-total">EGP ${total.toLocaleString(undefined,{maximumFractionDigits:2})}</div>
    </div>
    <div class="rev-divider"></div>
    <div class="rev-split">
      <div>
        <div class="rev-mini-title rev-platform"><i class="fa-solid fa-building"></i><span>Rafiq (15%)</span></div>
        <div class="rev-mini-value">EGP ${platform.toLocaleString(undefined,{maximumFractionDigits:2})}</div>
        <div class="rev-mini-sub">Platform commission</div>
      </div>
      <div>
        <div class="rev-mini-title rev-provider"><i class="fa-solid fa-users"></i><span>Providers (85%)</span></div>
        <div class="rev-mini-value">EGP ${payouts.toLocaleString(undefined,{maximumFractionDigits:2})}</div>
        <div class="rev-mini-sub">Provider earnings</div>
      </div>
    </div>`;
}

function toggleTopProviders(){
  const body = document.getElementById('topProvidersAccordionBody');
  const chev = document.getElementById('topProvidersChevron');
  body.classList.toggle('open');
  chev.classList.toggle('up', body.classList.contains('open'));
}

function renderRevenueLineChart(data) {
  const svg = document.getElementById('revenueLineChart');
  if (!svg) return;
  if (!data.length) { svg.outerHTML = '<div id="revenueLineChart" class="empty-state app-empty">No revenue yet</div>'; return; }
  const values = data.map(d => Number(d.total) || 0);
  const max = Math.max(...values, 1);
  const w = 620, h = 180, padL = 45, padR = 18, padT = 16, padB = 30;
  const plotW = w - padL - padR, plotH = h - padT - padB;
  const x = i => padL + (data.length === 1 ? plotW / 2 : (i / (data.length - 1)) * plotW);
  const y = v => padT + plotH - (v / max) * plotH;
  const pts = values.map((v,i)=>[x(i), y(v)]);
  let d = '';
  pts.forEach((p,i)=>{
    if(i===0) d += `M ${p[0]} ${p[1]}`;
    else {
      const prev = pts[i-1];
      const cx = (prev[0]+p[0])/2;
      d += ` C ${cx} ${prev[1]}, ${cx} ${p[1]}, ${p[0]} ${p[1]}`;
    }
  });
  const area = d + ` L ${pts[pts.length-1][0]} ${padT+plotH} L ${pts[0][0]} ${padT+plotH} Z`;
  const grid = [0,.33,.66,1].map(t=>`<line class="line-grid" x1="${padL}" y1="${padT+plotH*t}" x2="${w-padR}" y2="${padT+plotH*t}"/>`).join('');
  const labels = data.map((m,i)=>`<text class="axis-label" x="${x(i)}" y="${h-8}" text-anchor="middle">${m.month}</text>`).join('');
  const ylabels = [0, max/2, max].map(v=>`<text class="y-label" x="6" y="${y(v)+4}">${v>=1000?(v/1000).toFixed(0)+'k':Math.round(v)}</text>`).join('');
  const dots = pts.map(p=>`<circle class="line-dot" cx="${p[0]}" cy="${p[1]}" r="5"/>`).join('');
  svg.outerHTML = `<svg id="revenueLineChart" viewBox="0 0 ${w} ${h}" preserveAspectRatio="none"><defs><linearGradient id="revenueArea" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#edcc6f" stop-opacity="0.25"/><stop offset="100%" stop-color="#edcc6f" stop-opacity="0.02"/></linearGradient></defs>${grid}${ylabels}<path class="line-area" d="${area}"/><path class="line-path" d="${d}"/>${dots}${labels}</svg>`;
}


function renderTopProviders(data) {
  const box = document.getElementById('topProvidersList');
  if (!box) return;
  const list = (data || []).slice(0, 5);
  if (!list.length) {
    box.innerHTML = '<div class="empty-state app-empty">No providers yet</div>';
    return;
  }
  const maxRevenue = Math.max(...list.map(p => Number(p.total_earned || 0)), 1);
  box.innerHTML = list.map((p, i) => {
    const name = p.name || 'Provider';
    const category = p.category || 'Provider';
    const bookings = Number(p.total_bookings || 0);
    const earned = Number(p.total_earned || 0);
    const hours = Number(p.total_hours || 0);
    const rating = Number(p.avg_rating || 0);
    const width = Math.max(8, Math.round((earned / maxRevenue) * 100));
    return `
      <div class="top-provider-item">
        <div class="top-provider-rank">${i + 1}</div>
        ${avatar(name, 42)}
        <div class="top-provider-main">
          <div class="top-provider-line">
            <span class="top-provider-name">${name}</span>
            <span class="top-provider-earned">EGP ${earned.toLocaleString(undefined,{maximumFractionDigits:0})}</span>
          </div>
          <div class="top-provider-meta">${category} - ${bookings} bookings - ${hours.toFixed(1)} hours${rating ? ' - ' + rating.toFixed(1) + ' rating' : ''}</div>
          <div class="top-provider-track"><div class="top-provider-fill" style="width:${width}%"></div></div>
        </div>
      </div>`;
  }).join('');
}

function renderProviderRatings(data, avgRating) {
  const box = document.getElementById('providerRatings');
  if (!box) return;
  const rated = (data || []).filter(p => Number(p.avg_rating || 0) > 0).slice(0,5);
  if (!rated.length) { box.innerHTML = '<div class="empty-state app-empty">No ratings yet</div>'; return; }
  box.innerHTML = `
    <div class="rating-summary"><span class="rating-summary-icon"><i class="fa-solid fa-star"></i></span><span><strong>${Number(avgRating || 0).toFixed(1)}</strong><small>Overall average rating</small></span></div>
    ${rated.map(p => {
      const rating = Number(p.avg_rating || 0);
      return `<div class="metric-row"><div class="metric-left" style="flex:1">${avatar(p.name || '-',34)}<div style="flex:1"><div class="metric-name">${p.name || '-'}</div><div class="metric-sub">${p.category || 'Provider'} - ${p.rating_count || 0} reviews</div></div></div><div class="metric-value">${rating.toFixed(1)} / 5</div></div>`;
    }).join('')}`;
}

function renderCategoryDonut(data) {
  const filtered = (data || []).filter(d => String(d.category || '').toLowerCase() !== 'provider 2');
  renderDonutChart('categoryDonutSvg','categoryDonutLegend', filtered.map(d => ({name:d.category || 'Provider', count:parseInt(d.count)||0})), ['#404066','#88cafc','#edcc6f','#2b2c41','#d2ebff']);
}

function renderServicesDonut(raw) {
  const totals = {};

  (raw || []).forEach(x => {
    let name = String(x.service_type || '').trim();

    if (
      name === '' ||
      name.toLowerCase() === 'unknown' ||
      name.toLowerCase() === 'null' ||
      name.toLowerCase() === 'undefined'
    ) {
      return;
    }

    const lower = name.toLowerCase();

    if (lower.startsWith('interpreter')) {
      name = 'Interpreters';
    } else if (lower.startsWith('caregiver')) {
      name = 'Caregiver';
    } else if (lower.startsWith('driver')) {
      name = 'Driver';
    } else if (lower.startsWith('doctor')) {
      name = 'Doctor';
    }

    totals[name] = (totals[name] || 0) + (parseInt(x.count) || 0);
  });

  const data = Object.entries(totals)
    .map(([name, count]) => ({ name, count }))
    .filter(item => item.count > 0)
    .sort((a, b) => b.count - a.count)
    .slice(0, 5);

  renderDonutChart(
    'serviceDonutSvg',
    'serviceDonutLegend',
    data,
    ['#88cafc', '#edcc6f', '#404066', '#2b2c41', '#d2ebff']
  );
}

function renderDonutChart(svgId, legendId, data, colors) {
  const svg = document.getElementById(svgId), legend = document.getElementById(legendId);
  if (!svg || !legend) return;
  data = (data || []).filter(d => d.count > 0);
  if (!data.length) { svg.innerHTML = ''; legend.innerHTML = '<div class="empty-state app-empty">No data yet</div>'; return; }
  const total = data.reduce((sum,d)=>sum+d.count,0);
  const cx = 60, cy = 60, r = 38, sw = 18;
  const circ = 2 * Math.PI * r;
  let offset = 0;
  let circles = `<circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="#edf6ff" stroke-width="${sw}"/>`;
  data.forEach((d,i)=>{
    const dash = d.count / total * circ;
    circles += `<circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="${colors[i % colors.length]}" stroke-width="${sw}" stroke-linecap="round" stroke-dasharray="${Math.max(0,dash-2)} ${circ}" stroke-dashoffset="${-offset + circ*.25}"/>`;
    offset += dash;
  });
  svg.innerHTML = circles + `<text class="donut-center-main" x="60" y="58" text-anchor="middle">${total}</text><text class="donut-center-sub" x="60" y="70" text-anchor="middle">total</text>`;
  legend.innerHTML = data.map((d,i)=>`<div class="app-donut-row"><span class="app-donut-dot" style="background:${colors[i % colors.length]}"></span><span class="app-donut-name">${d.name}</span><span class="app-donut-count">${d.count}</span></div>`).join('');
}

function renderBarChart(data) {
  const box = document.getElementById('barChart');
  if (!box) return;
  if (!data.length) { box.innerHTML = '<div class="empty-state app-empty">No data yet</div>'; return; }
  const max = Math.max(...data.map(d => parseInt(d.count) || 0), 1);
  box.innerHTML = data.map(d => {
    const count = parseInt(d.count) || 0;
    const h = Math.max(4, Math.round((count / max) * 128));
    const isMax = count === max && count > 0;
    return `<div class="app-bar-col"><div class="app-bar-tip">${count}</div><div class="app-bar-fill ${isMax?'':'dim'}" style="height:${h}px"></div><span class="app-bar-label">${d.month}</span></div>`;
  }).join('');
}

function renderRecent(list) {
  if (!list.length) { document.getElementById('recentList').innerHTML = '<div class="empty-state">No bookings yet</div>'; return; }
  document.getElementById('recentList').innerHTML = list.map(b => `
    <div class="recent-row">
      ${avatar(b.patient_name || '?', 34)}
      <div class="recent-info">
        <div class="recent-name">${b.patient_name||'-'} - ${b.provider_name||'-'}
          ${b.is_urgent ? '<span class="urgent-tag">URGENT</span>' : ''}
        </div>
        <div class="recent-meta">${b.service_type||''} - ${b.date||''}</div>
      </div>
      ${badge(b.status)}
      <span class="booking-id">#${b.booking_id}</span>
    </div>`).join('');
}

// ════════════════════════════════════════════════════════════
//  PROVIDERS
// ════════════════════════════════════════════════════════════
let debounceTimer;
function loadProviders() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(_loadProviders, 300);
}

async function _loadProviders() {
  document.getElementById('provTable').innerHTML = '<tr><td colspan="6"><div class="spinner-wrap"><div class="spinner"></div></div></td></tr>';
  const search   = document.getElementById('provSearch').value;
  const status   = document.getElementById('provStatus').value;
  const category = document.getElementById('provCat').value;
  const qs = new URLSearchParams({ search, status, category }).toString();
  try {
    const list = await apiFetch('providers&' + qs);
    if (!list.length) { document.getElementById('provTable').innerHTML = '<tr><td colspan="6"><div class="empty-state">No providers found</div></td></tr>'; return; }
    document.getElementById('provTable').innerHTML = list.map(p => `
      <tr>
        <td><div class="td-name">
          ${avatar(`${p.first_name} ${p.last_name}`, 34)}
          <div><div class="td-name-text">${p.first_name} ${p.last_name}</div><div class="td-name-email">${p.email}</div></div>
        </div></td>
        <td><span class="cat-badge">${catIcon(p.category)} ${p.category}</span></td>
        <td class="td-muted" style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${p.address||'-'}</td>
        <td class="td-bold" style="text-align:center">${p.total_bookings||0}</td>
        <td>${badge(p.status||'pending')}</td>
        <td><div class="td-actions">
          <button class="btn-ghost-purple" onclick="openProviderDetail(${p.user_id})">View</button>
          ${p.status !== 'accepted' ? `<button class="btn-green" onclick="confirmStatusChange(${p.user_id},'${p.first_name} ${p.last_name}','accepted')">Accept</button>` : ''}
          ${p.status !== 'rejected' ? `<button class="btn-red"   onclick="confirmStatusChange(${p.user_id},'${p.first_name} ${p.last_name}','rejected')">Reject</button>` : ''}
          <button class="btn-note" onclick="openNote(${p.user_id},'${p.first_name} ${p.last_name}','${(p.admin_note||'').replace(/'/g,"\\'")}','${p.status || 'pending'}')">Note</button>
        </div></td>
      </tr>`).join('');
  } catch(e) { console.error(e); }
}


function escapeAttr(value){
  return String(value ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}

function escapeText(value){
  return String(value ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}

function normalizeFilePath(path){
  const raw = String(path || '').trim();
  if (!raw || raw === '-' || raw.toLowerCase() === 'null') return '';

  const clean = raw.replace(/\\/g, '/');

  if (/^https?:\/\//i.test(clean)) return clean;

  if (clean.startsWith('../')) return clean;
  if (clean.startsWith('/')) return '..' + clean;

  if (clean.startsWith('uploads/')) return '../' + clean;
  if (clean.startsWith('pictures/')) return '../' + clean;

  return '../uploads/' + clean.split('/').pop();
}

function looksLikeFile(value){
  const raw = String(value || '').trim();
  if (!raw) return false;

  if (/^https?:\/\//i.test(raw)) return true;
  if (raw.includes('/') || raw.includes('\\')) return true;

  return /\.(pdf|jpg|jpeg|png|webp|gif|doc|docx)$/i.test(raw);
}

function renderFileOrText(value, label){
  const raw = String(value || '').trim();

  if (!raw || raw.toLowerCase() === 'null') {
    return '<span class="file-muted">-</span>';
  }

  if (!looksLikeFile(raw)) {
    return `<div class="file-text-value">${escapeText(raw)}</div>`;
  }

  const url = normalizeFilePath(raw);

  return `<a class="file-link" href="${escapeAttr(url)}" target="_blank" rel="noopener">
    <i class="fa-solid fa-up-right-from-square"></i>
    View ${escapeAttr(label)}
  </a>`;
}

function renderFileLink(value, label){
  const raw = String(value || '').trim();

  if (!raw || raw.toLowerCase() === 'null') {
    return '<span class="file-muted">-</span>';
  }

  const url = normalizeFilePath(raw);

  return `<a class="file-link" href="${escapeAttr(url)}" target="_blank" rel="noopener">
    <i class="fa-solid fa-up-right-from-square"></i>
    View ${escapeAttr(label)}
  </a>`;
}

async function openProviderDetail(id) {
  document.getElementById('modalDetailBody').innerHTML = '<div class="spinner-wrap"><div class="spinner"></div></div>';
  openModal('modalDetail');

  try {
    const p = await apiFetch('provider_detail', { id });

    let extras = '';

    if (p.category === 'Driver') {
      extras = `
        <div class="fields-grid">
          <div class="field-box">
            <div class="field-label">Driving License</div>
            <div class="field-value">${renderFileOrText(p.driving_license, 'Driving License')}</div>
          </div>
          <div class="field-box">
            <div class="field-label">Car</div>
            <div class="field-value">${escapeText((p.car_make || '') + ' ' + (p.car_model || '-'))}</div>
          </div>
          <div class="field-box">
            <div class="field-label">Plate Number</div>
            <div class="field-value">${escapeText(p.license_plate || '-')}</div>
          </div>
          <div class="field-box">
            <div class="field-label">Wheelchair Van</div>
            <div class="field-value">${p.wheelchair_accessible === 't' || p.wheelchair_accessible === true ? 'Yes' : 'No'}</div>
          </div>
          <div class="field-box">
            <div class="field-label">Total Trips</div>
            <div class="field-value">${escapeText(p.total_trips || 0)}</div>
          </div>
          <div class="field-box">
            <div class="field-label">Balance</div>
            <div class="field-value">EGP ${escapeText(p.available_balance || 0)}</div>
          </div>
        </div>`;
    }

    if (p.category === 'Doctor') {
      extras = `
        <div class="fields-grid">
          <div class="field-box">
            <div class="field-label">Medical License</div>
            <div class="field-value">${renderFileOrText(p.medical_license, 'Medical License')}</div>
          </div>
          <div class="field-box">
            <div class="field-label">Speciality</div>
            <div class="field-value">${escapeText(p.speciality || '-')}</div>
          </div>
        </div>`;
    }

    if (p.category === 'Caregiver') {
      extras = `
        <div class="field-box" style="margin-bottom:14px">
          <div class="field-label">Shift Preference</div>
          <div class="field-value">${escapeText(p.shift_preference || '-')}</div>
        </div>`;
    }

    if (p.category === 'Interpreter') {
      extras = `
        <div class="field-box" style="margin-bottom:14px">
          <div class="field-label">Languages</div>
          <div class="field-value">${escapeText(p.languages || '-')}</div>
        </div>`;
    }

    document.getElementById('modalDetailBody').innerHTML = `
      <div class="detail-header">
        ${avatar(`${p.first_name} ${p.last_name}`, 54)}
        <div class="detail-header-info">
          <h3 class="detail-header-name">${escapeText(p.first_name)} ${escapeText(p.last_name)}</h3>
          <span class="detail-cat-chip">${catIcon(p.category)} ${escapeText(p.category)}</span>
        </div>
        ${badge(p.status || 'pending')}
      </div>

      <div class="fields-grid">
        <div class="field-box">
          <div class="field-label">Email</div>
          <div class="field-value">${escapeText(p.email || '-')}</div>
        </div>
        <div class="field-box">
          <div class="field-label">Phone</div>
          <div class="field-value">${escapeText((p.phone || '').trim() || '-')}</div>
        </div>
        <div class="field-box">
          <div class="field-label">Address</div>
          <div class="field-value">${escapeText(p.address || '-')}</div>
        </div>
        <div class="field-box">
          <div class="field-label">Gender</div>
          <div class="field-value">${escapeText(p.gender || '-')}</div>
        </div>
        <div class="field-box">
          <div class="field-label">Date of Birth</div>
          <div class="field-value">${escapeText(p.dob || '-')}</div>
        </div>
        <div class="field-box">
          <div class="field-label">National ID</div>
          <div class="field-value">${escapeText(p.national_id || '-')}</div>
        </div>
        <div class="field-box wide">
          <div class="field-label">CV</div>
          <div class="field-value">${renderFileOrText(p.cv, 'CV')}</div>
        </div>
      </div>

      ${extras}

      ${p.admin_note ? `<div class="note-box"><div class="note-label">Admin Note</div><div class="note-text">${escapeText(p.admin_note)}</div></div>` : ''}

      <div class="detail-actions">
        ${p.status !== 'accepted' ? `<button class="btn-accept-full" onclick="changeStatus(${p.user_id},'accepted')">Accept Provider</button>` : ''}
        ${p.status !== 'rejected' ? `<button class="btn-reject-full" onclick="changeStatus(${p.user_id},'rejected')">Reject Provider</button>` : ''}
      </div>`;
  } catch(e) {
    document.getElementById('modalDetailBody').innerHTML = '<div class="empty-state">Failed to load provider details.</div>';
  }
}

function confirmStatusChange(id, name, newStatus) {
  const isDanger = newStatus === 'rejected';
  openConfirm(
    isDanger ? 'Reject Provider?' : 'Accept Provider?',
    `Are you sure you want to ${newStatus} ${name}?`,
    isDanger,
    () => changeStatus(id, newStatus)
  );
}

async function changeStatus(id, status) {
  try {
    await apiFetch('update_provider_status', { id, method:'PATCH', body:{ status, note:'' } });
    showToast(`Provider ${status} successfully`);
    loadProviders();
    loadOverview();
    closeModal('modalDetail');
  } catch(e) { showToast('Error: ' + e.message); }
}

function openNote(id, name, existingNote, existingStatus = 'pending') {
  currentNoteProviderId = id;
  currentNoteProviderStatus = existingStatus || 'pending';
  document.getElementById('noteModalTitle').textContent = `Add Note - ${name}`;
  document.getElementById('noteText').value = existingNote;
  openModal('modalNote');
}

async function saveNote() {
  const note = document.getElementById('noteText').value.trim();
  if (!note) return;
  try {
    await apiFetch('update_provider_status', { id:currentNoteProviderId, method:'PATCH', body:{ status:currentNoteProviderStatus, note } });
    showToast('Note saved!');
    closeModal('modalNote');
    loadProviders();
  } catch(e) { showToast('Error: ' + e.message); }
}

// ════════════════════════════════════════════════════════════
//  PLACES
// ════════════════════════════════════════════════════════════
async function loadPlaces() {
  document.getElementById('placesGrid').innerHTML = '<div class="spinner-wrap"><div class="spinner"></div></div>';
  const search = document.getElementById('placeSearch').value;
  const type   = document.getElementById('placeType').value;
  const status = document.getElementById('placeStatus').value;
  const qs = new URLSearchParams({ search, type, status }).toString();
  try {
    const list = await apiFetch('places&' + qs);
    if (!list.length) { document.getElementById('placesGrid').innerHTML = '<div class="empty-state">No rated places found</div>'; return; }
    document.getElementById('placesGrid').innerHTML = list.map(pl => {
      const chips = featureChips(pl);
      return `
        <div class="place-card">
          <div class="place-card-top">
            <div class="place-card-icon" style="font-size:22px;color:#4f46e5">${placeIcon(pl.type)}</div>
            <div class="place-card-name">${pl.name}</div>
            <div class="place-card-addr">${pl.address}</div>
            <div class="place-card-status">${badge(pl.status||'active')}</div>
          </div>
          <div class="place-card-body">
            <div class="place-features">
              ${chips.length ? chips.map(c=>`<span class="feature-chip">${c}</span>`).join('') : '<span style="font-size:12px;color:#94a3b8">No features recorded</span>'}
            </div>
            ${pl.comment ? `<div class="place-desc">${pl.comment}</div>` : ''}
          </div>
          <div class="place-card-footer">
            <button class="btn-ghost-purple" onclick="openPlaceView(${pl.place_id})">View</button>
            <button class="btn-ghost" onclick="openPlaceReviews(${pl.place_id})">Reviews</button>
            <button class="btn-ghost" onclick="openEditPlace(${pl.place_id})" style="color:#312e81">Edit</button>
            <select class="place-status-select" onchange="changePlaceStatus(${pl.place_id},this.value)">
              <option value="active"   ${pl.status==='active'  ?'selected':''}>Active</option>
              <option value="pending"  ${pl.status==='pending' ?'selected':''}>Pending</option>
              <option value="hidden"   ${pl.status==='hidden'  ?'selected':''}>Hidden</option>
            </select>
            <button class="btn-red" onclick="confirmDeletePlace(${pl.place_id})">Delete</button>
          </div>
        </div>`;
    }).join('');
  } catch(e) { console.error(e); }
}

// place data cache for edit
let placesCache = {};

async function openPlaceView(id) {
  openModal('modalPlaceView');
  try {
    const list = await apiFetch('places');
    const pl = list.find(x => x.place_id == id);
    if (!pl) return;
    const chips = featureChips(pl);
    document.getElementById('modalPlaceViewBody').innerHTML = `
      <div style="background:linear-gradient(135deg,#1e1b4b,#4f46e5);border-radius:14px;padding:20px;margin-bottom:18px">
        <div style="font-size:28px;margin-bottom:8px;color:#fff">${placeIcon(pl.type)}</div>
        <h3 style="color:#fff;font-size:18px;font-weight:900;margin:0">${pl.name}</h3>
        <p style="color:#a5b4fc;font-size:13px;margin:4px 0 8px">${pl.address}</p>
        ${badge(pl.status||'active')}
      </div>
      <div class="fields-grid" style="margin-bottom:14px">
        <div class="field-box"><div class="field-label">Type</div><div class="field-value">${pl.type||'-'}</div></div>
        <div class="field-box"><div class="field-label">Bookings</div><div class="field-value">${pl.booking_count||0}</div></div>
        <div class="field-box"><div class="field-label">Latitude</div><div class="field-value">${pl.latitude||'-'}</div></div>
        <div class="field-box"><div class="field-label">Longitude</div><div class="field-value">${pl.longitude||'-'}</div></div>
      </div>
      <div style="margin-bottom:14px">
        <div class="field-label" style="margin-bottom:8px">Accessibility Features</div>
        <div style="display:flex;flex-wrap:wrap;gap:7px">
          ${chips.length ? chips.map(c=>`<span style="background:#eef2ff;color:#4338ca;font-size:12px;padding:4px 12px;border-radius:99px;font-weight:700">${c}</span>`).join('') : '<span style="color:#94a3b8;font-size:13px">None recorded.</span>'}
        </div>
      </div>
      ${pl.comment ? `<div class="field-box wide"><div class="field-label">Description</div><div class="field-value" style="font-weight:400;color:#475569">${pl.comment}</div></div>` : ''}`;
  } catch(e) {}
}


let placeReviewsCache = [];

async function openPlaceReviews(placeId = null) {
  openModal('modalPlaceReviews');
  document.getElementById('placeReviewsBody').innerHTML = '<div class="spinner-wrap"><div class="spinner"></div></div>';

  try {
    const action = placeId ? ('place_reviews&place_id=' + encodeURIComponent(placeId)) : 'place_reviews';
    const res = await apiFetch(action);
    placeReviewsCache = Array.isArray(res.reviews) ? res.reviews : [];

    if (res.meta && res.meta.review_table_found === false) {
      document.getElementById('placeReviewsBody').innerHTML = `
        <div class="empty-state">
          No place review table found yet.<br>
          Run the SQL file I sent to create <strong>public.place_review</strong>.
        </div>`;
      return;
    }

    renderPlaceReviews();
  } catch(e) {
    document.getElementById('placeReviewsBody').innerHTML = `<div class="empty-state">Could not load place reviews.</div>`;
  }
}

function renderPlaceReviews() {
  const box = document.getElementById('placeReviewsBody');
  const searchEl = document.getElementById('placeReviewSearch');
  const q = searchEl ? searchEl.value.toLowerCase().trim() : '';

  let list = placeReviewsCache || [];

  if (q) {
    list = list.filter(r => [
      r.patient_name,
      r.patient_email,
      r.place_name,
      r.place_address,
      r.comment
    ].join(' ').toLowerCase().includes(q));
  }

  if (!list.length) {
    box.innerHTML = '<div class="empty-state">No place reviews found.</div>';
    return;
  }

  box.innerHTML = list.map(r => `
    <div class="history-row" style="align-items:flex-start;gap:12px">
      <div style="flex:1">
        <div class="history-id">${r.place_name || 'Unknown place'}</div>
        <div class="history-meta">${r.place_address || ''}</div>
        <div style="margin-top:8px;font-size:13px;color:#1e1b4b;font-weight:800">${r.patient_name || 'Patient'}</div>
        <div class="history-meta">${r.patient_email || ''}</div>
        ${r.comment ? `<div style="margin-top:8px;font-size:13px;color:#475569;line-height:1.5">${r.comment}</div>` : ''}
      </div>
      <div class="history-right" style="flex-direction:column;align-items:flex-end">
        <span style="color:#f59e0b;font-weight:900">${r.rating ? stars(r.rating) : '-'}</span>
        <span class="history-meta">${r.created_at ? String(r.created_at).slice(0,16) : ''}</span>
      </div>
    </div>
  `).join('');
}


function openAddPlace() {
  document.getElementById('placeEditId').value = '';
  document.getElementById('placeModalTitle').textContent = 'Add New Place';
  ['fName','fAddress','fLat','fLng','fPhoto','fComment'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('fType').value   = '';
  document.getElementById('fStatus').value = 'active';
  placeFeatures = { elevator:false, ramp:false, toilet:false, parking:false };
  ['elevator','ramp','toilet','parking'].forEach(k => document.getElementById('ft-'+k).classList.remove('on'));
  openModal('modalPlace');
}

async function openEditPlace(id) {
  try {
    const list = await apiFetch('places');
    const pl   = list.find(x => x.place_id == id);
    if (!pl) return;
    document.getElementById('placeEditId').value  = id;
    document.getElementById('placeModalTitle').textContent = 'Edit Place';
    document.getElementById('fName').value    = pl.name    || '';
    document.getElementById('fType').value    = pl.type    || '';
    document.getElementById('fAddress').value = pl.address || '';
    document.getElementById('fLat').value     = pl.latitude  || '';
    document.getElementById('fLng').value     = pl.longitude || '';
    document.getElementById('fComment').value = pl.comment  || '';
    document.getElementById('fPhoto').value   = pl.photo    || '';
    document.getElementById('fStatus').value  = pl.status   || 'active';
    placeFeatures = {
      elevator: pl.elevator == 't' || pl.elevator == true,
      ramp:     pl.ramp     == 't' || pl.ramp     == true,
      toilet:   pl.toilet   == 't' || pl.toilet   == true,
      parking:  pl.parking  == 't' || pl.parking  == true,
    };
    ['elevator','ramp','toilet','parking'].forEach(k => document.getElementById('ft-'+k).classList.toggle('on', placeFeatures[k]));
    openModal('modalPlace');
  } catch(e) {}
}

function toggleFeature(k) {
  placeFeatures[k] = !placeFeatures[k];
  document.getElementById('ft-'+k).classList.toggle('on', placeFeatures[k]);
}

async function savePlace() {
  const name    = document.getElementById('fName').value.trim();
  const type    = document.getElementById('fType').value;
  const address = document.getElementById('fAddress').value.trim();
  if (!name || !type || !address) { alert('Please fill in Name, Type, and Address.'); return; }
  const editId = document.getElementById('placeEditId').value;
  const body = {
    name, type, address,
    latitude:  document.getElementById('fLat').value     || null,
    longitude: document.getElementById('fLng').value     || null,
    comment:   document.getElementById('fComment').value,
    photo:     document.getElementById('fPhoto').value,
    status:    document.getElementById('fStatus').value,
    ...placeFeatures,
  };
  try {
    if (editId) { await apiFetch('edit_place',   { id:editId, method:'PUT',  body }); showToast('Place updated!'); }
    else        { await apiFetch('add_place',     {            method:'POST', body }); showToast('Place added!');   }
    closeModal('modalPlace');
    loadPlaces();
    loadOverview();
  } catch(e) { showToast('Error: ' + e.message); }
}

function confirmDeletePlace(id) {
  openConfirm('Delete Place?', 'This will permanently delete this place. Cannot be undone.', true, () => deletePlace(id));
}

async function deletePlace(id) {
  try {
    await apiFetch('delete_place', { id, method:'DELETE' });
    showToast('Place deleted.');
    loadPlaces();
    loadOverview();
  } catch(e) { showToast('Error: ' + e.message); }
}

async function changePlaceStatus(id, status) {
  try {
    await apiFetch('update_place_status', { id, method:'PATCH', body:{ status } });
    showToast('Status updated!');
    loadPlaces();
    loadOverview();
  } catch(e) { showToast('Error: ' + e.message); }
}

// ════════════════════════════════════════════════════════════
//  BOOKINGS
// ════════════════════════════════════════════════════════════
let bookDebounce;
function loadBookings() {
  clearTimeout(bookDebounce);
  bookDebounce = setTimeout(_loadBookings, 300);
}

async function _loadBookings() {
  document.getElementById('bookTable').innerHTML = '<tr><td colspan="9"><div class="spinner-wrap"><div class="spinner"></div></div></td></tr>';
  const search  = document.getElementById('bookSearch').value;
  const status  = document.getElementById('bookStatus').value;
  const service = document.getElementById('bookService').value;
  const qs = new URLSearchParams({ search, status, service_type:service }).toString();
  try {
    const list = await apiFetch('bookings&' + qs);
    if (!list.length) { document.getElementById('bookTable').innerHTML = '<tr><td colspan="9"><div class="empty-state">No bookings found</div></td></tr>'; return; }
    document.getElementById('bookTable').innerHTML = list.map(b => `
      <tr>
        <td class="td-muted" style="font-weight:700">#${b.booking_id}${b.is_urgent ? '<span class="urgent-tag">URGENT</span>' : ''}</td>
        <td><div class="td-name">${avatar(b.patient_name||'?',28)}<span class="td-name-text">${b.patient_name||'-'}</span></div></td>
        <td class="td-muted">${b.provider_name||'-'}</td>
        <td><span class="service-badge">${b.service_type||'-'}</span></td>
        <td class="td-muted" style="white-space:nowrap">${b.date||'-'}</td>
        <td class="td-bold">${b.payment_total ? 'EGP '+b.payment_total : '-'}</td>
        <td>${badge(b.payment_status)}</td>
        <td style="color:${b.rating?'#f59e0b':'#94a3b8'};font-weight:700">${stars(b.rating)}</td>
        <td>${badge(b.status)}</td>
      </tr>`).join('');
  } catch(e) { console.error(e); }
}

// ════════════════════════════════════════════════════════════
//  PATIENTS
// ════════════════════════════════════════════════════════════
let patDebounce;
function loadPatients() {
  clearTimeout(patDebounce);
  patDebounce = setTimeout(_loadPatients, 300);
}

async function _loadPatients() {
  document.getElementById('patTable').innerHTML = '<tr><td colspan="6"><div class="spinner-wrap"><div class="spinner"></div></div></td></tr>';
  const search = document.getElementById('patSearch').value;
  const qs = new URLSearchParams({ search }).toString();
  try {
    const list = await apiFetch('patients&' + qs);
    if (!list.length) {
      document.getElementById('patTable').innerHTML = '<tr><td colspan="6"><div class="empty-state">No patients found</div></td></tr>';
      return;
    }
    document.getElementById('patTable').innerHTML = list.map(p => `
      <tr>
        <td><div class="td-name">
          ${avatar(`${p.first_name} ${p.last_name}`, 34)}
          <div>
            <div class="td-name-text">${p.first_name} ${p.last_name}</div>
            <div class="td-name-email">${p.email}</div>
          </div>
        </div></td>
        <td class="td-muted">${p.phone || '-'}</td>
        <td class="td-muted" style="text-transform:capitalize">${p.gender || '-'}</td>
        <td class="td-muted">${p.disability || '-'}</td>
        <td class="td-muted" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${p.address || '-'}</td>
        <td class="td-bold" style="text-align:center">${p.total_bookings || 0}</td>
      </tr>`).join('');
  } catch(e) { console.error(e); }
}
</script>
</body>
</html>