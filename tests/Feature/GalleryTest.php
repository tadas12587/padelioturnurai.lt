<?php

namespace Tests\Feature;

use App\Models\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_gallery_removes_its_files_from_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('galleries/a.png', 'x');
        Storage::disk('public')->put('galleries/b.png', 'x');

        $gallery = Gallery::create(['name' => 'Rėmėjai', 'images' => ['galleries/a.png', 'galleries/b.png']]);
        Storage::disk('public')->assertExists('galleries/a.png');

        $gallery->delete();

        Storage::disk('public')->assertMissing('galleries/a.png');
        Storage::disk('public')->assertMissing('galleries/b.png');
    }
}
