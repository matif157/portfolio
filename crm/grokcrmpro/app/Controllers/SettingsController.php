<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\View;
use App\Services\SettingsService;

class SettingsController
{
    public function index(): void
    {
        require_permission('settings.manage');
        $rows = Database::pdo()->query('SELECT * FROM settings ORDER BY setting_group, id')->fetchAll();
        $groups = [];
        foreach ($rows as $r) {
            $groups[$r['setting_group']][] = $r;
        }
        View::render('settings/index', ['title' => 'Settings', 'groups' => $groups]);
    }

    public function save(): void
    {
        require_permission('settings.manage');
        Security::requireCsrf();
        foreach ($_POST as $key => $value) {
            if ($key === '_csrf') continue;
            if (is_array($value)) continue;
            SettingsService::set($key, trim($value));
        }
        Auth::audit('settings.update', 'Settings', 'Settings updated');
        flash('success', 'Settings saved.');
        redirect('/settings');
    }
}
