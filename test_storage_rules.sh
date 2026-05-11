#!/bin/bash

# Test Archive Storage Rules - Priority Uniqueness
# Berdasarkan data actual di database

BASE="http://localhost:8000/api/v1"

# Get API Token
API_TOKEN=$(php artisan tinker --execute="
\$user = \App\Models\User::where('username', 'admin')->first();
echo \$user->createToken('test-curl-token')->plainTextToken;
" 2>/dev/null | tail -1)

AUTH="Authorization: Bearer $API_TOKEN"
JSON="Content-Type: application/json"
ACCEPT="Accept: application/json"

echo "=== DATA AWAL DI DATABASE ==="
echo "ID 1:  cat=1, sub=1 (Data Pribadi),          pri=5"
echo "ID 2:  cat=1, sub=2 (Nilai),                  pri=2"
echo "ID 3:  cat=2, sub=NULL,                       pri=1"
echo "ID 4:  cat=3, sub=9 (Modul Ajar),             pri=1"
echo "ID 5:  cat=4, sub=13 (Surat Masuk),           pri=2"
echo "ID 6:  cat=4, sub=NULL,                       pri=3"
echo "ID 7:  cat=5, sub=NULL,                       pri=1"
echo "ID 8:  cat=6, sub=23 (Bimbingan Konseling),   pri=2"
echo "ID 9:  cat=9, sub=NULL,                       pri=4"
echo "ID 10: cat=1, sub=2 (Nilai),                  pri=1"
echo "ID 11: cat=1, sub=1 (Data Pribadi),           pri=3"
echo ""

# ==========================================
echo "=========================================="
echo "TEST STORE - Priority Uniqueness"
echo "=========================================="

echo ""
echo "--- Test 1: DUPLIKAT - cat=1, sub=2, pri=1 (SUDAH ADA di ID 10) -> HARUS GAGAL ---"
curl -s -X POST "$BASE/archive-storage-rules" \
  -H "$AUTH" -H "$JSON" -H "$ACCEPT" \
  -d '{"category_id":1,"subcategory_id":2,"cabinet_id":10,"priority":1}' | python3 -c "
import json,sys
r=json.load(sys.stdin)
if 'errors' in r: print('  RESULT: GAGAL ✓ (duplikat terdeteksi)')
else: print('  RESULT: BERHASIL ✗ (seharusnya gagal!)')
print('  Response:', json.dumps(r, indent=4))
"

echo ""
echo "--- Test 2: BERBEDA SUB - cat=1, sub=3 (Absensi), pri=1 (belum ada) -> HARUS BERHASIL ---"
curl -s -X POST "$BASE/archive-storage-rules" \
  -H "$AUTH" -H "$JSON" -H "$ACCEPT" \
  -d '{"category_id":1,"subcategory_id":3,"cabinet_id":10,"priority":1}' | python3 -c "
import json,sys
r=json.load(sys.stdin)
if 'data' in r:
    print('  RESULT: BERHASIL ✓ (priority 1 boleh untuk subcategory beda)')
    print('  Created ID:', r['data']['id'])
else: print('  RESULT: GAGAL ✗ (seharusnya berhasil!)')
print('  Response:', json.dumps(r, indent=4))
"

echo ""
echo "--- Test 3: BERBEDA PRIORITY - cat=1, sub=2, pri=5 (pri 5 belum ada di sub 2) -> HARUS BERHASIL ---"
curl -s -X POST "$BASE/archive-storage-rules" \
  -H "$AUTH" -H "$JSON" -H "$ACCEPT" \
  -d '{"category_id":1,"subcategory_id":2,"cabinet_id":10,"priority":5}' | python3 -c "
import json,sys
r=json.load(sys.stdin)
if 'data' in r:
    print('  RESULT: BERHASIL ✓ (priority beda untuk subcategory sama)')
    print('  Created ID:', r['data']['id'])
else: print('  RESULT: GAGAL ✗ (seharusnya berhasil!)')
print('  Response:', json.dumps(r, indent=4))
"

echo ""
echo "--- Test 4: KATEGORI TANPA SUB - cat=7 (Alumni), sub=NULL, pri=4 -> HARUS GAGAL (pri 4 sudah dipakai cat 9) ---"
# Note: ini sebenarnya beda category_id, jadi HARUS BERHASIL
curl -s -X POST "$BASE/archive-storage-rules" \
  -H "$AUTH" -H "$JSON" -H "$ACCEPT" \
  -d '{"category_id":7,"subcategory_id":null,"cabinet_id":10,"priority":4}' | python3 -c "
import json,sys
r=json.load(sys.stdin)
if 'data' in r:
    print('  RESULT: BERHASIL ✓ (category_id beda, priority 4 boleh)')
    print('  Created ID:', r['data']['id'])
else:
    print('  RESULT: GAGAL')
print('  Response:', json.dumps(r, indent=4))
"

echo ""
echo "--- Test 5: KATEGORI TANPA SUB DUPLIKAT - cat=7, sub=NULL, pri=4 -> HARUS GAGAL (baru dibuat di Test 4) ---"
curl -s -X POST "$BASE/archive-storage-rules" \
  -H "$AUTH" -H "$JSON" -H "$ACCEPT" \
  -d '{"category_id":7,"subcategory_id":null,"cabinet_id":10,"priority":4}' | python3 -c "
import json,sys
r=json.load(sys.stdin)
if 'errors' in r: print('  RESULT: GAGAL ✓ (duplikat terdeteksi)')
else: print('  RESULT: BERHASIL ✗ (seharusnya gagal!)')
print('  Response:', json.dumps(r, indent=4))
"

echo ""
echo "--- Test 6: SIMULASI SKENARIO KAMU - a=2, b=1, c=1 ---"
echo "  a: cat=8 (MBG), sub=NULL, pri=2"
curl -s -X POST "$BASE/archive-storage-rules" \
  -H "$AUTH" -H "$JSON" -H "$ACCEPT" \
  -d '{"category_id":8,"subcategory_id":null,"cabinet_id":10,"priority":2}' | python3 -c "
import json,sys;r=json.load(sys.stdin)
print('  a: ' + ('BERHASIL' if 'data' in r else 'GAGAL'))
"

echo "  b: cat=10 (Pengembang Sekolah), sub=NULL, pri=1"
curl -s -X POST "$BASE/archive-storage-rules" \
  -H "$AUTH" -H "$JSON" -H "$ACCEPT" \
  -d '{"category_id":10,"subcategory_id":null,"cabinet_id":10,"priority":1}' | python3 -c "
import json,sys;r=json.load(sys.stdin)
print('  b: ' + ('BERHASIL' if 'data' in r else 'GAGAL'))
"

echo "  c: cat=10, sub=NULL, pri=1 (SAMA DENGAN b -> HARUS GAGAL)"
curl -s -X POST "$BASE/archive-storage-rules" \
  -H "$AUTH" -H "$JSON" -H "$ACCEPT" \
  -d '{"category_id":10,"subcategory_id":null,"cabinet_id":10,"priority":1}' | python3 -c "
import json,sys
r=json.load(sys.stdin)
if 'errors' in r: print('  c: GAGAL ✓ (a=2,b=1,c=1 tidak boleh karena b dan c sama!)')
else: print('  c: BERHASIL ✗ (seharusnya gagal!)')
"

echo ""
echo "  c (FIX): cat=10, sub=NULL, pri=2 (BEDA dengan b -> HARUS BERHASIL)"
curl -s -X POST "$BASE/archive-storage-rules" \
  -H "$AUTH" -H "$JSON" -H "$ACCEPT" \
  -d '{"category_id":10,"subcategory_id":null,"cabinet_id":10,"priority":2}' | python3 -c "
import json,sys;r=json.load(sys.stdin)
if 'data' in r: print('  c(fix): BERHASIL ✓ (a=2,b=1,c=2 BOLEH!)')
else: print('  c(fix): GAGAL ✗')
"

# ==========================================
echo ""
echo "=========================================="
echo "TEST UPDATE - Priority Uniqueness"
echo "=========================================="

echo ""
echo "--- Test 7: UPDATE DUPLIKAT - ID 2 (cat=1,sub=2,pri=2) ubah pri=1 -> HARUS GAGAL (pri 1 sudah ada di ID 10) ---"
curl -s -X PATCH "$BASE/archive-storage-rules/2" \
  -H "$AUTH" -H "$JSON" -H "$ACCEPT" \
  -d '{"priority":1}' | python3 -c "
import json,sys
r=json.load(sys.stdin)
if 'errors' in r: print('  RESULT: GAGAL ✓ (duplikat priority terdeteksi)')
else: print('  RESULT: BERHASIL ✗ (seharusnya gagal!)')
print('  Response:', json.dumps(r, indent=4))
"

echo ""
echo "--- Test 8: UPDATE BEDA SUB - ID 2 (cat=1,sub=2,pri=2) ubah pri=3 -> HARUS BERHASIL (pri 3 baru di sub 2) ---"
curl -s -X PATCH "$BASE/archive-storage-rules/2" \
  -H "$AUTH" -H "$JSON" -H "$ACCEPT" \
  -d '{"priority":3}' | python3 -c "
import json,sys
r=json.load(sys.stdin)
if 'data' in r: print('  RESULT: BERHASIL ✓ (priority 3 belum ada di sub 2)')
else: print('  RESULT: GAGAL ✗')
print('  Response:', json.dumps(r, indent=4))
"

echo ""
echo "--- Test 9: UPDATE IGNORE DIRI SENDIRI - ID 11 (cat=1,sub=1,pri=3) update pri=3 -> HARUS BERHASIL ---"
curl -s -X PATCH "$BASE/archive-storage-rules/11" \
  -H "$AUTH" -H "$JSON" -H "$ACCEPT" \
  -d '{"priority":3}' | python3 -c "
import json,sys
r=json.load(sys.stdin)
if 'data' in r: print('  RESULT: BERHASIL ✓ (ignore diri sendiri saat unique check)')
else: print('  RESULT: GAGAL ✗')
"

echo ""
echo "--- Test 10: UPDATE KATEGORI+PRIORITY - ID 3 (cat=2,sub=NULL,pri=1) -> cat=11,pri=99 ---"
echo "  Category 11 (Backup) tanpa subcategory, priority 99 belum ada -> HARUS BERHASIL"
curl -s -X PATCH "$BASE/archive-storage-rules/3" \
  -H "$AUTH" -H "$JSON" -H "$ACCEPT" \
  -d '{"category_id":11,"priority":99}' | python3 -c "
import json,sys
r=json.load(sys.stdin)
if 'data' in r: print('  RESULT: BERHASIL ✓ (category baru + priority baru)')
else: print('  RESULT: GAGAL')
print('  Response:', json.dumps(r, indent=4))
"

# ==========================================
echo ""
echo "=========================================="
echo "FINAL STATE"
echo "=========================================="
php artisan tinker --execute="
\$rules = \App\Models\ArchiveStorageRule::select('id', 'category_id', 'subcategory_id', 'priority')->orderBy('id')->get();
foreach (\$rules as \$r) {
    echo sprintf('ID %d: cat=%d, sub=%s, pri=%d', \$r->id, \$r->category_id, \$r->subcategory_id ?? 'NULL', \$r->priority) . PHP_EOL;
}
" 2>/dev/null

# Cleanup
php artisan tinker --execute="
\$user = \App\Models\User::where('username', 'admin')->first();
\$user->tokens()->where('name', 'test-curl-token')->delete();
" 2>/dev/null
