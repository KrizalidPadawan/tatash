SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Credenciales demo:
-- tenant: demo
-- Administrador Demo: admin@demo.com / Admin123!
-- Ingresador Demo: ingresador@demo.com / Ingresador123!
-- Visualizador Demo: visualizador@demo.com / Visualizador123!

INSERT INTO tenants (name, slug, plan, active)
VALUES ('Tenant Demo', 'demo', 'basic', 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  plan = VALUES(plan),
  active = VALUES(active);

INSERT INTO users (tenant_id, role_id, full_name, email, password_hash, active)
SELECT
  t.id,
  r.id,
  'Administrador Demo',
  'admin@demo.com',
  '$2y$10$/50jawGe1e0mXTU08isUiOZvU1XRVIIdyELDIffp9cvgBPsnWIjRy',
  1
FROM tenants t
JOIN roles r ON r.code = 'ADMIN'
WHERE t.slug = 'demo'
ON DUPLICATE KEY UPDATE
  role_id = VALUES(role_id),
  full_name = VALUES(full_name),
  password_hash = VALUES(password_hash),
  active = VALUES(active);

INSERT INTO users (tenant_id, role_id, full_name, email, password_hash, active)
SELECT
  t.id,
  r.id,
  'Ingresador Demo',
  'ingresador@demo.com',
  '$2y$10$1tzii7YbAHvatRrj3frMf.d5xK6GEyfe7PdVsTXIRNBcPT3i8sL8u',
  1
FROM tenants t
JOIN roles r ON r.code = 'INGRESADOR'
WHERE t.slug = 'demo'
ON DUPLICATE KEY UPDATE
  role_id = VALUES(role_id),
  full_name = VALUES(full_name),
  password_hash = VALUES(password_hash),
  active = VALUES(active);

INSERT INTO users (tenant_id, role_id, full_name, email, password_hash, active)
SELECT
  t.id,
  r.id,
  'Visualizador Demo',
  'visualizador@demo.com',
  '$2y$10$xLqFDSIZ8V8ksQh6M3MIeuY.SPJoCECaIA5Hqi3T6T5xbTyXwyBRm',
  1
FROM tenants t
JOIN roles r ON r.code = 'VISUALIZADOR'
WHERE t.slug = 'demo'
ON DUPLICATE KEY UPDATE
  role_id = VALUES(role_id),
  full_name = VALUES(full_name),
  password_hash = VALUES(password_hash),
  active = VALUES(active);
