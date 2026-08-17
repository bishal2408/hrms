<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The employee record this login belongs to, if one has been linked.
     *
     * Nullable by design: an account can exist before HR creates the employee
     * record, and most self-service screens depend on this being present.
     *
     * @return HasOne<Employee, $this>
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            // Back-office panel: only staff-facing roles, not every employee.
            'admin' => $this->hasAnyRole(['super_admin', 'hr_admin', 'payroll_accountant', 'manager']),
            // Self-service panel: every authenticated employee.
            'employee' => true,
            default => false,
        };
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
