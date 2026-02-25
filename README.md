# Financial SaaS (PHP 8.3 + Apache + MySQL)

## Run locally
1. Crear DB y cargar esquema:
```bash
mysql -u root -p -e "CREATE DATABASE financial_saas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p financial_saas < database/schema.sql
```
2. Configurar variables de entorno (Apache `SetEnv` o `.htaccess`):
```bash
APP_ENV=production
APP_DEBUG=0
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=financial_saas
DB_USERNAME=root
DB_PASSWORD=secret
JWT_SECRET=replace_with_64_char_secret
```
3. Apuntar DocumentRoot a `public/` y habilitar módulos:
```bash
sudo a2enmod rewrite headers deflate expires
sudo systemctl reload apache2
```

## Architecture (light hexagonal)
```text
[HTTP Interface]
  Router -> Controllers -> DTOs
      |         |
      v         v
 [Middleware] [Application Services]
      |         |
      v         v
  Auth/RBAC   Repository Ports
                 |
                 v
         [Infrastructure: PDO/MySQL, Cache APCu/File, Logger]
                 |
                 v
               [Domain Rules]
```

## Seguridad incluida
- JWT HS256 + refresh token rotativo
- RBAC por permisos
- Rate limiting por IP+ruta
- Protección brute force login
- CSP + headers hardening
- Cookies `HttpOnly`, `Secure`, `SameSite=Strict`
- Auditoría en `audit_logs`

## Escalabilidad horizontal
- Stateless API (token-based)
- Storage de recibos en `/storage/receipts` (preparado para mover a S3/MinIO)
- DB separable por host dedicado
- Apache listo para reverse proxy (Nginx futuro)
