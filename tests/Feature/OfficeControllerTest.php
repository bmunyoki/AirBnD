<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Http\Response;

use App\Models\Office;
use App\Models\User;
use App\Models\Reservation;
use App\Models\Image;
use App\Models\Tag;

class OfficeControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_list_all_offices_paginated() {
        Office::factory(3)->create();

        $response = $this->get('/api/offices');

        $response->assertOk();

        // Assert the returned json data has 3 items - the ones we created above
        $response->assertJsonCount(3, 'data');

        // Assert atleast the ID of the first item is not null
        $this->assertNotNull($response->json('data')[0]['id']);

        // Assert there is meta for the paginated results. You can include links as well
        $this->assertNotNull($response->json('meta'));

        //dd($response->json());

    }

    /**
     * @test
     */
    public function test_it_lists_all_offices_including_hidden_for_current_user() {
        $user = User::factory()->create();
        Office::factory(3)->for($user)->create();

        Office::factory()->hidden()->for($user)->create();
        Office::factory()->pending()->for($user)->create();

        $this->actingAs($user);

        $response = $this->get('/api/offices?user_id='.$user->id);

        $response->assertOk();

        // Assert the returned json data has 3 items - the ones we created above
        $response->assertJsonCount(5, 'data');

    }

    public function test_only_returns_approved_and_visible_offices () {
        Office::factory(3)->create();

        // Create hidden office
        $hiddenOffice = Office::factory()->create([
            'hidden' => true
        ]);

        // Create another office whose approval is pending
        $pendingOffice = Office::factory()->create([
            'approval_status' => Office::APPROVAL_PENDING
        ]);

        $response = $this->get('/api/offices');

        $response->assertOk();

        // Assert the returned json data has 3 items - the ones we created above. The last two should not be returned
        $response->assertJsonCount(3, 'data');
    }

    public function test_filters_by_host () {
        Office::factory()->create();

        $host = User::factory()->create();

        // Create an office for this host
        $office = Office::factory()->for($host)->create();

        $response = $this->get('/api/offices?user_id='.$host->id);

        $response->assertOk();

        // Assert the returned json data has 3 items - the ones we created above. The last two should not be returned
        $response->assertJsonCount(1, 'data');

        // The returned record ID should match the id of the office we created
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    public function test_filters_by_users_reservations () {
        $visitor = User::factory()->create();
        $office = Office::factory()->create();

        Reservation::factory()->for($office)->for($visitor)->create();

        // Create another reservation for a different user
        Reservation::factory()->for(Office::factory()->create())->create();

        $response = $this->get('/api/offices?visitor_id='.$visitor->id);

        $response->assertOk();

        // Assert the returned json data has 3 items - the ones we created above. The last two should not be returned
        $response->assertJsonCount(1, 'data');

        // The returned record ID should match the id of the office we created
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    public function test_offices_include_images_tags_and_user () {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();

        $office = Office::factory()->for($user)->create();

        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.png']);


        $response = $this->get('/api/offices');

        $response->assertOk();

        // Test it includes tags and images
        $this->assertIsArray($response->json('data')[0]['tags']);
        $this->assertIsArray($response->json('data')[0]['images']);

        // Test only one image and tag
        $this->assertCount(1, $response->json('data')[0]['images']);
        $this->assertCount(1, $response->json('data')[0]['tags']);

        // Test it belongs to the created user
        $this->assertEquals($user->id, $response->json('data')[0]['user']['id']);

        //dd($response->json());
    }

    public function test_it_returns_number_of_active_reservations() {
        $office = Office::factory()->create();
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCELLED]);

        $response = $this->get('/api/offices');

        $response->assertOk();   
        $this->assertEquals(2, $response->json('data')[0]['reservations_count']);
        //dd($response->json());
    }

    public function test_it_orders_by_distance_when_location_provided () {
        $msaOffice = Office::factory()->create([
            'lat' => '-4.0351857',
            'lng' => '39.5960506',
            'title' => 'Mombasa office'
        ]);

        $nakOffice = Office::factory()->create([
            'lat' => '-0.3158116',
            'lng' => '36.0086478',
            'title' => 'Nakuru office'
        ]);

        

        $response = $this->get('/api/offices?lat=-1.303205&lng=36.7069654');
            
        $response->assertOk();   
        $this->assertEquals('Nakuru office', $response->json('data')[0]['title']);
        //$response->dump();
    }

    /**
     * @test
    * 
    */
    public function test_it_shows_an_office() {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();

        $office = Office::factory()->for($user)->create();

        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.png']);

        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCELLED]);

        
        $response = $this->get('/api/offices/'.$office->id);

        //$response->assertOk();   
        //$this->assertEquals(2, $response->json('data')[0]['reservations_count']);

        $response->assertOk();

        // Test it includes tags and images
        $this->assertIsArray($response->json('data')['tags']);
        $this->assertIsArray($response->json('data')['images']);

        // Test only one image and tag
        $this->assertCount(1, $response->json('data')['images']);
        $this->assertCount(1, $response->json('data')['tags']);

        // Test it belongs to the created user
        $this->assertEquals($user->id, $response->json('data')['user']['id']);

    }

    /**
     * @test
    * 
    */
    public function test_it_creates_an_office() {
        $user = User::factory()->createQuietly();

        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/offices', [
            'title' => 'Nairobi office',
            'description' => 'Hyhgn gghhghg',
            'lat' => '-0.3158116',
            'lng' => '36.0086478',
            'address_line1' => 'Adress line 1',
            'price_per_day' => 118.0,
            'monthly_discount' => 5,
            'tags' => [
                $tag1->id, $tag2->id
            ],
        ]);

        //$response->dump();

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Nairobi office')
            ->assertJsonPath('data.approval_status', Office::APPROVAL_PENDING)
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertDatabaseHas('offices', [
            'title' => 'Nairobi office'
        ]);
    }

    /**
     * @test
    * 
    */
    public function test_it_only_user_with_ability_can_create() {
        $user = User::factory()->createQuietly();

        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();

        // To create a token uncomment below.
        //$token = $user->createToken('test', ['office.create']);

        // Since we need to test user without ability cant, create a token without ability
        $token = $user->createToken('test', []);
        
        $response = $this->postJson('/api/offices', [], [
            'Authorization' => 'Bearer '.$token->plainTextToken
        ]);

        $response->assertStatus(403);
    }

    /**
     * @test
    * 
    */
    public function test_it_updates_an_office() {
        $user = User::factory()->create();

        $office = Office::factory()->for($user)->create();

        $tags = Tag::factory(2)->create();
        $anotherTag = Tag::factory()->create();

        $office->tags()->attach($tags);

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id, [
            'title' => 'Updated Nairobi office',
            'tags' => [$tags[0]->id, $anotherTag->id]
        ]);

        //$response->dump();

        $response->assertOk()
            ->assertJsonCount(2, 'data.tags')
            ->assertJsonPath('data.title', 'Updated Nairobi office')
            ->assertJsonPath('data.approval_status', Office::APPROVAL_PENDING)
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertDatabaseHas('offices', [
            'title' => 'Updated Nairobi office'
        ]);
    }

    /**
     * @test
    * 
    */
    public function test_it_updates_featured_image_of_an_office() {
        $user = User::factory()->create();

        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id, [
            'featured_image_id' => $image->id,
        ]);

        //$response->dump();

        $response->assertOk()
            ->assertJsonPath('data.featured_image_id', $image->id);
    }

    
    /**
     * @test
    * 
    */
    public function test_it_does_not_update_an_office_thats_not_theirs() {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $office = Office::factory()->for($anotherUser)->create();

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id, [
            'title' => 'Updated Nairobi office',
        ]);

        //$response->dump();

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
    * 
    */
    public function test_it_deletes_an_office() {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/'.$office->id);

        //$response->dump();

        $response->assertOk();
        $this->assertSoftDeleted($office);
    }

    /**
     * @test
    * 
    */
    public function test_it_cannot_delete_an_office_with_reservations() {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        Reservation::factory(2)->for($office)->create();

        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/'.$office->id);

        //$response->dump();

        //$response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // The above assertion can also be written as below which is better
        $response->assertUnprocessable();
        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'deleted_at' => null
        ]);
    }
}
