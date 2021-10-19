<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use App\Models\Office;
use App\Models\User;

class OfficeImageControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     *
     * @test
     */
    public function test_it_uploads_an_image() {
        Storage::fake();

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->post("/api/offices/{$office->id}/images",[
            'image' => UploadedFile::fake()->image('image.jpg')
        ]);

        //$response->dump();

        $response->assertCreated();
        Storage::disk('public')->assertExists(
            $response->json('data.path')
        );
    }

    /**
     *
     * @test
     */
    public function test_it_deletes_an_image() {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'image.jpg'
        ]);

        $image2 = $office->images()->create([
            'path' => 'image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertOk();
        $this->assertModelMissing($image);
        //$response->dump();
    }

    /**
     *
     * @test
     */
    public function test_it_does_not_deletes_the_only_image() {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertUnprocessable();
    }

    /**
     *
     * @test
     */
    public function test_it_does_not_delete_featured_image() {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'image.jpg'
        ]);

        $image2 = $office->images()->create([
            'path' => 'image.jpg'
        ]);

        $office->update([
            'featured_image_id' => $image->id,
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['image' => 'Cannot delete the featured image']);
    }
}
