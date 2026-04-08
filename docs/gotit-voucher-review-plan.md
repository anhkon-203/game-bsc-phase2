# GotIt Voucher Review Plan (No Code Change)

Date: 2026-04-07
Scope: review all Got It voucher related code in plugin game-bsc, check git changes, assess code cleanliness, and define remediation plan without modifying application logic now.

## 1) Current Verdict

Status: NOT CLEAN for release yet.

Reason summary:
- Security blockers are present.
- Some correctness/performance risks are still open.
- Git working tree contains noisy non-source artifacts.

## 2) Reviewed Areas

Main files reviewed:
- wp-content/plugins/game-bsc/includes/api/rest-gift.php
- wp-content/plugins/game-bsc/includes/api/gotit-client.php
- wp-content/plugins/game-bsc/includes/api/gotit-ajax.php
- wp-content/plugins/game-bsc/includes/install-tables.php
- wp-content/plugins/game-bsc/includes/acf-fields.php
- wp-content/plugins/game-bsc/admin_dashboard/gotit-test.php
- wp-content/plugins/game-bsc/admin_dashboard/gift-detail.php
- wp-content/plugins/game-bsc/includes/helpers/function-custom.php
- wp-content/plugins/game-bsc/game-bsc.php

Git hygiene findings (non-code/noisy changes in working tree):
- wp-content/debug.log (very large runtime log delta)
- .tmp_check_cat_mapping.php (temporary script)
- tmp_gotit_doc.html (temporary scraped doc file)
- .claude/worktrees/... dirty marker

## 3) Prioritized Findings

### P0 Blockers

1) Nonce security check is bypassed globally in helper.
- Impact: REST endpoints relying on helper check are effectively running without nonce verification.
- Location: game_rest_perm_cb in function-custom.php currently returns true directly.

2) Transaction ownership check allows access when stored transaction user_id is 0.
- Impact: if historical/bad data has user_id = 0, another logged-in user can read voucher info by transaction_ref_id.
- Location: condition only blocks when transaction_user_id > 0 and != current user.

### P1 Should Fix

3) Voucher list pagination is done in memory.
- Impact: GET list fetches all posts first, then slices array in PHP. This can degrade with large voucher volume.
- Location: posts_per_page = -1 + array_slice based pagination.

4) Product sync total page extraction is less robust than stores extraction.
- Impact: may stop early if Got It payload uses pagination.totalPage style not covered in product loop parser.
- Location: totalPage parser in sync loop only checks limited keys.

5) Used-state fallback parser runs only when extractor function does not exist.
- Impact: if extractor exists but returns empty due payload shape drift, used state can be under-detected.
- Location: fallback check is in elseif branch tied to function_exists.

### P2 Hygiene

6) Working tree contains debug/temp artifacts unrelated to production code.
- Impact: noisy PR, hard review, accidental commit risk.

## 4) Remediation Plan

### Phase A - Security First
Owner: Backend
Priority: Immediate

Actions:
1. Restore nonce validation behavior in helper for non-dev environments.
2. Enforce strict transaction ownership in gotit-voucher-by-transaction endpoint.
3. Keep route-level permission callback aligned with real auth policy.

Definition of done:
- Unauthorized request without valid nonce returns 403.
- Logged-in user cannot access transaction_ref_id not owned by self, including rows with user_id null/0.

### Phase B - Correctness Hardening
Owner: Backend
Priority: High

Actions:
1. Improve used-status derivation flow: run payload fallback check when parsed list is empty.
2. Align product pagination parser with stores parser patterns (include nested pagination keys).

Definition of done:
- Used vouchers are detected correctly across legacy/new payload shapes.
- Full product pages are synced in environments where pagination is nested.

### Phase C - Performance and Maintainability
Owner: Backend
Priority: Medium

Actions:
1. Replace in-memory pagination with query-level paging for voucher list endpoints.
2. Optional: cache schema column presence instead of running SHOW COLUMNS inside redemption flow each request.

Definition of done:
- API response time remains stable as voucher count grows.
- DB calls in redeem path are reduced.

### Phase D - Git Hygiene and Release Gate
Owner: Dev + Reviewer
Priority: Immediate before merge

Actions:
1. Remove non-source temporary files from commit scope.
2. Exclude runtime log files from PR.
3. Keep PR focused to Got It feature files only.

Definition of done:
- Clean diff includes only intentional source/docs changes.

## 5) Test Plan After Fixes

Security tests:
- POST /vouchers/issue without nonce -> 403.
- GET /gotit-voucher-by-transaction with foreign transaction_ref_id -> 403.
- GET /gotit-voucher-by-transaction for owner -> 200.

Correctness tests:
- Issue flow returns code/link and persists transaction_ref_id, expiry metadata.
- Status reconciliation marks used vouchers when stateInfo/usedInfo is present in varied payloads.
- Product sync reaches all pages when API returns nested pagination.

Performance tests:
- Voucher list paging on page 1/2/last with large dataset.
- Compare query count and response latency before/after optimization.

## 6) Suggested Execution Order

1. Security blockers (Phase A)
2. Correctness hardening (Phase B)
3. Git cleanup for PR quality (Phase D)
4. Performance optimization (Phase C)
5. Final regression + deploy gate

## 7) Notes

- This file is planning-only.
- No application code was changed in this review step.
