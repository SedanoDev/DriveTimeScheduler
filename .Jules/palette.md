## 2024-05-23 - [UX Pattern: Fail-Fast & Friendly Errors]
**Learning:** Checking for user session and credits explicitly in Livewire components prevents 500 errors and allows for user-friendly error messages instead of technical crashes.
**Action:** Always validate `auth()->check()` before accessing user properties and use class constants for error messages to ensure consistency.
