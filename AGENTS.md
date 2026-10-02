# AGENTS.md

## Aturan Operasi Agent (Antigravity / Gemini)

Sesuai dengan Master Plan Pembangunan MOTIVA, Anda WAJIB mematuhi aturan berikut sebelum dan selama melakukan *coding*:

1. **Baca Dokumen Sebelum Coding**
   Selalu periksa `docs/motiva/` untuk memastikan bahwa Anda memahami konteks, business rules, dan arsitektur sebelum membuat perubahan apa pun.

2. **Jangan Mengarang Business Rule**
   Semua business rules harus berasal dari dokumen yang disepakati. Jika ada *ambiguity* (ketidakjelasan), Anda **HARUS BERHENTI** dan meminta klarifikasi dari pengguna. Jangan membuat asumsi.

3. **Jangan Hardcode Approver / User ID**
   Tidak boleh ada *hardcode* User ID atau Approver ID dalam *code*. Gunakan sistem *Resolver* atau *Policy* sesuai dengan Approval Architecture (jangan sekadar mengandalkan `manager_id`).

4. **RBAC ≠ Authority**
   Role Based Access Control (RBAC) hanya menentukan "Apa yang boleh dilakukan user", sedangkan *Authority* menentukan "Siapa yang secara struktural berwenang". Jangan campur adukkan keduanya.

5. **SQL Server Compatibility**
   Aplikasi ini akan di-deploy ke SQL Server 2022. Pastikan semua *migrations* (terutama terkait *cascade delete* yang berisiko menyebabkan *multiple cascade paths*) dan sintaks kueri berjalan dengan baik di SQL Server.

6. **Wajib Memiliki Test**
   Setiap *domain behavior* wajib memiliki test (Unit, Feature, Authorization, Workflow).

7. **Perubahan Unrelated Dilarang**
   Fokus pada *milestone* yang sedang dikerjakan. Jangan melakukan refaktor atau mengubah *file* di luar batasan fitur yang sedang aktif.

8. **Git Guardrails**
   Dilarang keras menjalankan perintah `git reset --hard`, `git clean -fd`, `git push --force`, menghapus *branch*, atau *rewrite history* tanpa instruksi eksplisit dari pengguna. Selalu kerjakan di *branch* `feature/*` dari `develop`.

*Untuk detail lengkap aturan, referensi teknis, dan arsitektur approval, baca dokumen di `docs/motiva/`.*
