# Changelog 📋

---

## v1.0.1 - 2026-10-08

### 🚀 Added
- Full PHP `>= 8.1` support.

### ⚡ Changed
- Removed `readonly` class modifiers to ensure PHP 8.1 compatibility (class-level `readonly` requires PHP 8.2+).

---

## v1.0.0 - 2026-09-30

### 🚀 Added
- Initial release of **MsgdPlug** for MISP.
- Client-side UI injection for MISP views (`Add`, `Edit`, `View`, `Index`).
- Dynamic **Sharing Group Blueprints** evaluation without schema changes.
- Member inspection modal (`#msgdViewModal`) in `View` and `Index` modes.
- Access control via MISP `perm_sharing_group` and email allowlist.
- Automatic CSRF token rotation (`nextToken`) for AJAX requests.
- Shell installer script (`msgd_installer.sh`) with backup, patching, and rollback.
- Docker environment script (`dev.sh`) with IDE stubs generation.
- Complete PHPUnit test suite for Controllers, DTOs, Services, and Voters.

---
