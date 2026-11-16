<style>
:root {
    --stock-blue: #003F8F;
    --stock-green: #047857;
    --stock-black: #1D4ED8;
    --stock-red: #DC2626;
    --stock-orange: #C2410C;
    --stock-purple: #6D28D9;
    --stock-white: #ffffff;
    --stock-bg: #F4F7FB;
    --stock-border: #D7DEE8;
    --stock-muted: #6D28D9;
}

.stock-page {
    min-height: 100vh;
    padding: 14px;
    box-sizing: border-box;
    background: var(--stock-bg);
}

.stock-shell {
    max-width: 1100px;
    margin: 0 auto;
    overflow: hidden;
    background: var(--stock-white);
    border: 1px solid var(--stock-border);
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
}

.stock-color-bar {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    height: 7px;
}

.stock-color-bar span:nth-child(1) { background: var(--stock-blue); }
.stock-color-bar span:nth-child(2) { background: var(--stock-green); }
.stock-color-bar span:nth-child(3) { background: var(--stock-orange); }
.stock-color-bar span:nth-child(4) { background: var(--stock-red); }

.stock-heading {
    padding: 16px 18px;
    background: var(--stock-blue);
    color: #ffffff;
}

.stock-heading h2 {
    margin: 0 0 4px;
    font-size: 26px;
    letter-spacing: 0;
    color: #ffffff;
}

.stock-heading p {
    margin: 0;
    color: #ffffff;
    font-size: 16px;
    font-weight: 700;
}

.stock-toolbar {
    display: grid;
    grid-template-columns: 1fr;
    gap: 11px;
    padding: 16px;
    border-bottom: 1px solid var(--stock-border);
}

.stock-search {
    width: 100%;
    min-width: 0;
    height: 44px;
    padding: 0 14px;
    box-sizing: border-box;
    border: 1px solid #cccccc;
    border-radius: 8px;
    font-size: 18px;
    font-weight: 800;
    color: #0F3D8C;
    background: #ffffff;
    outline: none;
}

.stock-search:focus {
    border-color: var(--stock-blue);
    box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.12);
}

.stock-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.stock-action {
    padding: 10px 14px;
    border-radius: 8px;
    color: #ffffff;
    text-decoration: none;
    font-size: 16px;
    font-weight: 800;
}

.stock-action.blue { background: var(--stock-blue); }
.stock-action.green { background: var(--stock-green); }
.stock-action.black { background: var(--stock-orange); }
.stock-action.red { background: var(--stock-red); }

.stock-table-wrap {
    padding: 16px;
    overflow-x: auto;
}

.stock-table {
    width: 100%;
    border-collapse: collapse;
}

.stock-table th {
    padding: 12px;
    background: var(--stock-blue);
    color: #ffffff;
    text-align: left;
    font-size: 16px;
    font-weight: 800;
}

.stock-table td {
    padding: 12px;
    border-bottom: 1px solid var(--stock-border);
    vertical-align: middle;
    font-size: 17px;
    font-weight: 800;
    background: #ffffff;
}

.stock-product {
    display: flex;
    align-items: center;
    gap: 11px;
}

.stock-qr {
    width: 48px;
    height: 48px;
    padding: 3px;
    border: 1px solid var(--stock-border);
    border-radius: 7px;
    background: white;
}

.stock-name {
    color: #1D4ED8;
    font-size: 18px;
    font-weight: 800;
    line-height: 1.35;
}

.stock-code {
    display: inline-block;
    margin-top: 4px;
    color: var(--stock-purple);
    font-size: 16px;
    font-weight: 800;
}

.stock-meta {
    display: block;
    margin-top: 4px;
    color: var(--stock-muted);
    font-size: 14px;
}

.stock-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    background: #ECFDF5;
    color: var(--stock-green);
    font-size: 18px;
    font-weight: 800;
}

.stock-badge.low {
    background: #FEF2F2;
    color: var(--stock-red);
}

.stock-badge.negative {
    background: #FFF7ED;
    color: var(--stock-orange);
}

.stock-price {
    color: var(--stock-green);
    font-weight: 900;
}

.stock-kpd {
    display: flex;
    flex-direction: column;
    gap: 3px;
    font-size: 15px;
    font-weight: 800;
    color: #0f172a;
}
.stock-kpd span:nth-child(1) { color: #047857; }
.stock-kpd span:nth-child(2) { color: #1D4ED8; }
.stock-kpd span:nth-child(3) { color: #C2410C; }
.stock-status {
    display: inline-block;
    margin-left: 6px;
    padding: 1px 7px;
    border-radius: 999px;
    background: #fff7d6;
    color: #7a5b00;
    font-size: 14px;
    font-style: normal;
    font-weight: 800;
    text-transform: uppercase;
}
.stock-status.old {
    background: #ffedd5;
    color: #9a3412;
}
.stock-status.db {
    background: #dcfce7;
    color: #166534;
    text-transform: none;
}
.stock-table tbody tr.is-db-row > td {
    background: #f0fdf4;
}
.stock-match-info {
    margin: 0 16px 8px;
    font-size: 14px;
    font-weight: 700;
    color: #475569;
}
.stock-match-info.is-error {
    color: var(--stock-red);
}

.stock-edit {
    display: inline-block;
    padding: 8px 12px;
    border-radius: 8px;
    background: var(--stock-blue);
    color: #ffffff;
    text-decoration: none;
    font-size: 16px;
    font-weight: 800;
}

.stock-message {
    margin: 16px;
    padding: 22px;
    border-radius: 12px;
    text-align: center;
}

.stock-message.empty {
    background: #F4F7FB;
    color: #1D4ED8;
    font-size: 18px;
    font-weight: 800;
}

.stock-message.error {
    background: #ffebee;
    color: var(--stock-red);
    border: 1px solid #ffcdd2;
}

@media (min-width: 760px) {
    .stock-toolbar {
        grid-template-columns: minmax(240px, 1fr) auto;
        align-items: center;
    }
}

@media (max-width: 650px) {
    .stock-page {
        padding: 8px;
    }

    .stock-table-wrap {
        padding: 10px;
        overflow: visible;
    }

    .stock-table,
    .stock-table tbody,
    .stock-table tr,
    .stock-table td {
        display: block;
        width: 100%;
        box-sizing: border-box;
    }

    .stock-table thead {
        display: none;
    }

    .stock-table tr {
        margin-bottom: 12px;
        padding: 12px;
        border: 1px solid var(--stock-border);
        border-left: 5px solid var(--stock-blue);
        border-radius: 13px;
        background: white;
    }

    .stock-table td {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 9px 0;
        border-bottom: 1px dashed #e2e8f0;
        text-align: right;
    }

    .stock-table td:first-child {
        display: block;
        text-align: left;
    }

    .stock-table td:last-child {
        border-bottom: 0;
    }

    .stock-table td::before {
        content: attr(data-label);
        color: var(--stock-black);
        font-weight: 800;
        text-align: left;
    }

    .stock-table td:first-child::before {
        display: none;
    }
}
</style>