# NChanger Server (PHP 8.1+ REST API, MySQL & Web Admin)

Hệ thống trọn gói Backend REST API và Web Admin Portal phục vụ cho **NChanger.exe (Windows Client)**, **Android Services** và **Kỹ thuật viên Quản trị**.

---

## 1. Cấu trúc mã nguồn

```text
nchanger-server/
│
├── config/
│   └── config.php               # Cấu hình DB, secret key, token lifetime
│
├── database/
│   ├── schema.sql               # 12 bảng CSDL chuẩn InnoDB utf8mb4 (thêm brands, models, change_profiles)
│   └── seed.sql                 # Dữ liệu ban đầu (SuperAdmin, Brands, Galaxy S9+, ROM A12, Change Profiles, Workflow)
│
├── public/
│   ├── index.php                # Front controller API & Router dispatch (hỗ trợ GET, POST, PATCH, DELETE)
│   ├── .htaccess                # Apache / cPanel URL rewrite & Security headers
│   │
│   └── admin/                   # 🖥️ GIAO DIỆN WEB QUẢN TRỊ (WEB ADMIN)
│       ├── index.php            # Chuyển hướng sang dashboard
│       ├── login.php            # Đăng nhập bảo mật
│       ├── logout.php           # Đăng xuất & hủy session
│       ├── dashboard.php        # 10 KPI metrics, tổng license, device online, audit logs
│       ├── licenses.php         # Quản lý license (Tạo mới, gia hạn, vô hiệu hóa)
│       ├── models.php           # Quản lý model phần cứng & brand (Thêm mới, bật/tắt status)
│       ├── roms.php             # Quản lý ROM profiles (Toggle Safe Mode / Validated)
│       ├── change_profiles.php  # Quản lý Change Profiles, Workflow Codes & Policy JSON
│       ├── devices.php          # Quản lý thiết bị đăng ký, trạng thái online, khóa thiết bị
│       ├── backups.php          # Quản lý bản sao lưu và tiến trình khôi phục
│       ├── logs.php             # Tra cứu nhật ký thao tác (Audit logs)
│       ├── settings.php         # Cấu hình hệ thống & Client version
│       │
│       ├── includes/
│       │   ├── auth.php         # Session guard & DB bootloader
│       │   ├── header.php       # Layout, sidebar navigation, responsive navbar
│       │   └── footer.php
│       └── assets/
│           ├── css/admin.css    # Giao diện Dark-mode hiện đại, responsive
│           └── js/admin.js      # Modal controls & UI scripts
│
├── app/
│   ├── Core/
│   │   ├── Autoloader.php       # PSR-4 Autoloading thuần
│   │   ├── Database.php         # PDO Singleton kết nối MySQL
│   │   ├── Request.php          # Request parser (JSON, Bearer token, Client IP)
│   │   ├── Response.php         # Chuẩn hóa JSON Response & Error Codes
│   │   └── Router.php           # RESTful Router hỗ trợ regex `{id}` & Middleware (GET, POST, PUT, PATCH, DELETE)
│   │
│   ├── Middleware/
│   │   └── AuthMiddleware.php   # Xác thực Bearer Token
│   │
│   └── Controllers/
│       ├── SystemController.php        # /system/status, /system/version, /system/config
│       ├── AuthController.php          # /auth/login, /auth/refresh, /auth/logout, /auth/me
│       ├── LicenseController.php       # /license/check, /license/activate, /license/deactivate, /license/info
│       ├── ModelController.php         # CRUD /models, /models/{id}/roms, /models/{id}/change-profile
│       ├── ChangeProfileController.php # CRUD /change-profiles (Workflow codes & Policies)
│       ├── RomController.php           # /roms, /roms/check, /rom/compatibility (pre-change check), /probe
│       ├── ProfileController.php       # Legacy alias
│       ├── DeviceController.php        # /device/register, /device/heartbeat, /status
│       ├── BackupController.php        # /backup/create, /backup/list, /backup/{id}/verify
│       ├── RestoreController.php       # /restore/prepare, /restore/verify, /restore/execute
│       ├── ClientController.php        # /client/version, /client/update
│       └── OpenApiController.php       # /api/v1/openapi.json, /api/v1/docs (Swagger UI)
│
└── .env.example
```

---

## 2. API Contract chi tiết theo đặc tả mới

### 2.1. Model API

```text
GET    /api/v1/models?brand=Samsung&codename=star2lte&status=active
POST   /api/v1/models
GET    /api/v1/models/{id}
PATCH  /api/v1/models/{id}
DELETE /api/v1/models/{id}

GET    /api/v1/models/{id}/roms
GET    /api/v1/models/{id}/capabilities
GET    /api/v1/models/{id}/change-profile
```

**Mẫu Response `GET /api/v1/models/{id}/change-profile`:**
```json
{
  "success": true,
  "data": {
    "model": {
      "id": 1,
      "brand": "Samsung",
      "model_code": "SM-G965F",
      "codename": "star2lte"
    },
    "rom": {
      "id": 1,
      "name": "NChangerOS PixelExperience A12 baseline",
      "android_version": "12.1",
      "api_level": 32
    },
    "change_profile": {
      "id": 1,
      "name": "NChangerOS Star2LTE A12",
      "version": "1.0.0",
      "workflow_code": "NCHANGER_STANDARD_V1",
      "status": "active",
      "policy": {
        "backup": { "enabled": true, "required_before_workflow": true },
        "restore": { "enabled": true },
        "diagnostics": { "enabled": true },
        "recovery": { "enabled": true },
        "rom_check": { "required": true },
        "safe_mode": { "enabled": true }
      }
    }
  },
  "meta": { "request_id": "req_..." }
}
```

---

### 2.2. Change Profile API

```text
GET    /api/v1/change-profiles
POST   /api/v1/change-profiles
GET    /api/v1/change-profiles/{id}
PATCH  /api/v1/change-profiles/{id}
DELETE /api/v1/change-profiles/{id}
```

---

### 2.3. Pre-Change Compatibility API (`/rom/compatibility`)

App NChanger gọi ngay trước khi thực thi workflow để kiểm tra tương thích và lấy Workflow Code:

```http
POST /api/v1/rom/compatibility
Authorization: Bearer TOKEN
Content-Type: application/json
```

```json
{
  "model_id": 1,
  "rom_id": 1,
  "device": {
    "codename": "star2lte",
    "android_version": "12.1",
    "api_level": 32
  }
}
```

**Response thành công:**
```json
{
  "success": true,
  "data": {
    "supported": true,
    "model": { "id": 1, "codename": "star2lte" },
    "rom": { "id": 1, "android_version": "12.1", "api_level": 32 },
    "change_profile": {
      "id": 1,
      "version": "1.0.0",
      "workflow_code": "NCHANGER_STANDARD_V1"
    },
    "capabilities": {
      "backup": true,
      "restore": true,
      "recovery": true,
      "diagnostics": true,
      "app_install": true
    },
    "safe_mode": false
  }
}
```

**Nếu không khớp hoặc ROM chưa kiểm thử:**
```json
{
  "success": true,
  "data": {
    "supported": false,
    "safe_mode": true,
    "reason": "ROM_NOT_SUPPORTED"
  }
}
```

---

### 2.4. Admin Quick Creation Endpoints

Dành cho frontend Web Admin gọi qua REST API:
```text
POST /api/v1/admin/models
POST /api/v1/admin/roms
POST /api/v1/admin/change-profiles
```
