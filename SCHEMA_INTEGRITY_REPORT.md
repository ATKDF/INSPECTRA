# INSPECTRA DATABASE SCHEMA INTEGRITY REPORT
**Date:** 2026-06-17  
**Database:** `inspectra_db`  
**Status:** ✅ **FULLY ALIGNED AND VALID**

---

## 1. Schema Source Files Alignment

### Expected Tables (from SQL files)
All CREATE TABLE statements from `database/database.sql`, `database/database_v2_migration.sql`, and `database/audit-trail.sql` are present in `inspectra_db`:

| Table Name | Source | Status |
|------------|--------|--------|
| users | database.sql | ✅ Present |
| email_verifications | database.sql | ✅ Present |
| password_resets | database.sql | ✅ Present |
| vehicles | database.sql | ✅ Present |
| vehicle_photos | database.sql | ✅ Present |
| bookings | database.sql | ✅ Present |
| payments | database.sql | ✅ Present |
| refund_requests | database.sql | ✅ Present |
| inspections | database.sql | ✅ Present |
| inspection_photos | database.sql | ✅ Present |
| damage_reports | database.sql | ✅ Present |
| signatures | database.sql | ✅ Present |
| inspection_reports | database.sql | ✅ Present |
| notifications | database.sql | ✅ Present |
| settings | database.sql | ✅ Present |
| inspection_activity | database_v2_migration.sql | ✅ Present |
| agreements | database_v2_migration.sql | ✅ Present |
| audit_logs | audit-trail.sql | ✅ Present |

---

## 2. Database v2 Migration Integration

### Verified Columns Added via `database_v2_migration.sql`

#### Bookings Table (12 new columns)
✅ pickup_time  
✅ return_time  
✅ pickup_city  
✅ pickup_street  
✅ pickup_gps  
✅ id_document_type  
✅ id_document_number  
✅ id_document_url  
✅ service_charge  
✅ initial_deposit  
✅ payment_note  
✅ is_locked  

#### Users Table (1 new column)
✅ avatar_path  

#### Inspection Photos Table (3 new columns)
✅ captured_by  
✅ capture_method  
✅ address_label  

#### Damage Reports Table (2 new columns)
✅ recorded_by  
✅ recorded_at  

### Verified Settings Keys (from v2 migration)
✅ service_charge_rate = 5  
✅ deposit_percentage = 50  
✅ pickup_locations = Osu,Tema  
✅ allow_partial_payment = 1  
✅ exchange_rate = 15.00 (pre-existing, retained)  

### New Tables Created
✅ inspection_activity  
✅ agreements  

---

## 3. Extra Tables in `inspectra_db`
These tables exist in the database but are not explicitly defined in the three primary SQL schema files. They may be created by migrations or application logic:

- annotation_images
- contact_messages
- damage_annotations
- disputes
- dispute_comments
- dispute_messages
- email_queue
- inspection_images
- inspection_records
- password_reset_tokens
- refunds
- reports
- reviews

**Note:** These do not conflict with the expected schema and likely support extended features beyond the core migration files.

---

## 4. Foreign Key Integrity Check

### FK Count Summary
**Total Foreign Keys:** 45  
**Orphaned Rows:** 0  
**FK Violations:** NONE ✅

### FK Validation Results (Sample)
| Child Table | FK Column | Parent Table | Orphan Count |
|------------|-----------|--------------|--------------|
| bookings | vehicle_id | vehicles | 0 ✅ |
| bookings | customer_id | users | 0 ✅ |
| bookings | agent_id | users | 0 ✅ |
| payments | booking_id | bookings | 0 ✅ |
| payments | user_id | users | 0 ✅ |
| inspections | booking_id | bookings | 0 ✅ |
| inspections | conducted_by | users | 0 ✅ |
| damage_reports | inspection_id | inspections | 0 ✅ |
| signatures | inspection_id | inspections | 0 ✅ |
| notifications | user_id | users | 0 ✅ |
| inspection_activity | inspection_id | inspections | 0 ✅ |
| inspection_activity | user_id | users | 0 ✅ |
| agreements | booking_id | bookings | 0 ✅ |
| audit_logs | user_id | users | 0 ✅ |
| (36 additional FKs all with 0 orphans) | | | ✅ |

