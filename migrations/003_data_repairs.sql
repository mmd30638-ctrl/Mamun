-- APONSHOP 10.98.165
-- Owner decision 2026-09-24: every pending record is active.
-- Supplier 59 (ফারিয়া এন্টারপ্রাইজ) stays active.
-- Product 708 has store_stock but no catalog row in the 24 Sep backup,
-- balances JSON, or split product.sql. Restore keeps the -41 qty and
-- inserts a clearly labeled catalog stub. Prices and SKU are not guessed.

SET @supplier_59_status = 'active';   -- owner: all suppliers are active
SET @product_708_action = 'restored'; -- owner: keep the product, do not delete

-- Review before changing anything.
SELECT `id`, `name`, `status`
FROM `suppliers`
WHERE `id` = 59
   OR `status` IS NULL
   OR `status` = '';

SELECT ss.`store_id`, ss.`product_id`, ss.`qty`,
       CASE WHEN p.`id` IS NULL THEN 'ORPHAN' ELSE 'PRODUCT_EXISTS' END AS `state`,
       p.`name` AS `product_name`,
       p.`status` AS `product_status`
FROM `store_stock` AS ss
LEFT JOIN `products` AS p ON p.`id` = ss.`product_id`
WHERE ss.`store_id` = 1 AND ss.`product_id` = 708;

-- Empty supplier status becomes active. Already-set inactive rows are left alone.
UPDATE `suppliers`
SET `status` = 'active'
WHERE (`status` IS NULL OR `status` = '')
  AND @supplier_59_status = 'active';

-- Restore the missing catalog row so the kept stock is no longer an orphan.
-- Name is a recovery marker only. Rename it in inventory after cutover.
INSERT INTO `products` (
  `id`, `name`, `sku`, `purchase_price`, `wholesale_price`, `dealer_price`,
  `sale_price`, `stock`, `min_stock`, `status`
)
SELECT
  708,
  'পণ্য 708 — স্টক থেকে পুনরুদ্ধার',
  NULL,
  0, 0, 0, 0,
  ss.`qty`,
  0,
  'active'
FROM `store_stock` AS ss
LEFT JOIN `products` AS p ON p.`id` = 708
WHERE ss.`store_id` = 1
  AND ss.`product_id` = 708
  AND p.`id` IS NULL
  AND @product_708_action = 'restored';

UPDATE `products` AS p
INNER JOIN `store_stock` AS ss
        ON ss.`product_id` = p.`id`
       AND ss.`store_id` = 1
SET p.`status` = 'active',
    p.`stock` = ss.`qty`
WHERE p.`id` = 708
  AND @product_708_action = 'restored';

-- Delete path remains available only if the owner later sets delete_orphan.
DELETE ss
FROM `store_stock` AS ss
LEFT JOIN `products` AS p ON p.`id` = ss.`product_id`
WHERE ss.`store_id` = 1
  AND ss.`product_id` = 708
  AND p.`id` IS NULL
  AND @product_708_action = 'delete_orphan';

INSERT INTO `ame_migrations` (`version`, `description`)
VALUES (
  '003_data_repairs_10.98.165',
  'Owner: supplier 59 active; product 708 restored from store_stock'
)
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

SELECT 'supplier_59_status' AS `check_name`,
       CASE
         WHEN EXISTS (
           SELECT 1 FROM `suppliers`
           WHERE `id` = 59 AND `status` IN ('active', 'inactive')
         ) THEN 'OK'
         ELSE 'REVIEW_REQUIRED'
       END AS `result`
UNION ALL
SELECT 'empty_supplier_status',
       CASE
         WHEN NOT EXISTS (
           SELECT 1 FROM `suppliers`
           WHERE `status` IS NULL OR `status` = ''
         ) THEN 'OK'
         ELSE 'REVIEW_REQUIRED'
       END
UNION ALL
SELECT 'product_708_stock',
       CASE
         WHEN EXISTS (
           SELECT 1 FROM `products` WHERE `id` = 708 AND `status` = 'active'
         )
         AND EXISTS (
           SELECT 1 FROM `store_stock`
           WHERE `store_id` = 1 AND `product_id` = 708
         )
         AND NOT EXISTS (
           SELECT 1
           FROM `store_stock` AS ss
           LEFT JOIN `products` AS p ON p.`id` = ss.`product_id`
           WHERE ss.`store_id` = 1
             AND ss.`product_id` = 708
             AND p.`id` IS NULL
         ) THEN 'OK'
         ELSE 'REVIEW_REQUIRED'
       END;
