## 2026-02-12 - Fail-fast validation in Livewire mount
**Learning:** Adding validation logic in the `mount` method (like credit checks) provides immediate feedback and prevents users from entering invalid workflows, significantly improving UX compared to waiting for a final confirmation step to fail.
**Action:** Always check for critical prerequisites in `mount` and use `$this->addError()` to inform the user upfront.
