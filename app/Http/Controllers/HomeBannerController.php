<?php

namespace App\Http\Controllers;

use App\Models\HomeBanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Home page images (hero slides, category tiles, lifestyle gallery, editorial).
 * Admins and managers pick which ones show and in what order.
 */
class HomeBannerController extends Controller
{
    private const UPLOAD_DIR = 'home';

    /**
     * Visible images in display order. Hidden ones are included only for staff asking for all=1.
     */
    public function index(Request $request)
    {
        $query = HomeBanner::ordered();

        if (! ($request->boolean('all') && $this->isStaff($request))) {
            $query->active();
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules() + [
            'section' => ['required', Rule::in(HomeBanner::SECTIONS)],
        ]);

        // New images go to the end of their section.
        $data['position'] = (int) HomeBanner::where('section', $data['section'])->max('position') + 1;

        return response()->json(HomeBanner::create($data), 201);
    }

    public function update(Request $request, HomeBanner $banner)
    {
        $data = $request->validate($this->rules(partial: true));
        $oldImage = $banner->image;

        $banner->update($data);

        if ($banner->image !== $oldImage) {
            $this->deleteUpload($oldImage);
        }

        return response()->json($banner);
    }

    public function destroy(HomeBanner $banner)
    {
        $banner->delete();
        $this->deleteUpload($banner->image);

        return response()->json(['message' => 'Image removed']);
    }

    /**
     * Save the order of one section. `ids` lists that section's images top to bottom.
     */
    public function reorder(Request $request)
    {
        $data = $request->validate([
            'section' => ['required', Rule::in(HomeBanner::SECTIONS)],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct', Rule::exists('home_banners', 'id')->where('section', $request->input('section'))],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $index => $id) {
                HomeBanner::whereKey($id)->update(['position' => $index + 1]);
            }
        });

        return response()->json(HomeBanner::where('section', $data['section'])->ordered()->get());
    }

    /**
     * Store an uploaded image and return its public URL, to be saved on a banner.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $path = $request->file('image')->store(self::UPLOAD_DIR, 'public');

        return response()->json(['url' => asset('storage/'.$path)], 201);
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'image' => [$required, 'string', 'max:2048'],
            'eyebrow' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'text' => ['nullable', 'string', 'max:500'],
            'cta' => ['nullable', 'string', 'max:255'],
            'link' => ['nullable', 'string', 'max:2048'],
            'alt' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Remove a file we uploaded once nothing points at it. External URLs are left alone.
     */
    private function deleteUpload(?string $url): void
    {
        $marker = '/storage/'.self::UPLOAD_DIR.'/';
        if (! $url || ! Str::contains($url, $marker)) {
            return;
        }

        if (HomeBanner::where('image', $url)->exists()) {
            return;
        }

        Storage::disk('public')->delete(self::UPLOAD_DIR.'/'.Str::after($url, $marker));
    }
}
