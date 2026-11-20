<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_JUDGE = 'judge';
    public const ROLE_TABULATOR = 'tabulator';

    public const ROLES = [
        self::ROLE_ADMIN => 'Administrator',
        self::ROLE_TABULATOR => 'Tabulator',
        self::ROLE_JUDGE => 'Judge',
    ];

    protected $fillable = ['name', 'username', 'email', 'password', 'role', 'event_id', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'event_id' => 'integer',
            'notifications_read_id' => 'integer',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isTabulator(): bool
    {
        return $this->role === self::ROLE_TABULATOR;
    }

    /** Admins and tabulators can watch live scoring, open/lock categories and see results. */
    public function canTabulate(): bool
    {
        return $this->isAdmin() || $this->isTabulator();
    }

    /** Admins: every event. Tabulators: their own event, or every event if not tied to one. */
    public function canAccessEvent(Event $event): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->isTabulator() && (! $this->event_id || $this->event_id === $event->id);
    }

    /** The event a generated tabulator account belongs to. */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ucfirst($this->role);
    }

    public function homeRoute(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => 'admin.dashboard',
            self::ROLE_TABULATOR => 'tabulator.dashboard',
            default => 'judge.dashboard',
        };
    }

    public function isJudge(): bool
    {
        return $this->role === self::ROLE_JUDGE;
    }

    public function judgedEvents()
    {
        return $this->belongsToMany(Event::class, 'event_judge', 'user_id', 'event_id');
    }

    public function submissions()
    {
        return $this->hasMany(CategorySubmission::class, 'judge_id');
    }

    public function scopeJudges($query)
    {
        return $query->where('role', self::ROLE_JUDGE);
    }
}
