# 📝 Fitur Register User

## ✨ Fitur Baru yang Ditambahkan

Sistem sekarang memiliki **halaman register publik** untuk user biasa dengan ketentuan:

### 🎯 Ketentuan Register

1. **Siapa yang bisa register?**
   - Siapa saja bisa mendaftar melalui halaman register
   - Tidak perlu approval dari admin

2. **Role otomatis:**
   - User yang register akan otomatis mendapat role **"Viewer"**
   - Role Viewer memiliki **read-only access** (hanya bisa melihat, tidak bisa edit/delete)

3. **Permissions yang didapat:**
   - `read dashboard` - Lihat dashboard
   - `read users` - Lihat daftar users
   - `read roles` - Lihat daftar roles
   - `read permissions` - Lihat daftar permissions

4. **Status akun:**
   - Akun langsung aktif (`is_active = true`)
   - Bisa langsung login setelah register

## 🔗 Akses Halaman

### Register
- **URL:** `http://localhost:8000/register`
- **Link:** Tersedia di halaman login

### Login
- **URL:** `http://localhost:8000/login`
- **Link:** Tersedia di halaman register

## 📸 Tampilan

### Halaman Register
- Design menggunakan AdminLTE
- Form fields:
  - Full Name
  - Email
  - Password
  - Confirm Password
- Info box: Menjelaskan bahwa user akan terdaftar sebagai Viewer
- Link ke halaman login

### Halaman Login (Updated)
- Design menggunakan AdminLTE
- Form fields:
  - Email
  - Password
  - Remember Me checkbox
- Link ke:
  - Forgot Password
  - **Register (NEW!)**

## 🧪 Testing

### Test Register User Baru

1. Buka browser ke `http://localhost:8000/register`
2. Isi form:
   - Name: `Test User`
   - Email: `testuser@example.com`
   - Password: `password`
   - Confirm Password: `password`
3. Klik tombol **Register**
4. User akan otomatis login dan redirect ke dashboard
5. Cek role: User akan memiliki role "Viewer"

### Verifikasi Permissions

Setelah register, user hanya bisa:
- ✅ Lihat dashboard
- ✅ Lihat daftar users
- ✅ Lihat daftar roles
- ✅ Lihat daftar permissions
- ❌ Tidak bisa create/edit/delete apapun

## 🔐 Keamanan

### Proteksi yang Diterapkan

1. **Email Validation:**
   - Email harus unique (tidak boleh duplikat)
   - Format email harus valid

2. **Password Validation:**
   - Minimum 8 karakter (sesuai Laravel default)
   - Harus konfirmasi password
   - Password di-hash dengan bcrypt

3. **Auto Role Assignment:**
   - Tidak bisa memilih role sendiri
   - Otomatis dapat role "Viewer"
   - Tidak bisa escalate privilege

4. **Active Status:**
   - Akun langsung aktif
   - Tidak perlu email verification (bisa ditambahkan jika perlu)

## 🎨 Customization

### Mengubah Default Role

Jika ingin mengubah role default untuk user yang register, edit file:
`app/Http/Controllers/Auth/RegisteredUserController.php`

```php
// Ubah dari 'viewer' ke role lain
$user->assignRole('viewer'); // Ganti dengan role yang diinginkan
```

### Menambah Email Verification

Jika ingin menambahkan email verification sebelum user bisa login:

1. Uncomment `MustVerifyEmail` di model User
2. Update RegisteredUserController untuk tidak auto-login
3. User harus verify email dulu sebelum bisa login

### Menonaktifkan Register

Jika ingin menonaktifkan register publik:

1. Hapus route register di `routes/auth.php`
2. Atau tambahkan middleware untuk restrict access

## 📊 Database Changes

### Tabel Users
Tidak ada perubahan struktur tabel, hanya menambahkan:
- `is_active` sudah ada
- `last_login_at` sudah ada

### Tabel role_user
Otomatis terisi saat user register dengan:
- `user_id` - ID user yang baru register
- `role_id` - ID role "viewer"

## 🚀 Deployment Notes

Untuk production:

1. **Pertimbangkan Email Verification:**
   - Aktifkan email verification untuk keamanan
   - Cegah spam registration

2. **Rate Limiting:**
   - Tambahkan rate limiting untuk prevent abuse
   - Laravel sudah include throttle middleware

3. **CAPTCHA:**
   - Pertimbangkan menambahkan reCAPTCHA
   - Cegah bot registration

4. **Terms & Conditions:**
   - Tambahkan checkbox untuk T&C
   - Simpan consent di database

## 📝 Notes

- Fitur register ini cocok untuk sistem yang membutuhkan user registration
- User yang register tidak bisa langsung jadi admin (harus di-promote oleh admin)
- Admin tetap harus dibuat melalui halaman Admin Management atau seeder

---

**Update:** Fitur register berhasil ditambahkan dengan role "Viewer" sebagai default! 🎉
