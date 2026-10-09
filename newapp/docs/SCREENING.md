# Screening Demo Script (Internal)

Dokumen ini hanya untuk developer/demo. Jangan ditampilkan sebagai fitur aplikasi.

## Value Proposition

Application ini mengelola project berdasarkan BOQ, mulai dari penyusunan biaya, approval, pembayaran, sampai monitoring progress pekerjaan.

## Akun Demo

- Email: `superadmin@example.com`
- Password: `password`

## Seed Projects

| Code | Scenario | Status Workflow |
| --- | --- | --- |
| PRJ-001 | A — Draft | BOQ DRAFT |
| PRJ-002 | B — Waiting | WAITING APPROVAL |
| PRJ-003 | C — Revision | REVISION REQUIRED |
| PRJ-004 | D — Payment | APPROVED + PAYMENT |
| PRJ-005 | E — Progress | IN PROGRESS (paid + progress) |

Reset data:

```bash
php artisan migrate:fresh --seed
```

## Live Demo Flow (end-to-end)

1. Login sebagai Superadmin.
2. Buka Dashboard — jelaskan value proposition singkat.
3. Buka Projects — tunjukkan 5 project di berbagai tahap.
4. Pilih **PRJ-001** (Draft) → tunjukkan Next Action: Create BOQ.
5. Buat/buka BOQ → jelaskan COGS = kelompok biaya, Item = pekerjaan.
6. Tambah COGS + Item → Save Draft → Submit BOQ.
7. Buka **PRJ-002** atau lanjut review → Review BOQ.
8. Tunjukkan Confirm vs Request Revision (helper text).
9. Request Revision pada satu item → muncul REVISION REQUIRED + kode REV.
10. Open Revision → perbaiki item → Submit BOQ Again.
11. Confirm BOQ → APPROVED → Next Action Payment Planning.
12. Buat Payment Plan (jenis: Bisa Dibayar Langsung) → Process Payment.
13. Jelaskan: payment selesai ≠ progress 100%.
14. Buka Progress → update item → tunjukkan Overall Progress & nilai selesai.
15. Logout.

## Talking Points

- Next Action selalu menunjukkan langkah berikutnya.
- Workflow strip: PROJECT → BOQ → APPROVAL → PAYMENT → PROGRESS.
- Progress = Total Nilai Selesai ÷ Total BOQ × 100%.
- Role/permission tetap aktif di backend; UI hanya menyembunyikan aksi yang tidak relevan.
