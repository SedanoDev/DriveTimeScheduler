# DriveTime Scheduler - Architecture & Design Document

## 1. Decisiones de Arquitectura

### Multi-Tenancy (Core)
*   **Modelo:** Single-Database Multi-Tenant.
*   **Identificación:** Columna `school_id` en todas las tablas principales (`users`, `bookings`, `vehicles`, `packages`).
*   **Aislamiento:**
    *   **Eloquent Global Scope:** `BelongsToSchool` trait con `SchoolScope` aplicado por defecto.
    *   **Middleware:** `TenantResolver` intercepta la petición (subdominio) y configura el `current_school_id`.
    *   **Super Admin:** `withoutGlobalScopes()` para gestión cross-tenant.

### Autenticación y Seguridad
*   **Auth Strategy:** Laravel Sanctum (SPA/Mobile API + Session Web).
*   **Roles & Permisos:** `spatie/laravel-permission` (RBAC).
    *   **Roles:** `super_admin`, `school_admin`, `instructor`, `student`.
*   **Auditoría:** `spatie/laravel-activitylog` o tabla custom `activity_logs` para trazar cambios de estado críticos.

### Motor de Reservas (State Machine)
*   **Estados:**
    1.  `DRAFT`: Usuario selecciona slot. Lock temporal (Redis/Cache) de 5 min.
    2.  `CONFIRMED`: Pago/Crédito validado. Registro DB permanente.
    3.  `CHECK_IN`: Alumno/Instructor confirma asistencia (QR/Geo).
    4.  `COMPLETED`: Clase finalizada. Requiere evaluación.
    5.  `CANCELLED`: Liberación de recursos.

*   **Prevención de Conflictos:**
    *   **Pessimistic Locking:** `SELECT ... FOR UPDATE` en transacción final.
    *   **Atomic Cache Locks:** Para la fase de selección (UX friendly).

## 2. Esquema de Base de Datos (ERD Textual)

### Tablas Core
*   **schools**: `id`, `slug` (subdomain), `branding_config`, `timezone`.
*   **users**: `id`, `school_id`, `role`, `credits`.
*   **vehicles**: `id`, `school_id`, `plate`, `type` (manual/auto).
*   **bookings**:
    *   `id`, `school_id`
    *   `student_id`, `instructor_id`, `vehicle_id`
    *   `start_at`, `end_at`
    *   `status` (enum)
*   **instructor_availabilities**: Reglas de horario recurrente.
*   **booking_locks**: Locks temporales (si no se usa Redis puro).

## 3. Máquina de Estados y Transiciones

| Origen | Destino | Acción | Guard (Condición) | Efectos |
| :--- | :--- | :--- | :--- | :--- |
| (null) | `DRAFT` | `selectSlot` | Slot libre, Instructor disponible | Cache Lock (5min) |
| `DRAFT` | `CONFIRMED` | `confirmBooking` | Saldo suficiente, Lock válido | Transacción DB, -1 Crédito |
| `CONFIRMED` | `CANCELLED` | `cancelBooking` | >24h antelación (Config) | +1 Crédito (Reembolso) |
| `CONFIRMED` | `CHECK_IN` | `checkIn` | Hora inicio ±15min | Log Geo/IP |
| `CHECK_IN` | `COMPLETED` | `completeClass` | Instructor submit eval | Log Final |
| `CONFIRMED` | `COMPLETED` | `completeClass` | (Si no hubo check-in explícito) | Log Final |

## 4. Lógica BookingWizard (Livewire)

El componente `BookingWizard` gestiona el flujo de reserva reactivo.

### Pseudocódigo / Lógica Clave

```php
class BookingWizard extends Component {
    public $step = 1;
    public $filters = ['date', 'instructor', 'type'];
    public $selectedSlot = null; // {start, end, resource_ids}
    public $lockToken = null;

    // 1. Carga reactiva de slots disponibles
    public function loadSlots() {
        // Filtra por Availability rules
        // Excluye Bookings existentes (CONFIRMED)
        // Excluye Locks activos (DRAFT de otros users)
    }

    // 2. Selección con bloqueo atómico
    public function selectSlot($slotId) {
        // Intenta adquirir lock en Cache (TTL 300s)
        if (Cache::add("lock:{$slotId}", $this->token, 300)) {
            $this->selectedSlot = $slotId;
            $this->step = 2; // Confirmación
        } else {
            $this->addError('slot', 'Ocupado por otro estudiante.');
        }
    }

    // 3. Confirmación Transaccional
    public function confirm() {
        DB::transaction(function() {
            // Re-validar estado DB (Pessimistic Lock)
            // Verificar saldo crédito
            // Crear Booking (Status: CONFIRMED)
            // Descontar saldo
            // Liberar Cache Lock
        });
        $this->emit('booking-confirmed');
    }
}
```

## 5. Route List (Resumen)

### Public / Auth
*   `POST /login`
*   `POST /logout`

### Student (Mobile-First Web App)
*   `GET /student/dashboard`
*   `GET /student/book` (Livewire Wizard)
*   `GET /api/calendar/feed` (JSON para FullCalendar)
*   `GET /student/my-bookings`

### Instructor
*   `GET /instructor/schedule`
*   `POST /api/bookings/{id}/check-in`
*   `POST /api/bookings/{id}/evaluate`

### Admin (School)
*   `RESOURCE /admin/instructors`
*   `RESOURCE /admin/vehicles`
*   `GET /admin/settings` (Branding, Timezone)

## 6. UX & Accesibilidad Checklist

### General
- [ ] **Mobile First**: Layout funciona en 360px width sin scroll horizontal.
- [ ] **Touch Targets**: Botones y slots de calendario mín 44x44px.
- [ ] **Feedback**: Loading spinners en todas las acciones de red (Livewire `wire:loading`).
- [ ] **Color**: Contraste ratio 4.5:1 para texto normal.

### Calendario (FullCalendar)
- [ ] **Vistas**: AgendaDay (Mobile), AgendaWeek (Desktop).
- [ ] **Eventos**: Colores distintos por estado (Gris: Pasado, Verde: Confirmado, Amarillo: Pendiente).
- [ ] **Interacción**: Clic en evento abre Modal, no redirección.

### Flujo de Reserva
- [ ] **Sin Fricción**: Máximo 3 clics para reservar si hay "Instructor Favorito".
- [ ] **Validación**: Mensajes de error amigables ("Ups, alguien te ganó el lugar" vs "Error 500").
- [ ] **Recuperación**: Si expira el DRAFT (5 min), avisar y refrescar disponibilidad.
