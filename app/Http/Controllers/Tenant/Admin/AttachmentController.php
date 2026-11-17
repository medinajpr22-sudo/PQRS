<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pqrs;
use App\Models\PqrsAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function __invoke(Pqrs $pqrs, PqrsAttachment $attachment): StreamedResponse
    {
        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }
}
