<?php

use App\Support\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Login med telefonnummer: nummeret i ens form (se App\Support\Phone), unikt */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('login_phone', 20)->nullable()->unique()->after('phone');
        });
        // Har flere samme nummer, får den første det som login (de andre kan stadig logge ind med e-mail)
        $seen = [];
        foreach (DB::table('users')->whereNotNull('phone')->orderBy('id')->get(['id', 'phone']) as $u) {
            $n = Phone::normalize($u->phone);
            if (! $n || isset($seen[$n])) continue;
            $seen[$n] = true;
            DB::table('users')->where('id', $u->id)->update(['login_phone' => $n]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('login_phone'));
    }
};