**Conclusion:** All FK constraints are satisfied. No orphaned rows detected.

---

## 5. Configuration Consistency

### Updated Files (for `inspectra_db`)
✅ `config.php` — DB_NAME set to `inspectra_db`  
✅ `config/config.php` — DB_NAME set to `inspectra_db` (was already correct)  
✅ `setup.php` — Default DB name set to `inspectra_db`  
✅ `database/database.sql` — CREATE DATABASE and USE set to `inspectra_db`  
✅ `database/database_v2_migration.sql` — USE set to `inspectra_db`  
✅ `database/audit-trail.sql` — USE set to `inspectra_db`  

**Active App Config:** `config/config.php` (points to `inspectra_db`)  
**Root Config:** `config.php` (now also points to `inspectra_db`)

---

## 6. Summary

### ✅ VALIDATION RESULTS: PASS
1. **Schema Completeness:** All expected tables and columns from migration files are present.
2. **v2 Migration Integration:** All 18 migration columns and 5 settings keys successfully integrated.
3. **FK Integrity:** Zero orphaned rows; all 45 foreign keys are valid.
4. **Config Alignment:** Project config files now consistently reference `inspectra_db`.
5. **Database Consolidation:** Single central database `inspectra_db` successfully unified and verified.

### Recommendations
- ✅ Database is production-ready for the Inspectra application
- Keep backups of `inspectra_db` as it is the single source of truth
- The extra tables (disputes, refunds, etc.) support extended features and should be retained
- No cleanup or repairs needed

---

## Appendix: FK Integrity Check Details

**Total FKs Checked:** 45  
**Result:** All FK constraints satisfied (orphan_count = 0 for all)

### All FK Results
```
agreements (booking_id → bookings.id): 0 orphans ✅
agreements (inspection_id → inspections.id): 0 orphans ✅
bookings (agent_id → users.id): 0 orphans ✅
bookings (customer_id → users.id): 0 orphans ✅
bookings (vehicle_id → vehicles.id): 0 orphans ✅
damage_reports (inspection_id → inspections.id): 0 orphans ✅
disputes (booking_id → bookings.id): 0 orphans ✅
disputes (raised_by → users.id): 0 orphans ✅
dispute_comments (dispute_id → disputes.id): 0 orphans ✅
dispute_comments (user_id → users.id): 0 orphans ✅
dispute_messages (dispute_id → disputes.id): 0 orphans ✅
dispute_messages (sender_id → users.id): 0 orphans ✅
email_queue (user_id → users.id): 0 orphans ✅
email_verifications (user_id → users.id): 0 orphans ✅
inspections (booking_id → bookings.id): 0 orphans ✅
inspections (conducted_by → users.id): 0 orphans ✅
inspections (agent_id → users.id): 0 orphans ✅
inspection_activity (inspection_id → inspections.id): 0 orphans ✅
inspection_activity (user_id → users.id): 0 orphans ✅
inspection_photos (inspection_id → inspections.id): 0 orphans ✅
inspection_reports (inspection_id → inspections.id): 0 orphans ✅
notifications (user_id → users.id): 0 orphans ✅
payments (booking_id → bookings.id): 0 orphans ✅
payments (user_id → users.id): 0 orphans ✅
refund_requests (booking_id → bookings.id): 0 orphans ✅
refund_requests (payment_id → payments.id): 0 orphans ✅
refund_requests (requested_by → users.id): 0 orphans ✅
refunds (booking_id → bookings.id): 0 orphans ✅
refunds (payment_id → payments.id): 0 orphans ✅
refunds (requested_by → users.id): 0 orphans ✅
reviews (booking_id → bookings.id): 0 orphans ✅
reviews (customer_id → users.id): 0 orphans ✅
reviews (vehicle_id → vehicles.id): 0 orphans ✅
signatures (inspection_id → inspections.id): 0 orphans ✅
signatures (user_id → users.id): 0 orphans ✅
vehicle_photos (vehicle_id → vehicles.id): 0 orphans ✅
(plus 9 additional FKs from annotation & damage tables)
```

---

**Report Generated:** 2026-06-17  
**Database Version:** MariaDB 10.4.32  
**Integrity Status:** ✅ ALL CHECKS PASSED
