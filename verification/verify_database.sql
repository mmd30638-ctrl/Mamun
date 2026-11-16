-- APONSHOP 10.98.165 production-readiness checks.
-- Every result named *_errors must be 0 before cutover.

SELECT 'schema_migration' AS check_name,
       CASE WHEN EXISTS (
         SELECT 1 FROM ame_migrations
         WHERE version='001_schema_repair_10.98.165'
       ) THEN 0 ELSE 1 END AS error_count;

SELECT 'data_repair_migration' AS check_name,
       CASE WHEN EXISTS (
         SELECT 1 FROM ame_migrations
         WHERE version='003_data_repairs_10.98.165'
       ) THEN 0 ELSE 1 END AS error_count;

SELECT 'missing_required_columns' AS check_name, COUNT(*) AS error_count
FROM (
  SELECT 'accounts' t, 'created_at' c UNION ALL
  SELECT 'bill_collections','created_at' UNION ALL
  SELECT 'bill_partners','status' UNION ALL
  SELECT 'bill_partners','created_at' UNION ALL
  SELECT 'brands','created_at' UNION ALL
  SELECT 'categories','created_at' UNION ALL
  SELECT 'contact_groups','created_at' UNION ALL
  SELECT 'customers','updated_at' UNION ALL
  SELECT 'customer_areas','created_at' UNION ALL
  SELECT 'customer_call_log','note' UNION ALL
  SELECT 'customer_groups','created_at' UNION ALL
  SELECT 'employees','emp_id' UNION ALL
  SELECT 'employees','department' UNION ALL
  SELECT 'employees','created_at' UNION ALL
  SELECT 'engineers','created_at' UNION ALL
  SELECT 'personal_categories','created_at' UNION ALL
  SELECT 'products','supplier_id' UNION ALL
  SELECT 'product_units','created_at' UNION ALL
  SELECT 'salaries','created_at' UNION ALL
  SELECT 'sale_returns','created_at' UNION ALL
  SELECT 'sale_warranties','created_at' UNION ALL
  SELECT 'settings','created_at' UNION ALL
  SELECT 'suppliers','created_at' UNION ALL
  SELECT 'sale_items','legacy_key'
) required
LEFT JOIN information_schema.columns actual
  ON actual.table_schema = DATABASE()
 AND actual.table_name = required.t
 AND actual.column_name = required.c
WHERE actual.column_name IS NULL;

SELECT 'invalid_supplier_status_errors' AS check_name, COUNT(*) AS error_count
FROM suppliers
WHERE status IS NULL OR status NOT IN ('active','inactive');

SELECT 'orphan_store_stock_errors' AS check_name, COUNT(*) AS error_count
FROM store_stock ss
LEFT JOIN products p ON p.id=ss.product_id
LEFT JOIN stores st ON st.id=ss.store_id
WHERE p.id IS NULL OR st.id IS NULL;

SELECT 'orphan_sale_item_sale_errors' AS check_name, COUNT(*) AS error_count
FROM sale_items i
LEFT JOIN sales s ON s.id=i.sale_id
WHERE s.id IS NULL;

SELECT 'orphan_sale_item_product_errors' AS check_name, COUNT(*) AS error_count
FROM sale_items i
LEFT JOIN products p ON p.id=i.product_id
WHERE i.product_id IS NOT NULL AND p.id IS NULL;

SELECT 'orphan_purchase_supplier_errors' AS check_name, COUNT(*) AS error_count
FROM purchases x
LEFT JOIN suppliers s ON s.id=x.supplier_id
WHERE x.supplier_id IS NOT NULL AND x.supplier_id<>0 AND s.id IS NULL;

SELECT 'orphan_purchase_product_errors' AS check_name, COUNT(*) AS error_count
FROM purchases x
LEFT JOIN products p ON p.id=x.product_id
WHERE x.product_id IS NOT NULL AND x.product_id<>0 AND p.id IS NULL;

SELECT 'duplicate_invoice_errors' AS check_name, COUNT(*) AS error_count
FROM (
  SELECT invoice_number
  FROM sales
  WHERE invoice_number IS NOT NULL AND invoice_number<>''
  GROUP BY invoice_number
  HAVING COUNT(*)>1
) duplicates;

SELECT 'active_admin_errors' AS check_name,
       CASE WHEN COUNT(*)>0 THEN 0 ELSE 1 END AS error_count
FROM users
WHERE status=1 AND LOWER(role)='admin';

-- Informational counts. Historical item coverage must be reconciled against
-- the private migration report, not judged only by this summary.
SELECT
  (SELECT COUNT(*) FROM sales) AS sales,
  (SELECT COUNT(*) FROM sale_items) AS sale_items,
  (SELECT COUNT(*) FROM sale_items WHERE legacy_key LIKE 'mae-sql:%') AS recovered_legacy_items,
  (SELECT COUNT(*) FROM sales s WHERE NOT EXISTS (
      SELECT 1 FROM sale_items i WHERE i.sale_id=s.id
   )) AS sales_without_items,
  (SELECT COUNT(*) FROM customers) AS customers,
  (SELECT COUNT(*) FROM products) AS products,
  (SELECT COUNT(*) FROM purchases) AS purchases,
  (SELECT COUNT(*) FROM suppliers) AS suppliers,
  (SELECT COUNT(*) FROM employees) AS employees;
