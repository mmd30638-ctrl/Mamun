<?php
/**
 * ⚠️ এই ফাইলটা এখন আর সরাসরি ব্যবহার হয় না — এডিট ফিচার এখন সরাসরি
 * add.php-এর একই প্রফেশনাল ক্রয়-স্ক্রিনে করা হয় (?edit_memo=মেমো নম্বর
 * দিয়ে), যাতে নতুন ক্রয় করার পুরো অভিজ্ঞতা (পণ্য সার্চ, দাম, খরচ,
 * পেমেন্ট — সবকিছু) দিয়েই পুরনো মেমো এডিট করা যায়।
 *
 * পুরনো কোনো বুকমার্ক/লিংক (edit.php?memo=...) যেন ভেঙে না যায়, তাই এই
 * ফাইলটা শুধু নতুন জায়গায় পাঠিয়ে দেয়।
 */
require_once '../../includes/config.php';
require_once '../../includes/purchase_functions.php';

$memo_no = ame_purchase_normalize_memo($_GET['memo'] ?? '');
header('Location: add.php' . ($memo_no !== '' ? '?edit_memo=' . rawurlencode($memo_no) : ''));
exit();
