<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Permission;

class Menu extends Model
{
    protected $table = 'menus';

    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    /**
     * Get the user that owns the Menu
     */
    public function parent_menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id', 'id');
    }

    /**
     * Get all of the comments for the Menu
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'menu_id', 'id');
    }

    public function childs(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id', 'id')->with('permissions')->orderBy('position');
    }

    public function main_permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'id', 'menu_id')
            ->where('main_permission', 1);
    }

    public function childs_main_permission(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id', 'id')
            ->with('main_permission')
            ->orderBy('position');
    }
}
