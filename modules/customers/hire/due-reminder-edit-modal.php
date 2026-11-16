<!-- hire due reminder: one-page editor, sales-desk navy/green -->
<div class="hire-due-overlay" id="hireEditOverlay" onclick="if(event.target===this) hireCloseEdit()">
    <div class="hire-edit-card hire-edit-desk">
        <header class="hire-edit-bar">
            <div>
                <strong>ভাড়া বকেয়া — এডিট ও টাকা নিন</strong>
                <small id="hireEditName"></small>
            </div>
            <button type="button" class="hire-edit-x" onclick="hireCloseEdit()" aria-label="বন্ধ">&times;</button>
        </header>
        <p class="hire-edit-sub" id="hireEditItem"></p>
        <div class="hire-edit-grid">
            <label>ভাড়া বকেয়া (মোট)
                <input type="text" id="hireEditTotalDue" readonly>
            </label>
            <label>বকেয়া দিন
                <input type="text" id="hireEditUnpaidDays" readonly>
            </label>
            <label>মাস
                <select id="hireEditMonths">
                    <?php for ($m = 0; $m <= 36; $m++): ?>
                    <option value="<?php echo $m; ?>"><?php echo $m; ?> মাস</option>
                    <?php endfor; ?>
                </select>
            </label>
            <label>দিন
                <select id="hireEditDays">
                    <?php for ($d = 0; $d <= 30; $d++): ?>
                    <option value="<?php echo $d; ?>"><?php echo $d; ?> দিন</option>
                    <?php endfor; ?>
                </select>
            </label>
            <label>দৈনিক ভাড়া (৳)
                <input type="number" step="0.01" min="0.01" id="hireEditRate">
            </label>
            <label>প্রত্যাশিত টাকা
                <input type="number" step="0.01" min="0" id="hireEditExpected" oninput="hireRecalcRemaining()">
            </label>
            <label>অবশিষ্ট বকেয়া
                <input type="text" id="hireEditRemaining" readonly>
            </label>
            <label>পরবর্তী তারিখ *
                <input type="date" id="hireEditDueDate">
            </label>
            <label class="hire-edit-full">নোট
                <input type="text" id="hireEditNote" placeholder="যেমন: আগামী শুক্রবার টাকা নেবেন">
            </label>
            <div class="hire-pay-box">
                <strong>টাকা নিন</strong>
                <div class="hire-edit-grid hire-pay-grid">
                    <label>পরিমাণ
                        <input type="number" step="0.01" min="0.01" id="hirePayAmount">
                    </label>
                    <label>পেমেন্টের তারিখ
                        <input type="date" id="hirePayDate">
                    </label>
                    <label>মাধ্যম
                        <select id="hirePayMethod">
                            <option>CASH</option>
                            <option>BANK</option>
                            <option>MOBILE</option>
                            <option>OTHER</option>
                        </select>
                    </label>
                    <label>অ্যাকাউন্ট
                        <select id="hirePayAccount">
                            <option value="0">—</option>
                        </select>
                    </label>
                </div>
            </div>
        </div>
        <footer class="hire-edit-actions">
            <button type="button" class="hire-save-btn" id="hireSaveBtn">আপডেট করুন</button>
            <button type="button" class="hire-pay-btn" id="hirePayBtn">টাকা নিন</button>
            <div class="hire-edit-msg" id="hireEditMsg"></div>
        </footer>
    </div>
</div>
