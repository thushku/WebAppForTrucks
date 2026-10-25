<?php

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentFile;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Files of documents, kept small and private (blueprint 01 §5, guideline
 * "uploads 250–500 KB").
 *
 *  · Photos and scans are checked by their content, turned the right way up,
 *    re-saved as JPEG at most 1600 px on the long side and stepped down in
 *    quality until they are under 500 KB. Re-saving also drops the camera's
 *    hidden data (location, phone model).
 *  · PDFs must really be PDFs (they start with "%PDF-") and be at most 2 MB.
 *  · Files live in the company's own folder on the private disk and are only
 *    served through a checked route; a share link shows images with a
 *    watermark burnt in.
 */
class DocumentFiles
{
    public const IMAGE_TARGET_BYTES = 500 * 1024;
    public const PDF_MAX_BYTES      = 2 * 1024 * 1024;
    public const UPLOAD_MAX_KB      = 8 * 1024;
    private const LONG_SIDE         = 1600;

    public function __construct(private CompanyContext $context) {}

    public function store(Document $document, UploadedFile $upload, ?string $label = null): DocumentFile
    {
        $real = (string) $upload->getRealPath();
        $head = (string) file_get_contents($real, false, null, 0, 5);
        $info = @getimagesize($real);

        if ($head === '%PDF-') {
            if ($upload->getSize() > self::PDF_MAX_BYTES) {
                throw ValidationException::withMessages(['files' => __('A PDF may be at most 2 MB. Scan it at a lower resolution or upload photos of the pages.')]);
            }
            [$bytes, $mime, $ext] = [(string) file_get_contents($real), 'application/pdf', 'pdf'];
        } elseif ($info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            [$bytes, $mime, $ext] = [\App\Support\Images::shrink($real, self::LONG_SIDE, self::IMAGE_TARGET_BYTES), 'image/jpeg', 'jpg'];
        } else {
            throw ValidationException::withMessages(['files' => __(':name is not a photo (JPG, PNG, WebP) or a PDF.', ['name' => $upload->getClientOriginalName()])]);
        }

        $company = $this->context->company();
        $path    = "companies/{$company->uuid}/documents/" . now()->format('Y/m') . '/' . Str::random(40) . '.' . $ext;
        Storage::disk('local')->put($path, $bytes);

        return DocumentFile::create([
            'document_id'   => $document->id,
            'version'       => $document->version,
            'path'          => $path,
            'original_name' => mb_substr($upload->getClientOriginalName(), 0, 200),
            'mime'          => $mime,
            'bytes'         => strlen($bytes),
            'sha256'        => hash('sha256', $bytes),
            'label'         => $label,
            'created_by'    => auth()->id(),
        ]);
    }

    /** The image again with "Copy shared on … for …" across it, as JPEG bytes. */
    public function watermarked(DocumentFile $file, string $text): string
    {
        $image = imagecreatefromstring(Storage::disk('local')->get($file->path));
        $w = imagesx($image);
        $h = imagesy($image);
        $ink    = imagecolorallocatealpha($image, 190, 20, 20, 70);
        $font   = 5;
        $lineW  = imagefontwidth($font) * strlen($text);
        $step   = imagefontheight($font) * 6;

        // Repeated lines, offset row by row, so the text cannot be cropped away.
        for ($y = $step / 2, $row = 0; $y < $h; $y += $step, $row++) {
            for ($x = -($row % 2) * ($lineW / 2); $x < $w; $x += $lineW + 60) {
                imagestring($image, $font, (int) $x, (int) $y, $text, $ink);
            }
        }

        ob_start();
        imagejpeg($image, null, 75);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
