<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Storage;

/** Shows the current stamp to Admins on Finance Settings; the file itself is never public. */
class CompanyStampController extends Controller
{
    public function __invoke()
    {
        $path = CompanyProfile::current()->stamp_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
