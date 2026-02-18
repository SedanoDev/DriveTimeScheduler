## 2024-05-22 - [Livewire Fail-Fast Patterns]
**Learning:** Livewire components can provide immediate "fail-fast" feedback in `mount()` (e.g., credit checks), but must guard against guest users (using `auth()->check()`) to prevent false-positive error messages.
**Action:** Always wrap user-specific checks in `auth()->check()` and use `addError` in `mount` to block invalid workflows early.
