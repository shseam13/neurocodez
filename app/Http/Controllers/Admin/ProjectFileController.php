<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectFileController extends Controller
{
    /**
     * Extensions refused outright.
     *
     * Files are streamed as downloads and never executed, so this is defence in
     * depth — but a .php or .htaccess sitting in a storage directory is the
     * kind of thing that becomes a hole the moment a server is misconfigured.
     */
    private const BLOCKED = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'htaccess', 'htpasswd',
        'exe', 'com', 'bat', 'cmd', 'sh', 'msi', 'dll', 'scr', 'jsp', 'asp', 'aspx',
    ];

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('create', ProjectFile::class);

        $request->validate([
            'files' => ['required', 'array', 'max:10'],
            // 20 MB matches this server's upload_max_filesize. Raising the rule
            // alone would not help — PHP rejects the upload before Laravel sees it.
            'files.*' => ['required', 'file', 'max:20480'],
            'client_visible' => ['nullable', 'boolean'],
        ]);

        $visible = $request->boolean('client_visible');
        $disk = config('filesystems.default');
        $stored = 0;

        foreach ($request->file('files') as $upload) {
            $extension = strtolower($upload->getClientOriginalExtension());

            if (in_array($extension, self::BLOCKED, true)) {
                return back()->withErrors([
                    'files' => "\"{$upload->getClientOriginalName()}\" is a file type we do not accept.",
                ]);
            }

            /*
             * Stored under a generated name, never the uploaded one.
             *
             * A client-supplied filename is untrusted input: it can carry path
             * separators, collide with an existing file, or contain characters
             * that break on another filesystem. The real name lives in the
             * database and is reattached on download.
             */
            $path = $upload->storeAs(
                "projects/{$project->id}",
                Str::ulid().($extension !== '' ? ".{$extension}" : ''),
                $disk,
            );

            $project->files()->create([
                'disk' => $disk,
                'path' => $path,
                'original_name' => $upload->getClientOriginalName(),
                'size' => $upload->getSize(),
                'mime' => $upload->getClientMimeType(),
                'client_visible' => $visible,
                'uploaded_by' => $request->user()->id,
            ]);

            $stored++;
        }

        return back()->with('status', $stored === 1
            ? 'File uploaded.'
            : "{$stored} files uploaded.");
    }

    /** Streamed through an authorisation check on every request. */
    public function download(ProjectFile $file): StreamedResponse
    {
        Gate::authorize('download', $file);

        // A row whose object is gone (a botched restore, a disk swap) should
        // read as missing rather than throwing a 500.
        abort_unless($file->exists(), 404, 'That file is no longer stored.');

        return $file->download();
    }

    public function toggleVisibility(Project $project, ProjectFile $file): RedirectResponse
    {
        Gate::authorize('update', $file);
        abort_unless($file->project_id === $project->id, 404);

        $file->update(['client_visible' => ! $file->client_visible]);

        return back()->with('status', $file->client_visible
            ? "\"{$file->original_name}\" is now visible to the client."
            : "\"{$file->original_name}\" is now internal only.");
    }

    public function destroy(Project $project, ProjectFile $file): RedirectResponse
    {
        Gate::authorize('delete', $file);
        abort_unless($file->project_id === $project->id, 404);

        // Soft delete keeps the object on disk, so an accidental removal is
        // recoverable. Only a force delete removes the stored file.
        $file->delete();

        return back()->with('status', "\"{$file->original_name}\" removed.");
    }
}
