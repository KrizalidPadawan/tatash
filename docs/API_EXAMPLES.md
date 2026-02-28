# API Examples

## POST /api/v1/auth/login
Request:
```json
{
  "email": "admin@acme.com",
  "password": "StrongPass!123"
}
```

Response:
```json
{
  "success": true,
  "data": {
    "access_token": "<JWT>",
    "refresh_token": "<JWT_REFRESH>",
    "token_type": "Bearer",
    "expires_in": 900
  },
  "errors": []
}
```

## GET /api/v1/transactions?page=1&per_page=20
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 91,
        "category_id": 3,
        "type": "expense",
        "amount": "24.90",
        "description": "Taxi",
        "transaction_date": "2026-02-22",
        "created_at": "2026-02-22 18:00:10"
      }
    ],
    "meta": {
      "page": 1,
      "per_page": 20,
      "total": 1020,
      "total_pages": 51
    }
  },
  "errors": []
}
```

## POST /api/v1/transactions
```json
{
  "category_id": 3,
  "type": "expense",
  "amount": 120.45,
  "description": "Suscripción",
  "transaction_date": "2026-02-22"
}
```

## GET /api/v1/reports/monthly?month=2026-02
```json
{
  "success": true,
  "data": {
    "month": "2026-02",
    "summary": [
      {"type": "income", "total": "18000.00"},
      {"type": "expense", "total": "7425.10"}
    ]
  },
  "errors": []
}
```

## GET /api/v1/dashboard/summary
```json
{
  "success": true,
  "data": {
    "total_income": "25000.00",
    "total_expense": "9900.00",
    "tx_count": 245
  },
  "errors": []
}
```
