<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use DB;

class CommandExecuteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function commandExecute()
    {
        $output = shell_exec('supervisorctl restart all 2>&1');


        return response()->json([
            'success' => true,
            'data' => $output,
            'message' => 'Execute successfully'
        ]);
    }

}
