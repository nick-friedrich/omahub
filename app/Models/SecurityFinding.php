<?php

namespace App\Models;

use App\Security\DocumentationFile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'security_scan_id',
    'rule',
    'severity',
    'file',
    'line',
    'snippet',
    'description',
])]
class SecurityFinding extends Model
{
    public function scan(): BelongsTo
    {
        return $this->belongsTo(SecurityScan::class, 'security_scan_id');
    }

    /**
     * Whether this finding lives in a documentation file (README, docs/, *.md).
     * Documentation findings are reported but do not drive the whole-scan risk
     * level — see ScanEngine.
     */
    public function isDocumentation(): bool
    {
        return DocumentationFile::matches((string) $this->file);
    }

    /**
     * Path relative to the repository root. Raw stored paths carry the tarball
     * root directory prefix (e.g. `my-plugin-abc123/install.sh`).
     */
    public function repositoryPath(): string
    {
        $parts = explode('/', str_replace('\\', '/', (string) $this->file));

        return count($parts) > 1 ? implode('/', array_slice($parts, 1)) : (string) $this->file;
    }

    /** Compact path for display: last two segments, elided when deeper. */
    public function displayPath(): string
    {
        $parts = explode('/', $this->repositoryPath());

        return count($parts) > 2 ? '…/'.implode('/', array_slice($parts, -2)) : implode('/', $parts);
    }
}

