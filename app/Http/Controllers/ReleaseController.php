<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ReleaseController extends Controller
{
    public function index(Request $request)
    {
        $dir = resource_path('releases/en');
        $files = [];
        if (is_dir($dir)) {
            $files = glob($dir.'/*.md');
        }

        $items = collect($files)->map(function ($path) {
            $version = pathinfo($path, PATHINFO_FILENAME);
            $body = @file_get_contents($path) ?: '';
            $date = date('Y-m-d H:i:s', filemtime($path));
            $excerpt = Str::limit(strip_tags(Str::markdown($body)), 200);
            return [
                'version' => $version,
                'body' => $body,
                'excerpt' => $excerpt,
                'date' => $date,
            ];
        })->sortByDesc('date')->values();

        $page = (int) $request->get('page', 1);
        $perPage = 10;

        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('releases.index', ['releases' => $paginator]);
    }

    public function show(Request $request, $version)
    {
        $path = resource_path("releases/en/{$version}.md");
        if (! file_exists($path)) {
            abort(404);
        }

        $body = file_get_contents($path);
        $date = date('Y-m-d H:i:s', filemtime($path));
        $html = Str::markdown($body);

        return view('releases.show', [
            'version' => $version,
            'date' => $date,
            'html' => $html,
        ]);
    }
}
