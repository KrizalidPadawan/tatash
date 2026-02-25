# Optimized Queries

```sql
-- Lista paginada, columnas mínimas, filtro por tenant + índice (tenant_id, transaction_date)
SELECT id, category_id, type, amount, description, transaction_date, created_at
FROM transactions
WHERE tenant_id = :tenant_id
ORDER BY transaction_date DESC, id DESC
LIMIT :limit OFFSET :offset;
```

```sql
-- Resumen mensual, usa rango por fecha + tenant
SELECT type, SUM(amount) AS total
FROM transactions
WHERE tenant_id = :tenant_id
  AND transaction_date >= :start_date
  AND transaction_date < :end_date
GROUP BY type;
```

```sql
-- Dashboard últimos 30 días, evita SELECT *
SELECT
  SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS total_income,
  SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS total_expense,
  COUNT(*) AS tx_count
FROM transactions
WHERE tenant_id = :tenant_id
  AND transaction_date >= (CURDATE() - INTERVAL 30 DAY);
```
