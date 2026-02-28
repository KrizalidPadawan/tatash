SET NAMES utf8mb4;
SET time_zone = '+00:00';

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
  '$2y$10$HJ/94QvwleoN.Cl5nOzsVeD79kHM9oMTyqwkKSTpHgWtrHh7Rdupe',
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
  '$2y$10$x0rHPTjbLCYajtM283FJA.FbTZm3CDUfDPcSBd8ioy9Ml7MRNn6Gm',
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
  '$2y$10$k.GLGNHIi8zI/Vy1vUeee.PWdY4oY0IF.cUjbjcVSP2b.LG9kfg36',
  1
FROM tenants t
JOIN roles r ON r.code = 'VISUALIZADOR'
WHERE t.slug = 'demo'
ON DUPLICATE KEY UPDATE
  role_id = VALUES(role_id),
  full_name = VALUES(full_name),
  password_hash = VALUES(password_hash),
  active = VALUES(active);
