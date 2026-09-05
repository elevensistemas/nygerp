<?php

namespace App\Http\Controllers;

use App\Models\SystemParameter;
use App\Models\SystemParameterLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SystemParameterController extends Controller
{
    public function index()
    {
        $parameters = SystemParameter::orderBy('key')->get();
        $logs = SystemParameterLog::with(['parameter', 'user'])
            ->latest()
            ->limit(40)
            ->get();

        $newDriverDays = \App\Models\DriverImportSetting::where('setting_key', 'driver_new_days')->first();
        $newDriverColor = \App\Models\DriverImportSetting::where('setting_key', 'driver_new_color')->first();

        return view('configuration.general', compact('parameters', 'logs', 'newDriverDays', 'newDriverColor'));
    }

    public function update(Request $request, SystemParameter $parameter)
    {
        $request->validate([
            'value' => ['nullable', 'string', 'max:255'],
        ]);

        $oldValue = $parameter->value;
        $newValue = $request->input('value');

        $parameter->update(['value' => $newValue]);
        $parameter->refreshCache();

        SystemParameterLog::create([
            'system_parameter_id' => $parameter->id,
            'user_id' => Auth::id(),
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);

        return back()->with('ok', 'Parámetro actualizado correctamente.');
    }
}
