<?php
/**
 * সিরিয়াল ৪১: ড্যাশবোর্ড ডাটা প্রসেসর (Clean Version)
 * কাজ: দৃশ্যমান বক্সগুলো বাতিল করা।
 */

// মাসের লাভ হিসাব
$mon_start = date('Y-m-01');
$profit_data = $db->query("SELECT SUM((si.sale_price - si.purchase_price) * si.qty) as gross_profit FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE DATE(s.sale_date) >= '$mon_start'")->fetch_assoc();
$monthly_profit = $profit_data['gross_profit'] ?? 0;

// আজকের নগদ জমা হিসাব
$today = date('Y-m-d');
$today_cr = $db->query("SELECT SUM(amount) as total FROM account_ledger WHERE type='credit' AND DATE(created_at)='$today'")->fetch_assoc()['total'] ?? 0;
?>