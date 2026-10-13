<?php
declare(strict_types=1);
const APP_NAME='Syed Professional Services CRM';
const APP_SHORT_NAME='SPS CRM';
const APP_VERSION='1.0.0-PHASE-6';
const APP_TIMEZONE='Asia/Karachi';
const DB_HOST='127.0.0.1';
const DB_PORT='3306';
const DB_NAME='gpt';
const DB_USER='root';
const DB_PASS='';
const SESSION_NAME='gpt_session';
const DEFAULT_ADMIN_USERNAME='admin';
const SESSION_COOKIE_HTTPONLY=true;
const SESSION_COOKIE_SAMESITE='Lax';
date_default_timezone_set(APP_TIMEZONE);
ini_set('display_errors','0'); ini_set('log_errors','1');
if(session_status()===PHP_SESSION_NONE){
    session_name(SESSION_NAME);
    if(PHP_VERSION_ID>=70300) session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>SESSION_COOKIE_SAMESITE]);
    session_start();
}
?>