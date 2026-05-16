<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MediaController extends Controller
{
    public function index(Request $request)
    {
        $query = Media::query()
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->when($request->search, fn($q, $search) => $q->where('title', 'like', "%{$search}%"))
            ->orderBy('title');

        $media = $query->paginate(40)->withQueryString();

        return Inertia::render('Catalog/Index', [
            'media'   => $media,
            'filters' => $request->only(['type', 'search']),
        ]);
    }
}
