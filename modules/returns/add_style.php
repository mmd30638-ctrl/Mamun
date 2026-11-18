<style>
:root{
    --rtn-green: var(--brand-success);
    --rtn-green-light: var(--brand-success-soft);
    --rtn-red: var(--brand-danger);
    --rtn-red-light: var(--brand-danger-soft);
    --rtn-black: var(--brand-black);
    --rtn-white: var(--brand-white);
    --rtn-gray: var(--brand-bg-soft);
    --rtn-border: var(--brand-border);
}
.rtn-wrap{ padding:12px; max-width:600px; margin:0 auto; }
.rtn-card{
    background:var(--rtn-white);
    border-radius:14px;
    box-shadow:0 2px 10px rgba(0,0,0,0.08);
    overflow:hidden;
    border:1px solid var(--rtn-border);
}
.rtn-header{
    background:var(--rtn-green);
    color:var(--rtn-white);
    padding:16px 18px;
    display:flex;
    align-items:center;
    gap:10px;
}
.rtn-header h3{ margin:0; font-size:18px; font-weight:700; }
.rtn-header-icon{ font-size:22px; }

.rtn-body{ padding:16px; }

/* Mode tabs */
.rtn-toggle{
    display:flex;
    gap:6px;
    margin-bottom:16px;
    background:var(--rtn-gray);
    padding:5px;
    border-radius:10px;
}
.rtn-toggle label{
    flex:1;
    text-align:center;
    padding:10px 4px;
    border-radius:8px;
    font-size:13px;
    font-weight:600;
    color:#555;
    cursor:pointer;
    transition:all .15s;
    border:2px solid transparent;
}
.rtn-toggle input{ display:none; }
.rtn-toggle label.rtn-tab-active{
    background:var(--rtn-green);
    color:var(--rtn-white);
    border-color:var(--rtn-green);
}

.rtn-mode-panel{
    background:var(--rtn-gray);
    border-radius:10px;
    padding:14px;
    margin-bottom:14px;
}

.rtn-group{ margin-bottom:14px; }
.rtn-group:last-child{ margin-bottom:0; }
.rtn-group label{
    display:block;
    font-size:13px;
    font-weight:600;
    color:var(--rtn-black);
    margin-bottom:6px;
}
.rtn-group input[type=text],
.rtn-group input[type=number],
.rtn-group select{
    width:100%;
    padding:11px 12px;
    border:1px solid var(--rtn-border);
    border-radius:8px;
    font-size:15px;
    background:var(--rtn-white);
    box-sizing:border-box;
}
.rtn-group input:focus,
.rtn-group select:focus{
    outline:none;
    border-color:var(--rtn-green);
}

.rtn-load-btn{
    margin-top:8px;
    width:100%;
    padding:11px;
    background:var(--rtn-black);
    color:var(--rtn-white);
    border:none;
    border-radius:8px;
    font-size:14px;
    font-weight:600;
}
.rtn-load-btn:active{ opacity:.8; }

.rtn-items-box{ margin-top:10px; }
.rtn-item-row{
    display:flex;
    align-items:center;
    gap:8px;
    background:var(--rtn-white);
    border:1px solid var(--rtn-border);
    border-radius:8px;
    padding:10px;
    margin-bottom:8px;
}
.rtn-item-row input[type=number]{
    width:90px;
    padding:8px;
    border:1px solid var(--rtn-border);
    border-radius:6px;
}

.rtn-diff-box{
    text-align:center;
    font-weight:700;
    font-size:15px;
    padding:12px;
    border-radius:8px;
    margin-top:6px;
}
.rtn-diff-zero{ background:var(--rtn-gray); color:#555; }
.rtn-diff-positive{ background:var(--rtn-green-light); color:var(--rtn-green); }
.rtn-diff-negative{ background:var(--rtn-red-light); color:var(--rtn-red); }

.rtn-acc-hint{
    font-size:12px;
    font-weight:700;
    margin-top:6px;
}
.rtn-acc-hint.rtn-neg{ color:var(--rtn-red); }
.rtn-acc-hint.rtn-pos{ color:var(--rtn-green); }

.rtn-hide{ display:none !important; }

.rtn-submit{
    width:100%;
    padding:14px;
    background:var(--rtn-green);
    color:var(--rtn-white);
    border:none;
    border-radius:10px;
    font-size:16px;
    font-weight:700;
    margin-top:6px;
}
.rtn-submit:active{ opacity:.85; }

.rtn-back{
    display:block;
    text-align:center;
    padding:14px;
    background:var(--rtn-gray);
    color:var(--rtn-black);
    text-decoration:none;
    font-size:14px;
    font-weight:600;
    border-top:1px solid var(--rtn-border);
}
</style>