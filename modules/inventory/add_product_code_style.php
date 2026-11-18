<style>
.product-code-section {
    border-left: 5px solid #0d47a1;
}

.product-code-options {
    display: grid;
    grid-template-columns: 1fr;
    gap: 11px;
    margin-bottom: 15px;
}

.product-code-option {
    display: block;
    padding: 14px;
    border: 2px solid #d8e1ec;
    border-radius: 12px;
    background: #ffffff;
    cursor: pointer;
}

.product-code-option:has(input:checked) {
    border-color: #0d47a1;
    background: #eaf2ff;
    box-shadow: 0 0 0 3px rgba(13, 71, 161, 0.10);
}

.product-code-option input {
    width: auto;
    height: auto;
    margin-right: 7px;
    accent-color: #087f3d;
}

.product-code-option strong {
    color: #111827;
}

.product-code-option small {
    display: block;
    margin: 6px 0 0 24px;
    color: #64748b;
}

.product-code-status {
    display: none;
    margin-top: 8px;
    padding: 9px 11px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 750;
}

.product-code-status.checking {
    display: block;
    background: #eaf2ff;
    color: #0d47a1;
}

.product-code-status.available {
    display: block;
    background: #e8f5e9;
    color: #087f3d;
}

.product-code-status.duplicate,
.product-code-status.invalid,
.product-code-status.failed {
    display: block;
    background: #ffebee;
    color: #c62828;
}

#productCodeInput.code-available {
    border-color: #087f3d;
    box-shadow: 0 0 0 4px rgba(8, 127, 61, 0.10);
}

#productCodeInput.code-invalid {
    border-color: #c62828;
    box-shadow: 0 0 0 4px rgba(198, 40, 40, 0.10);
}

@media (min-width: 600px) {
    .product-code-options {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>