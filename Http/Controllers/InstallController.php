<?php

namespace Modules\Koko\Http\Controllers;

use App\System;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class InstallController extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();

            // Set module version in system table
            System::addProperty('koko_version', '1.0');

            // Run migrations
            Artisan::call('module:migrate', ['module' => 'Koko', '--force' => true]);

            // Install View Hooks
            $this->_installViewHooks();

            DB::commit();

            $output = ['success' => 1,
                'msg' => 'Koko module installed successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());

            $output = ['success' => 0,
                'msg' => 'Something went wrong, please try again'
            ];
        }

        return redirect()
            ->action('\App\Http\Controllers\Install\ModulesController@index')
            ->with('status', $output);
    }

    /**
     * Update the module
     */
    public function update()
    {
        if (!auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();

            System::addProperty('koko_version', '1.0');

            Artisan::call('module:migrate', ['module' => 'Koko', '--force' => true]);

            // Install View Hooks
            $this->_installViewHooks();

            DB::commit();

            $output = ['success' => 1,
                'msg' => 'Koko module updated successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());

            $output = ['success' => 0,
                'msg' => 'Something went wrong, please try again'
            ];
        }

        return redirect()
            ->action('\App\Http\Controllers\Install\ModulesController@index')
            ->with('status', $output);
    }
    
    /**
     * Uninstall the module
     */
    public function uninstall()
    {
        if (!auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();

            // Remove module version from system table
            System::where('key', 'koko_version')->delete();

            // Remove View Hooks
            $this->_uninstallViewHooks();

            DB::commit();

            $output = ['success' => 1,
                'msg' => 'Koko module uninstalled successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());

            $output = ['success' => 0,
                'msg' => 'Something went wrong, please try again'
            ];
        }

        return redirect()
            ->action('\App\Http\Controllers\Install\ModulesController@index')
            ->with('status', $output);
    }

    /**
     * Inject hooks into core views
     */
    private function _installViewHooks()
    {
        // 1. Guest Payment Form (sale_pos.partials.guest_payment_form)
        $file2 = resource_path('views/sale_pos/partials/guest_payment_form.blade.php');
        if (file_exists($file2)) {
            $content = file_get_contents($file2);
            if (strpos($content, "@includeIf('koko::partials.guest_payment_hook')") === false) {
                // Insert before @else block of payment status check
                $pattern = '/(@else\s*<table class="table no-border">)/';
                $replacement = "\n                        @includeIf('koko::partials.guest_payment_hook')\n\n                    " . '$1';
                
                $content = preg_replace($pattern, $replacement, $content, 1);
                file_put_contents($file2, $content);
            }
        }
    }

    /**
     * Remove hooks from core views
     */
    private function _uninstallViewHooks()
    {
        $file2 = resource_path('views/sale_pos/partials/guest_payment_form.blade.php');
        if (file_exists($file2)) {
            $content = file_get_contents($file2);
            $content = str_replace("\n                        @includeIf('koko::partials.guest_payment_hook')\n\n                    ", "", $content);
            file_put_contents($file2, $content);
        }
    }
}
