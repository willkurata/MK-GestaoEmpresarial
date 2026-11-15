<?php

namespace App\Http\Controllers\Mk;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PropertyPhotoController extends Controller
{
    /** Maximum size of each uploaded photo, in kilobytes. */
    public const MAX_PHOTO_KILOBYTES = 8192;

    /** Maximum number of files accepted in a single upload request. */
    public const MAX_PHOTOS_PER_UPLOAD = 10;

    public function store(Request $request, Property $property): RedirectResponse
    {
        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:'.self::MAX_PHOTOS_PER_UPLOAD],
            'photos.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_PHOTO_KILOBYTES],
        ]);

        /** @var list<UploadedFile> $files */
        $files = $request->file('photos');
        $currentCount = $property->photos()->count();

        if ($currentCount + count($files) > Property::MAX_PHOTOS) {
            throw ValidationException::withMessages([
                'photos' => 'Cada imóvel pode ter no máximo '.Property::MAX_PHOTOS.' fotos. Restam '
                    .max(0, Property::MAX_PHOTOS - $currentCount).'.',
            ]);
        }

        $disk = (string) config('filesystems.property_photos_disk');
        $nextPosition = ((int) $property->photos()->max('position')) + ($currentCount > 0 ? 1 : 0);
        $storedPaths = [];

        try {
            DB::transaction(function () use ($files, $property, $disk, &$nextPosition, &$storedPaths): void {
                foreach ($files as $file) {
                    $path = $file->store('properties/'.$property->id, $disk);
                    if ($path === false) {
                        throw ValidationException::withMessages(['photos' => 'Não foi possível salvar uma das fotos. Tente novamente.']);
                    }
                    $storedPaths[] = $path;

                    $property->photos()->create([
                        'disk' => $disk,
                        'path' => $path,
                        'position' => $nextPosition++,
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($storedPaths);

            throw $exception;
        }

        $property->syncCoverPhoto();

        return back()->with('success', count($files) === 1 ? 'Foto adicionada.' : count($files).' fotos adicionadas.');
    }

    public function setCover(Request $request, Property $property): RedirectResponse
    {
        $validated = $request->validate([
            'photo_id' => ['required', 'integer', Rule::exists('property_photos', 'id')->where('property_id', $property->id)],
        ]);

        $property->forceFill(['cover_photo_id' => $validated['photo_id']])->save();

        return back()->with('success', 'Foto de capa atualizada.');
    }

    public function reorder(Request $request, Property $property): RedirectResponse
    {
        $validated = $request->validate([
            'photo_ids' => ['required', 'array'],
            'photo_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $currentIds = $property->photos()->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
        /** @var list<int> $requestedIds */
        $requestedIds = array_map(intval(...), $validated['photo_ids']);
        $sortedRequestedIds = $requestedIds;
        sort($sortedRequestedIds);

        if ($sortedRequestedIds !== $currentIds) {
            throw ValidationException::withMessages([
                'photo_ids' => 'Envie todas as fotos deste imóvel, cada uma uma única vez, na nova ordem.',
            ]);
        }

        DB::transaction(function () use ($requestedIds, $property): void {
            foreach ($requestedIds as $position => $photoId) {
                $property->photos()->whereKey($photoId)->update(['position' => $position]);
            }
        });

        return back()->with('success', 'Ordem das fotos atualizada.');
    }

    public function destroy(Property $property, PropertyPhoto $photo): RedirectResponse
    {
        if (! $property->isDraft() && $property->photos()->count() <= 1) {
            throw ValidationException::withMessages([
                'photos' => 'Um imóvel visível no site precisa de ao menos uma foto. Adicione outra antes de remover esta.',
            ]);
        }

        DB::transaction(function () use ($property, $photo): void {
            if ($property->cover_photo_id === $photo->id) {
                $property->forceFill(['cover_photo_id' => null])->save();
            }
            $photo->delete();
        });

        Storage::disk($photo->disk)->delete($photo->path);
        $property->refresh()->syncCoverPhoto();

        return back()->with('success', 'Foto removida.');
    }
}
