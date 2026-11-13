<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\Request;

/** Den fulde ændringslog (alle handlinger i systemet) – Indstillinger → Ændringslog. */
class AuditController extends Controller
{
    public function index(Request $r)
    {
        abort_unless($r->user()->may('audit.view'), 403);

        return Audit::rows(financeOnly: false);
    }
}
