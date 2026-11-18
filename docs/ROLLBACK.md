# Rollback

1. Enable maintenance mode.
2. Restore the database backup taken immediately before cutover.
3. Restore the previous `public_html` directory from `private_aponshop/rollback/`.
4. Confirm the previous `.env` still points at the restored database.
5. Open `/login.php` and verify one known invoice, one customer balance, and one stock item.
6. Disable maintenance mode only after those three checks match the pre-cutover backup.

Do not run `003_data_repairs.sql` in reverse automatically. Supplier 59 is set `active` and product 708 is restored as a catalog stub; undo those by restoring the full pre-cutover database backup, not by hand-editing rows.
