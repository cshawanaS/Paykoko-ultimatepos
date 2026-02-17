<?php

namespace Modules\Koko\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    /**
     * Adds Koko settings to the side menu
     */
    public function modifyAdminMenu()
    {
        $business_id = session()->get('user.business_id');
        $is_admin = auth()->user()->hasRole('Admin#' . $business_id) ? true : false;

        if (auth()->user()->can('business_settings.access')) {
            Menu::modify('admin-sidebar-menu', function ($menu) {
                $menu->url(
                    action([\Modules\Koko\Http\Controllers\KokoController::class, 'index']),
                    'Koko Settings',
                    ['icon' => 'fa fas fa-credit-card', 'active' => request()->segment(1) == 'koko']
                )->order(91);
            });
        }
    }

    /**
     * Define module permissions
     */
    public function user_permissions()
    {
        return [
            [
                'value' => 'koko.access_settings',
                'label' => 'Access Koko Settings',
                'default' => false
            ],
        ];
    }
}
