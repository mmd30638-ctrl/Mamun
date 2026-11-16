-- Record and index the actual project account used for Partner Accounting v2
-- joint expenses. Additive and safe to re-run on supported MySQL/MariaDB.

SET @pv2_account_column_exists = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'partner_v2_expenses'
    AND column_name = 'account_id'
);
SET @pv2_account_column_sql = IF(
  @pv2_account_column_exists = 0,
  'ALTER TABLE partner_v2_expenses ADD COLUMN account_id INT NULL AFTER payer_partner_id',
  'SELECT 1'
);
PREPARE pv2_account_column_stmt FROM @pv2_account_column_sql;
EXECUTE pv2_account_column_stmt;
DEALLOCATE PREPARE pv2_account_column_stmt;

SET @pv2_account_index_exists = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'partner_v2_expenses'
    AND index_name = 'idx_pv2_expense_account'
);
SET @pv2_account_index_sql = IF(
  @pv2_account_index_exists = 0,
  'ALTER TABLE partner_v2_expenses ADD INDEX idx_pv2_expense_account (account_id)',
  'SELECT 1'
);
PREPARE pv2_account_index_stmt FROM @pv2_account_index_sql;
EXECUTE pv2_account_index_stmt;
DEALLOCATE PREPARE pv2_account_index_stmt;
