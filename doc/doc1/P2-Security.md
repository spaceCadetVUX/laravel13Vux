# P2 — Security Hardening Summary

> Implemented: 2026-04-18  
> Tests: 91/91 GREEN  
> Branch: master

---

## 1. Rate Limiting — Brute Force Protection

**Mục tiêu:** Chặn tấn công brute force vào login và register.

**Cách hoạt động:**
- Middleware `throttle:5,1` được áp dụng cho 2 route: `POST /api/v1/auth/login` và `POST /api/v1/auth/register`
- Sau 5 lần gọi trong vòng 1 phút từ cùng 1 IP → HTTP `429 Too Many Requests`
- Laravel tự động reset counter sau 1 phút

**File thay đổi:**
- `routes/api.php` — bọc login + register trong `Route::middleware('throttle:5,1')`

**Tests:** `tests/Feature/Auth/RateLimitTest.php` (2 tests)

---

## 2. Password Reset Flow

**Mục tiêu:** Cho phép user đặt lại mật khẩu qua email, không lộ thông tin user.

**Flow:**
```
POST /forgot-password (email)
  → Tìm user theo email_hash (email mã hóa ở rest)
  → Tạo random token 64 ký tự
  → Lưu Hash::make(token) vào bảng password_reset_tokens (key = email_hash)
  → Gửi ResetPasswordNotification (link đến frontend /reset-password?token=...)
  → Luôn trả 200 dù email không tồn tại (chống user enumeration)

POST /reset-password (email + token + password)
  → Tìm record theo email_hash
  → Hash::check(token, hashed_token)
  → Kiểm tra token < 60 phút (Carbon::parse)
  → Cập nhật password (auto Hash qua model cast)
  → Xóa token khỏi DB
  → Xóa toàn bộ Sanctum tokens (revoke all sessions)
```

**Bảo mật đặc biệt:**
- Email lưu dưới dạng `sha256(strtolower(email))` trong `password_reset_tokens.email` — không bao giờ lưu email plain text
- Token chỉ valid 1 lần (xóa sau khi dùng)
- Sau reset → toàn bộ thiết bị đang login bị logout (revoke Sanctum tokens)
- Anti-enumeration: `forgot-password` luôn trả `200` dù email không tồn tại

**Files tạo mới:**
| File | Vai trò |
|------|---------|
| `app/Services/Auth/PasswordResetService.php` | Logic tạo token, validate, reset password |
| `app/Notifications/Auth/ResetPasswordNotification.php` | Email notification kèm reset link |
| `app/Http/Controllers/Api/V1/Auth/PasswordResetController.php` | Thin controller gọi service |
| `app/Http/Requests/Auth/ForgotPasswordRequest.php` | Validate email format |
| `app/Http/Requests/Auth/ResetPasswordRequest.php` | Validate token + password + confirmation |

**Tests:** `tests/Feature/Auth/PasswordResetTest.php` (6 tests)
- Gửi email reset ✓
- Email không tồn tại → 200 (no enumeration) ✓
- Token hợp lệ → reset thành công ✓
- Token sai → 422 ✓
- Token hết hạn (>60 phút) → 422 ✓
- Sau reset → tất cả Sanctum tokens bị xóa ✓

---

## 3. Email Verification Flow

**Mục tiêu:** Xác minh email user sau khi đăng ký.

**Flow:**
```
POST /register
  → Tạo user
  → Gửi VerifyEmailNotification (signed URL có TTL 60 phút)
  → Trả 201 (user chưa cần verify để nhận token)

GET /email/verify/{id}/{hash}?signature=... (signed URL từ email)
  → Laravel signed middleware kiểm tra chữ ký + TTL
  → So sánh hash param với user.email_hash
  → Nếu khớp → markEmailAsVerified()
  → Idempotent: đã verify rồi → vẫn 200

POST /email/resend  [auth:sanctum]
  → Kiểm tra user chưa verify
  → Gửi lại VerifyEmailNotification
```

**Bảo mật đặc biệt:**
- URL có chữ ký tạm thời (Laravel `URL::temporarySignedRoute`) — không thể giả mạo
- Hash trong URL là `email_hash` (sha256) — không lộ email plain text
- Link hết hạn sau 60 phút → Laravel middleware tự trả `403`
- User đã verify → gọi resend sẽ nhận `422`

**Files tạo mới:**
| File | Vai trò |
|------|---------|
| `app/Notifications/Auth/VerifyEmailNotification.php` | Email notification kèm signed URL |
| `app/Http/Controllers/Api/V1/Auth/EmailVerificationController.php` | verify + resend actions |

**Files chỉnh sửa:**
| File | Thay đổi |
|------|----------|
| `app/Models/User.php` | Implement `MustVerifyEmail`, override `sendEmailVerificationNotification()` |
| `app/Services/Auth/AuthService.php` | Gọi `sendEmailVerificationNotification()` sau register |

**Tests:** `tests/Feature/Auth/EmailVerificationTest.php` (7 tests)
- Email gửi sau register ✓
- Signed URL hợp lệ → verify thành công ✓
- Đã verify → 200 idempotent ✓
- Hash sai → 403 ✓
- Link hết hạn → 403 ✓
- User chưa verify có thể resend ✓
- User đã verify không thể resend → 422 ✓

---

## 4. Sanctum Token Expiry

**Mục tiêu:** Token tự hết hạn sau 30 ngày, không tồn tại vĩnh viễn.

**Cách hoạt động:**
- Cấu hình `expiration` trong `config/sanctum.php`
- Laravel Sanctum tự kiểm tra `created_at` của token khi authenticate
- Token quá hạn → tự động trả `401 Unauthenticated`
- Có thể override qua env `SANCTUM_TOKEN_EXPIRATION` (đơn vị: phút)

**File thay đổi:**
- `config/sanctum.php`:
  ```php
  'expiration' => env('SANCTUM_TOKEN_EXPIRATION', 60 * 24 * 30), // 30 ngày
  ```

---

## Tổng quan Routes thêm vào

```
POST   /api/v1/auth/forgot-password          [throttle:60,1]  → PasswordResetController@forgot
POST   /api/v1/auth/reset-password           [throttle:60,1]  → PasswordResetController@reset
GET    /api/v1/auth/email/verify/{id}/{hash} [signed]         → EmailVerificationController@verify
POST   /api/v1/auth/email/resend             [auth:sanctum]   → EmailVerificationController@resend
```

Routes hiện tại được bọc thêm:
```
POST   /api/v1/auth/login                    [throttle:5,1]
POST   /api/v1/auth/register                 [throttle:5,1]
```

---

## Test Coverage P2

| Test file | Tests | Assertions |
|-----------|-------|------------|
| `RateLimitTest` | 2 | 2 |
| `PasswordResetTest` | 6 | 10 |
| `EmailVerificationTest` | 7 | 14 |
| **Tổng P2** | **15** | **26** |
