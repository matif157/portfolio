<?php use App\Support\View; ?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=View::e($title??'SPS CRM')?></title><link rel="stylesheet" href="/assets/css/app.css"></head><body>
<div class="shell"><aside class="sidebar"><div class="brand">SPS <small>CRM</small></div>
<nav>
<a href="/">Dashboard</a><a href="/clients">Clients</a><a href="/leads">Leads</a><a href="/services">Services</a><a href="/projects">Projects</a>
<a href="/quotations">Quotations</a><a href="/invoices">Invoices</a><a href="/tasks">Tasks</a><a href="/calendar">Calendar</a>
<a href="/documents">Documents</a><a href="/communications">Communications</a><a href="/cases">Cases</a><a href="/approvals">Approvals</a><a href="/ai">AI Operations</a><a href="/reports">Reports</a><a href="/admin/users">Users & Roles</a><a href="/activities">Activity & Audit</a><a href="/notifications">Notifications</a>
</nav><form method="post" action="/logout" class="logout"><?=App\Support\Csrf::input()?><button>Sign out</button></form></aside>
<main class="main"><header class="top"><div class="crumb">SPS CRM</div><div class="user"><?=View::e(App\Support\Auth::user()['full_name']??'')?></div></header>
