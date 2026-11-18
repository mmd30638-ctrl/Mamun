<style>
:root {
    --diag-blue: var(--brand-navy);
    --diag-green: var(--brand-success);
    --diag-black: var(--brand-black);
    --diag-red: var(--brand-danger);
    --diag-white: var(--brand-white);
    --diag-bg: var(--brand-bg-soft);
}

.diag-page {
    min-height: 100vh;
    padding: 14px;
    box-sizing: border-box;
    background: var(--diag-bg);
}

.diag-shell {
    max-width: 1050px;
    margin: 0 auto;
    overflow: hidden;
    border-radius: 20px;
    background: var(--diag-white);
    box-shadow: 0 15px 40px rgba(15, 23, 42, 0.14);
}

.diag-colors {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    height: 7px;
}

.diag-colors span:nth-child(1) { background: var(--diag-blue); }
.diag-colors span:nth-child(2) { background: var(--diag-green); }
.diag-colors span:nth-child(3) { background: var(--diag-black); }
.diag-colors span:nth-child(4) { background: var(--diag-red); }

.diag-heading {
    padding: 22px;
    background: linear-gradient(135deg, #071a34, var(--diag-blue));
    color: white;
}

.diag-heading h2 {
    margin: 0 0 6px;
}

.diag-heading p {
    margin: 0;
    color: #dbeafe;
    font-size: 13px;
}

.diag-content {
    padding: 18px;
}

.diag-checks {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.diag-check {
    padding: 13px;
    border-radius: 11px;
    border-left: 5px solid var(--diag-green);
    background: #f0fdf4;
}

.diag-check.failed {
    border-left-color: var(--diag-red);
    background: #fff1f2;
}

.diag-check strong {
    display: block;
    color: var(--diag-black);
}

.diag-check small {
    display: block;
    margin-top: 5px;
    color: #64748b;
    word-break: break-word;
}

.diag-section-title {
    margin: 24px 0 12px;
    padding-bottom: 8px;
    border-bottom: 2px solid var(--diag-blue);
    color: var(--diag-black);
}

.diag-error {
    margin-bottom: 11px;
    padding: 13px;
    border: 1px solid #fecaca;
    border-left: 5px solid var(--diag-red);
    border-radius: 10px;
    background: #fff7f7;
}

.diag-error-top {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    color: var(--diag-red);
    font-weight: 800;
    font-size: 12px;
}

.diag-error-message {
    margin: 8px 0;
    color: var(--diag-black);
    word-break: break-word;
}

.diag-error-meta {
    color: #64748b;
    font-size: 11px;
    word-break: break-all;
}

.diag-empty {
    padding: 20px;
    border-radius: 10px;
    background: #f0fdf4;
    color: var(--diag-green);
    text-align: center;
}

.diag-back {
    display: inline-block;
    margin-top: 18px;
    padding: 11px 16px;
    border-radius: 9px;
    background: var(--diag-black);
    color: white;
    text-decoration: none;
}

@media (min-width: 700px) {
    .diag-checks {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>